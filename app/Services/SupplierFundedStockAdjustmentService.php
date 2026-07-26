<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentProduct;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class SupplierFundedStockAdjustmentService
{
    public function __construct(
        private InventoryCostService $inventoryCosts,
    ) {}

    public function branchQuery(?string $dateFrom = null, ?string $dateTo = null, ?int $branchId = null): Builder
    {
        return StockAdjustment::query()
            ->ownBranch()
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('date', '>=', Carbon::parse($dateFrom)->toDateString()))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('date', '<=', Carbon::parse($dateTo)->toDateString()))
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    /**
     * @param  iterable<StockAdjustment>  $adjustments
     * @return list<array{adjustment: StockAdjustment, supplier: Supplier, signed_amount: float}>
     */
    public function fundedEntries(iterable $adjustments, ?int $supplierId = null): array
    {
        $entries = [];

        foreach ($adjustments as $adjustment) {
            foreach ($this->fundedEntriesForAdjustment($adjustment, $supplierId) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{adjustment: StockAdjustment, supplier: Supplier, signed_amount: float}>
     */
    public function fundedEntriesForAdjustment(StockAdjustment $adjustment, ?int $supplierId = null): array
    {
        /** @var array<int, float> $amountsBySupplier */
        $amountsBySupplier = [];
        /** @var array<int, Supplier> $suppliersById */
        $suppliersById = [];

        foreach ($adjustment->products as $line) {
            $product = $line->product;

            if ($product === null) {
                continue;
            }

            $lineSupplier = $this->resolveSupplier($product, $line);

            if ($lineSupplier === null) {
                continue;
            }

            if ($supplierId !== null && $lineSupplier->id !== $supplierId) {
                continue;
            }

            $lineCost = $this->inventoryCosts->costForStockAdjustmentLine($line);

            if ($lineCost <= 0) {
                continue;
            }

            $signedAmount = $adjustment->isIncrease() ? $lineCost : -$lineCost;
            $amountsBySupplier[$lineSupplier->id] = ($amountsBySupplier[$lineSupplier->id] ?? 0) + $signedAmount;
            $suppliersById[$lineSupplier->id] = $lineSupplier;
        }

        $entries = [];

        foreach ($amountsBySupplier as $resolvedSupplierId => $signedAmount) {
            $entries[] = [
                'adjustment' => $adjustment,
                'supplier' => $suppliersById[$resolvedSupplierId],
                'signed_amount' => round($signedAmount, 2),
            ];
        }

        return $entries;
    }

    public function resolveSupplier(Product $product, StockAdjustmentProduct $line): ?Supplier
    {
        if ($product->initial_stock_supplier_id !== null) {
            return Supplier::query()->find($product->initial_stock_supplier_id);
        }

        $batchMap = is_array($line->batches) ? $line->batches : [];

        if ($batchMap === []) {
            return null;
        }

        $batch = Batch::query()->find(array_key_first($batchMap));

        if ($batch?->supplier_id === null) {
            return null;
        }

        return Supplier::query()->find($batch->supplier_id);
    }
}
