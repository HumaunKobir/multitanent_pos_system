<?php

namespace App\Services;

use App\Enums\StockDistributionStatus;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockDistribution;
use App\Models\StockDistributionProduct;

class StockDistributionService
{
    public function __construct(
        private InventoryStockService $stock,
        private ProductBranchReplicationService $replication,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPendingDistribution(array $data, ?int $branchId, ?int $purchaseId = null): StockDistribution
    {
        $distribution = StockDistribution::create([
            'branch_id' => $branchId,
            'from_branch_id' => Branch::resolveMainBranchId(),
            'to_branch_id' => (int) $data['to_branch_id'],
            'date' => $data['date'],
            'comment' => $data['comment'] ?? null,
            'purchase_id' => $purchaseId,
            'status' => StockDistributionStatus::Pending,
            'serial' => 'INVT'.str_pad((string) (StockDistribution::max('id') + 1), 8, '0', STR_PAD_LEFT),
        ]);

        foreach ($this->buildPendingProductLines($data, $branchId) as $line) {
            $distribution->products()->create($line);
        }

        return $distribution->load('products');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public function buildPendingProductLines(array $data, ?int $branchId): array
    {
        $lines = [];

        foreach ($data['items'] as $item) {
            $qty = (float) $item['quantity'];

            if ($qty <= 0) {
                continue;
            }

            $productId = (int) $item['product_id'];
            $variationId = $item['variation_id'] ? (int) $item['variation_id'] : null;
            $mainStockBefore = $this->mainWarehouseStock($productId, $variationId);

            if ($mainStockBefore < $qty) {
                throw new \RuntimeException('Quantity exceeds available main branch stock.');
            }

            $batchMaps = $this->dispatchLine(
                (int) $data['to_branch_id'],
                $productId,
                $variationId,
                $qty,
            );

            $lines[] = [
                'branch_id' => $branchId,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $qty,
                'main_stock_before' => $mainStockBefore,
                'source_batches' => $batchMaps['source_batches'],
                'destination_batches' => $batchMaps['destination_batches'],
            ];
        }

        if ($lines === []) {
            throw new \RuntimeException('At least one line with quantity greater than zero is required.');
        }

        return $lines;
    }

    public function mainWarehouseStock(int $productId, ?int $variationId): float
    {
        $mainBranchId = Branch::resolveMainBranchId();

        if ($variationId) {
            return (float) (ProductVariation::query()
                ->whereKey($variationId)
                ->where('product_id', $productId)
                ->where('branch_id', $mainBranchId)
                ->value('stock') ?? 0);
        }

        return (float) Batch::query()
            ->where('product_id', $productId)
            ->atBranchWarehouse($mainBranchId)
            ->sum('available');
    }

    /**
     * Reserve stock at main branch for a pending distribution.
     *
     * @return array{source_batches: array<int|string, float>, destination_batches: array<int|string, float>}
     */
    public function dispatchLine(int $toBranchId, int $productId, ?int $variationId, float $qty): array
    {
        $fromBranchId = Branch::resolveMainBranchId();

        if ($variationId) {
            return $this->dispatchVariation(
                $fromBranchId,
                $toBranchId,
                $productId,
                $variationId,
                $qty,
            );
        }

        $sourceBatchMap = $this->stock->deductFifo(
            $fromBranchId,
            $productId,
            $qty,
            fn (Batch $batch, float $deductQty) => $batch->distributionOutStock($deductQty)
        );

        return [
            'source_batches' => $sourceBatchMap,
            'destination_batches' => [],
        ];
    }

    /**
     * Complete a pending distribution line at the destination branch.
     *
     * @return array{destination_batches: array<int|string, float>}
     */
    public function receiveLine(StockDistributionProduct $line, int $toBranchId): array
    {
        if ($line->isReceived()) {
            throw new \RuntimeException('This product line has already been received.');
        }

        $qty = (float) $line->quantity;
        $productId = (int) $line->product_id;
        $variationId = $line->variation_id ? (int) $line->variation_id : null;
        $destinationProduct = $this->resolveDestinationProduct($productId, $toBranchId);

        if ($variationId) {
            return [
                'destination_batches' => $this->receiveVariation(
                    $toBranchId,
                    $productId,
                    $destinationProduct->id,
                    $variationId,
                    $qty,
                ),
            ];
        }

        $sourceBatches = Batch::query()
            ->whereIn('id', array_keys($line->source_batches ?? []))
            ->get()
            ->keyBy('id');

        $destinationBatchMap = [];

        foreach (($line->source_batches ?? []) as $sourceBatchId => $deductQty) {
            $sourceBatch = $sourceBatches->get((int) $sourceBatchId);

            if (! $sourceBatch) {
                throw new \RuntimeException('Source batch not found.');
            }

            $destinationBatch = Batch::firstOrCreate(
                [
                    'branch_id' => $toBranchId,
                    'product_id' => $destinationProduct->id,
                    'purchase_price' => $sourceBatch->purchase_price,
                    'expiry_date' => $sourceBatch->expiry_date,
                    'serial' => $sourceBatch->serial,
                ],
                ['available' => 0]
            );

            $destinationBatch->increment('available', $deductQty);
            $destinationBatch->refresh();
            $destinationBatch->distributionInStock($deductQty);

            if (isset($destinationBatchMap[$destinationBatch->id])) {
                $destinationBatchMap[$destinationBatch->id] += $deductQty;
            } else {
                $destinationBatchMap[$destinationBatch->id] = $deductQty;
            }
        }

        return ['destination_batches' => $destinationBatchMap];
    }

    /**
     * @param  list<int>  $lineIds
     * @return list<StockDistributionProduct>
     */
    public function receiveLines(StockDistribution $distribution, array $lineIds, int $userId): array
    {
        $distribution->loadMissing('products');
        $toBranchId = (int) $distribution->to_branch_id;

        $lines = $distribution->products
            ->filter(fn (StockDistributionProduct $line) => in_array((int) $line->id, $lineIds, true) && ! $line->isReceived())
            ->values();

        if ($lines->isEmpty()) {
            throw new \RuntimeException('No pending lines selected for receipt.');
        }

        $receivedLines = [];

        foreach ($lines as $line) {
            $batchMaps = $this->receiveLine($line, $toBranchId);

            $line->update([
                'destination_batches' => $batchMaps['destination_batches'],
                'received_at' => now(),
                'received_by_user_id' => $userId,
            ]);

            $receivedLines[] = $line->fresh();
        }

        $distribution->syncStatusFromLines();

        return $receivedLines;
    }

    /**
     * @return list<StockDistributionProduct>
     */
    public function receiveAllPendingLines(StockDistribution $distribution, int $userId): array
    {
        $lineIds = $distribution->products()
            ->whereNull('received_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->receiveLines($distribution, $lineIds, $userId);
    }

    public function rollbackDistribution(StockDistribution $distribution): void
    {
        $distribution->loadMissing('products');

        foreach ($distribution->products as $line) {
            $this->rollbackLine($line, (int) $distribution->to_branch_id, $line->isReceived());
        }
    }

    public function rollbackLine(StockDistributionProduct $line, int $toBranchId, bool $wasReceived): void
    {
        $qty = (float) $line->quantity;

        if ($wasReceived && ($line->destination_batches ?? []) !== []) {
            $this->stock->deductFromBatchMap(
                $line->destination_batches,
                fn (Batch $batch, float $deductQty) => $batch->distributionOutStock($deductQty)
            );
        }

        if (($line->source_batches ?? []) !== []) {
            $this->stock->restoreFromBatchMap(
                $line->source_batches,
                fn (Batch $batch, float $batchQty) => $batch->distributionInStock($batchQty)
            );

            return;
        }

        if ($line->variation_id) {
            $destinationProductId = (int) $line->product_id;

            if ($wasReceived) {
                $destinationProduct = $this->resolveDestinationProduct((int) $line->product_id, $toBranchId);
                $destinationProductId = $destinationProduct->id;
            }

            $this->rollbackVariation(
                (int) $line->variation_id,
                $toBranchId,
                (int) $line->product_id,
                $destinationProductId,
                $qty,
                $wasReceived,
            );
        }
    }

    /**
     * Step 1 of a two-step branch→main return: the goods physically leave the branch
     * (in transit to the main warehouse). Only received lines hold branch stock.
     * Throws if the branch has already sold/used the received stock.
     */
    public function withdrawReceivedLineFromBranch(StockDistributionProduct $line, int $toBranchId): void
    {
        if (! $line->isReceived()) {
            return;
        }

        if (($line->destination_batches ?? []) !== []) {
            $this->stock->deductFromBatchMap(
                $line->destination_batches,
                fn (Batch $batch, float $qty) => $batch->distributionOutStock($qty)
            );

            return;
        }

        if ($line->variation_id) {
            $destinationProduct = $this->resolveDestinationProduct((int) $line->product_id, $toBranchId);
            $sourceVariation = ProductVariation::query()->whereKey((int) $line->variation_id)->firstOrFail();

            $destinationVariation = ProductVariation::query()
                ->where('product_id', $destinationProduct->id)
                ->where('branch_id', $toBranchId)
                ->where('sku', $sourceVariation->sku)
                ->lockForUpdate()
                ->first();

            if (! $destinationVariation) {
                throw new \RuntimeException('Destination variation not found.');
            }

            if ((float) $destinationVariation->stock < (float) $line->quantity) {
                throw new \RuntimeException('Cannot return because branch stock has already been used.');
            }

            $destinationVariation->decrement('stock', (float) $line->quantity);
        }
    }

    /**
     * Step 2 of a two-step branch→main return: the returned goods arrive back at the
     * main warehouse when the admin receives them. Mirrors the original source dispatch.
     */
    public function restoreReturnedLineToMain(StockDistributionProduct $line): void
    {
        if (($line->source_batches ?? []) !== []) {
            $this->stock->restoreFromBatchMap(
                $line->source_batches,
                fn (Batch $batch, float $qty) => $batch->distributionInStock($qty)
            );

            return;
        }

        if ($line->variation_id) {
            ProductVariation::whereKey((int) $line->variation_id)->increment('stock', (float) $line->quantity);
        }
    }

    /**
     * @return array{source_batches: array<int|string, float>, destination_batches: array<int|string, float>}
     */
    private function dispatchVariation(
        int $fromBranchId,
        int $toBranchId,
        int $sourceProductId,
        int $variationId,
        float $qty,
    ): array {
        $sourceVariation = ProductVariation::query()
            ->whereKey($variationId)
            ->where('product_id', $sourceProductId)
            ->where('branch_id', $fromBranchId)
            ->lockForUpdate()
            ->first();

        if (! $sourceVariation) {
            throw new \RuntimeException('Variation stock not found at main branch.');
        }

        if ((float) $sourceVariation->stock < $qty) {
            throw new \RuntimeException('Insufficient variation stock at main branch.');
        }

        $sourceVariation->decrement('stock', $qty);

        return [
            'source_batches' => [],
            'destination_batches' => [],
        ];
    }

    /**
     * @return array<int|string, float>
     */
    private function receiveVariation(
        int $toBranchId,
        int $sourceProductId,
        int $destinationProductId,
        int $variationId,
        float $qty,
    ): array {
        $fromBranchId = Branch::resolveMainBranchId();
        $sourceVariation = ProductVariation::query()
            ->whereKey($variationId)
            ->where('product_id', $sourceProductId)
            ->where('branch_id', $fromBranchId)
            ->first();

        if (! $sourceVariation) {
            throw new \RuntimeException('Source variation not found.');
        }

        $destinationVariation = ProductVariation::query()->firstOrCreate(
            [
                'product_id' => $destinationProductId,
                'branch_id' => $toBranchId,
                'sku' => $sourceVariation->sku,
            ],
            [
                'sku_code' => $sourceVariation->sku_code,
                'variation_data' => $sourceVariation->variation_data,
                'price' => $sourceVariation->price,
                'purchase_price' => $sourceVariation->purchase_price,
                'stock' => 0,
                'status' => $sourceVariation->status,
            ]
        );

        $destinationVariation->increment('stock', $qty);

        return [];
    }

    private function rollbackVariation(
        int $sourceVariationId,
        int $toBranchId,
        int $sourceProductId,
        int $destinationProductId,
        float $qty,
        bool $wasReceived,
    ): void {
        $sourceVariation = ProductVariation::query()->whereKey($sourceVariationId)->first();

        if (! $sourceVariation) {
            throw new \RuntimeException('Source variation not found.');
        }

        if ($wasReceived) {
            $destinationVariation = ProductVariation::query()
                ->where('product_id', $destinationProductId)
                ->where('branch_id', $toBranchId)
                ->where('sku', $sourceVariation->sku)
                ->lockForUpdate()
                ->first();

            if (! $destinationVariation) {
                throw new \RuntimeException('Destination variation not found.');
            }

            if ((float) $destinationVariation->stock < $qty) {
                throw new \RuntimeException('Cannot reverse distribution because branch stock has already been used.');
            }

            $destinationVariation->decrement('stock', $qty);
        }

        ProductVariation::whereKey($sourceVariationId)->increment('stock', $qty);
    }

    private function resolveDestinationProduct(int $sourceProductId, int $toBranchId): Product
    {
        $sourceProduct = Product::query()->findOrFail($sourceProductId);

        if ((int) $sourceProduct->branch_id === $toBranchId) {
            return $sourceProduct;
        }

        $destinationProduct = $sourceProduct->siblingForBranch($toBranchId);

        if ($destinationProduct !== null) {
            return $destinationProduct;
        }

        return $this->autoReplicateToDestination($sourceProduct, $toBranchId);
    }

    private function autoReplicateToDestination(Product $sourceProduct, int $toBranchId): Product
    {
        $sourceProduct->loadMissing(['photos', 'variations']);

        $data = [
            'name' => $sourceProduct->name,
            'slug' => $sourceProduct->slug,
            'code' => $sourceProduct->code,
            'category_id' => $sourceProduct->category_id,
            'brand_id' => $sourceProduct->brand_id,
            'unit_id' => $sourceProduct->unit_id,
            'warranty_id' => $sourceProduct->warranty_id,
            'colors' => $sourceProduct->colors ?? [],
            'sizes' => $sourceProduct->sizes ?? [],
            'purchase_price' => (float) $sourceProduct->purchase_price,
            'sale_price' => (float) $sourceProduct->sale_price,
            'discount_price' => (float) ($sourceProduct->discount_price ?? 0),
            'tags' => $sourceProduct->tags ?? [],
            'image' => $sourceProduct->image,
            'youtube_link' => $sourceProduct->youtube_link,
            'description' => $sourceProduct->description,
            'delivery_info' => $sourceProduct->delivery_info,
            'visible' => $sourceProduct->visible ?? 'no',
            'status' => $sourceProduct->status ?? 1,
        ];

        // Build combinations with stock = 0 so receiveVariation handles stock via its own increment.
        $combinations = $sourceProduct->variations->map(fn (ProductVariation $variation): array => [
            'variant' => $variation->variation_data['label'] ?? '',
            'variation_data' => $variation->variation_data,
            'sale_price' => $variation->price,
            'purchase_price' => $variation->purchase_price,
            'sku' => $variation->sku,
            'stock' => 0,
        ])->all();

        return $this->replication->copyToBranch(
            $sourceProduct,
            $toBranchId,
            $data,
            $combinations,
            (float) $sourceProduct->purchase_price,
            (float) $sourceProduct->sale_price,
            0,
        );
    }
}
