<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockDistribution;
use App\Models\StockDistributionProduct;

class StockDistributionService
{
    public function __construct(private InventoryStockService $stock) {}

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
     * @return array{source_batches: array<int|string, float>, destination_batches: array<int|string, float>}
     */
    public function distributeLine(int $toBranchId, int $productId, ?int $variationId, float $qty): array
    {
        $fromBranchId = Branch::resolveMainBranchId();
        $destinationProduct = $this->resolveDestinationProduct($productId, $toBranchId);

        if ($variationId) {
            return $this->distributeVariation(
                $fromBranchId,
                $toBranchId,
                $productId,
                $destinationProduct->id,
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

        $sourceBatches = Batch::whereIn('id', array_keys($sourceBatchMap))
            ->get()
            ->keyBy('id');

        $destinationBatchMap = [];

        foreach ($sourceBatchMap as $sourceBatchId => $deductQty) {
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

        return [
            'source_batches' => $sourceBatchMap,
            'destination_batches' => $destinationBatchMap,
        ];
    }

    public function rollbackDistribution(StockDistribution $distribution): void
    {
        $distribution->loadMissing('products');

        foreach ($distribution->products as $line) {
            $this->rollbackLine($line, (int) $distribution->to_branch_id);
        }
    }

    public function rollbackLine(StockDistributionProduct $line, int $toBranchId): void
    {
        $qty = (float) $line->quantity;

        if (($line->destination_batches ?? []) !== []) {
            $this->stock->deductFromBatchMap(
                $line->destination_batches,
                fn (Batch $batch, float $deductQty) => $batch->distributionOutStock($deductQty)
            );

            $this->stock->restoreFromBatchMap(
                $line->source_batches ?? [],
                fn (Batch $batch, float $batchQty) => $batch->distributionInStock($batchQty)
            );

            return;
        }

        if ($line->variation_id) {
            $destinationProduct = $this->resolveDestinationProduct((int) $line->product_id, $toBranchId);
            $this->rollbackVariation(
                (int) $line->variation_id,
                $toBranchId,
                (int) $line->product_id,
                $destinationProduct->id,
                $qty,
            );
        }
    }

    /**
     * @return array{source_batches: array<int|string, float>, destination_batches: array<int|string, float>}
     */
    private function distributeVariation(
        int $fromBranchId,
        int $toBranchId,
        int $sourceProductId,
        int $destinationProductId,
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

        return [
            'source_batches' => [],
            'destination_batches' => [],
        ];
    }

    private function rollbackVariation(
        int $sourceVariationId,
        int $toBranchId,
        int $sourceProductId,
        int $destinationProductId,
        float $qty,
    ): void {
        $sourceVariation = ProductVariation::query()->whereKey($sourceVariationId)->first();

        if (! $sourceVariation) {
            throw new \RuntimeException('Source variation not found.');
        }

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

        if ($sourceProduct->product_group_id === null) {
            return $sourceProduct;
        }

        throw new \RuntimeException('No product catalog entry exists for the destination branch.');
    }
}
