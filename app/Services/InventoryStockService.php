<?php

namespace App\Services;

use App\Models\Batch;
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

    public function deductVariation(int $variationId, float $qty): void
    {
        $variation = ProductVariation::whereKey($variationId)->lockForUpdate()->firstOrFail();

        if ((float) $variation->stock < $qty) {
            throw new \RuntimeException('Insufficient stock for variation.');
        }

        $variation->decrement('stock', $qty);
    }

    public function restoreVariation(int $variationId, float $qty): void
    {
        ProductVariation::whereKey($variationId)->increment('stock', $qty);
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
