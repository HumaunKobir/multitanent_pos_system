<?php

namespace App\Services;

use App\Enums\ProductLogType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\ProductInOutLog;
use App\Models\ProductVariation;
use Closure;
use Illuminate\Support\Collection;

class InventoryStockService
{
    /**
     * @return array<int|string, float>
     */
    public function deductFifo(?int $branchId, int $productId, float $qty, Closure $logCallback): array
    {
        $batches = Batch::where('product_id', $productId)
            ->where('available', '>', 0)
            ->when($branchId !== null, fn ($q) => $q->atBranchWarehouse($branchId))
            ->oldest()
            ->lockForUpdate()
            ->get();

        $remaining = $qty;
        $batchMap = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min((float) $batch->available, $remaining);
            $batchMap[$batch->id] = $deduct;
            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new \RuntimeException('Insufficient stock for one or more products.');
        }

        $this->applyBatchDeductions($batchMap, $batches, $logCallback);

        return $batchMap;
    }

    /**
     * @param  array<int|string, float>  $batchMap
     */
    public function deductFromBatchMap(array $batchMap, Closure $logCallback): void
    {
        if ($batchMap === []) {
            return;
        }

        $batches = Batch::whereIn('id', array_keys($batchMap))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $this->applyBatchDeductions($batchMap, $batches, $logCallback);
    }

    /**
     * @param  array<int|string, float>  $batchMap
     */
    public function restoreFromBatchMap(array $batchMap, Closure $logCallback): void
    {
        foreach ($batchMap as $batchId => $quantity) {
            $qty = (float) $quantity;
            $batch = Batch::whereKey($batchId)->lockForUpdate()->first();

            if (! $batch) {
                throw new \RuntimeException('Batch not found.');
            }

            $batch->increment('available', $qty);
            $batch->refresh();
            $logCallback($batch, $qty);
        }
    }

    public function deductVariation(int $variationId, float $qty, ProductLogType $type): void
    {
        $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();

        if ((float) $variation->stock < $qty) {
            throw new \RuntimeException('Insufficient stock for variation.');
        }

        $variation->decrement('stock', $qty);
        $variation->refresh();
        $this->logVariationMovement($variation, $qty, $type);
    }

    public function restoreVariation(int $variationId, float $qty, ProductLogType $type): void
    {
        $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();
        $variation->increment('stock', $qty);
        $variation->refresh();
        $this->logVariationMovement($variation, $qty, $type);
    }

    /**
     * Record a stock ledger row for variant products (batch available is not changed).
     */
    public function logVariationMovement(ProductVariation $variation, float $qty, ProductLogType $type): void
    {
        if ($qty == 0.0) {
            return;
        }

        $variation->loadMissing('product:id,name,branch_id');
        $product = $variation->product;

        if ($product === null) {
            $product = Product::query()->findOrFail($variation->product_id);
        }

        $branchId = $product->resolveStockBranchId($variation->branch_id ?? $product->branch_id);
        $batch = Batch::query()->firstOrCreate(
            [
                'product_id' => $product->id,
                'branch_id' => $branchId,
            ],
            [
                'purchase_price' => $variation->purchase_price,
                'available' => 0,
            ],
        );

        $label = $this->variationLabel($variation);

        ProductInOutLog::create([
            'batch_id' => $batch->id,
            'branch_id' => $variation->branch_id ?? $product->branch_id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'type' => $type->value,
            'stock' => (int) ProductVariation::query()
                ->where('product_id', $product->id)
                ->where('branch_id', $variation->branch_id)
                ->sum('stock'),
            'remark' => $type->name.($label !== '' ? ' — '.$label : ''),
        ]);
    }

    private function variationLabel(ProductVariation $variation): string
    {
        $data = $variation->variation_data;

        if (is_array($data) && filled($data['label'] ?? null)) {
            return (string) $data['label'];
        }

        return (string) ($variation->sku ?? '');
    }

    /**
     * @param  array<int|string, float>  $batchMap
     * @param  Collection<int, Batch>|\Illuminate\Database\Eloquent\Collection<int, Batch>  $batches
     */
    private function applyBatchDeductions(array $batchMap, $batches, Closure $logCallback): void
    {
        foreach ($batchMap as $batchId => $deductQty) {
            $batch = $batches instanceof Collection
                ? $batches->firstWhere('id', (int) $batchId)
                : ($batches[(int) $batchId] ?? null);

            if (! $batch) {
                $batch = Batch::whereKey($batchId)->lockForUpdate()->first();
            }

            if (! $batch) {
                throw new \RuntimeException('Batch not found.');
            }

            if ((float) $batch->available < (float) $deductQty) {
                throw new \RuntimeException('Insufficient stock in batch.');
            }

            $batch->decrement('available', $deductQty);
            $batch->refresh();
            $logCallback($batch, (float) $deductQty);
        }
    }
}
