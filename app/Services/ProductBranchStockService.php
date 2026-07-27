<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Builder;

class ProductBranchStockService
{
    public function currentStock(int $productId, ?int $branchId): float
    {
        $variationQuery = ProductVariation::query()->where('product_id', $productId);

        if ($branchId !== null) {
            $variationQuery->where('branch_id', $branchId);
        }

        if ($variationQuery->exists()) {
            return (float) $variationQuery->sum('stock');
        }

        $query = Batch::query()->where('product_id', $productId);

        if ($branchId === null) {
            return (float) $query->sum('available');
        }

        return (float) $query->atBranchWarehouse($branchId)->sum('available');
    }

    public function scopeLogsForWarehouse(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $outer) use ($branchId) {
            $outer->where('branch_id', $branchId)
                ->orWhereHas('batch', fn (Builder $batchQuery) => $batchQuery->atBranchWarehouse($branchId))
                ->orWhere(function (Builder $variationLogQuery) use ($branchId) {
                    $variationLogQuery
                        ->whereNull('batch_id')
                        ->whereHas('product.variations', fn (Builder $variationQuery) => $variationQuery->where('branch_id', $branchId));
                });
        });
    }
}
