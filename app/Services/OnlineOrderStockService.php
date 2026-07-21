<?php

namespace App\Services;

use App\Enums\ProductLogType;
use App\Models\Batch;
use App\Models\OnlineOrder;
use RuntimeException;

class OnlineOrderStockService
{
    public function __construct(private InventoryStockService $stock) {}

    public function deductForOrder(OnlineOrder $order, ?int $branchId = null): float
    {
        $order->loadMissing('products');

        $totalCost = 0.0;

        foreach ($order->products as $line) {
            $quantity = (float) $line->quantity;

            if ($quantity <= 0) {
                continue;
            }

            if ($line->variation_id) {
                $this->stock->deductVariation((int) $line->variation_id, $quantity, ProductLogType::Sale);
                $lineCost = app(InventoryCostService::class)->costFromVariation((int) $line->variation_id, $quantity);
                $line->update(['batches' => []]);
            } else {
                $batchMap = $this->deductBatchStock($branchId, (int) $line->product_id, $quantity);
                $line->update(['batches' => $batchMap]);
                $lineCost = app(InventoryCostService::class)->costFromBatchMap($batchMap);
            }

            $totalCost += $lineCost;
        }

        return round($totalCost, 2);
    }

    /** @return array<int|string, float> */
    private function deductBatchStock(?int $branchId, int $productId, float $quantity): array
    {
        $batches = Batch::query()
            ->where('product_id', $productId)
            ->where('available', '>', 0)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->oldest()
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
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
            throw new RuntimeException('Insufficient stock for one or more products.');
        }

        foreach ($batchMap as $batchId => $deductQty) {
            $batch = $batches->firstWhere('id', $batchId);
            $batch->decrement('available', $deductQty);
            $batch->refresh();
            $batch->outStock($deductQty);
        }

        return $batchMap;
    }
}
