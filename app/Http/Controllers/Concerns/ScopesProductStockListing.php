<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ScopesProductStockListing
{
    protected function resolveProductListBranchId(Request $request): ?int
    {
        $userBranchId = Auth::user()?->branch_id;

        if ($userBranchId !== null) {
            return $userBranchId;
        }

        $filter = $request->input('branch_id');

        if ($filter === 'all') {
            return null;
        }

        if ($filter !== null && $filter !== '') {
            return (int) $filter;
        }

        return Branch::resolveMainBranchId();
    }

    protected function applyProductListStockAggregates(Builder $query, ?int $listBranchId): void
    {
        $query
            ->withSum([
                'variations as variations_sum_stock' => fn ($q) => $this->scopeProductListVariationStock($q, $listBranchId),
            ], 'stock')
            ->withSum([
                'batches as batches_sum_available' => fn ($q) => $this->scopeProductListBatchStock($q, $listBranchId),
            ], 'available');
    }

    protected function scopeProductListVariationStock($query, ?int $listBranchId): void
    {
        if ($listBranchId !== null) {
            $query->where('branch_id', $listBranchId);

            return;
        }

        $mainBranchId = Branch::resolveMainBranchId();

        $query->where(function (Builder $query) use ($mainBranchId) {
            $query->whereColumn('product_variations.branch_id', 'products.branch_id')
                ->orWhere(function (Builder $query) use ($mainBranchId) {
                    $query->where('products.branch_id', $mainBranchId)
                        ->whereNull('product_variations.branch_id');
                });
        });
    }

    protected function scopeProductListVariations($query, ?int $listBranchId): void
    {
        $query->select([
            'id',
            'product_id',
            'sku',
            'variation_data',
            'price',
            'purchase_price',
            'stock',
            'branch_id',
        ]);

        if ($listBranchId !== null) {
            $query->where('branch_id', $listBranchId);
        }
    }

    protected function scopeProductListBatchStock($query, ?int $listBranchId): void
    {
        if ($listBranchId !== null) {
            $query->atBranchWarehouse($listBranchId);

            return;
        }

        $mainBranchId = Branch::resolveMainBranchId();

        $query->where(function (Builder $query) use ($mainBranchId) {
            $query->whereColumn('batches.branch_id', 'products.branch_id')
                ->orWhere(function (Builder $query) use ($mainBranchId) {
                    $query->where('products.branch_id', $mainBranchId)
                        ->whereNull('batches.branch_id');
                });
        });
    }

    protected function resolveProductStockQuantity(Product $product): float
    {
        if ($product->relationLoaded('variations') && $product->variations->isNotEmpty()) {
            return (float) $product->variations->sum(
                fn ($variation): float => (float) $variation->stock,
            );
        }

        return (float) ($product->batches_sum_available ?? 0);
    }

    /**
     * @return array{total_stock: float, product_count: int, in_stock_count: int, out_of_stock_count: int}
     */
    protected function inventoryStockSummary(Builder $query, ?int $listBranchId): array
    {
        $totalStock = 0.0;
        $productCount = 0;
        $inStockCount = 0;

        (clone $query)
            ->select(['products.id'])
            ->with([
                'variations' => fn ($q) => $this->scopeProductListVariations($q, $listBranchId),
            ])
            ->tap(fn ($q) => $this->applyProductListStockAggregates($q, $listBranchId))
            ->chunkById(200, function ($products) use (&$totalStock, &$productCount, &$inStockCount): void {
                foreach ($products as $product) {
                    $productCount++;
                    $stock = $this->resolveProductStockQuantity($product);
                    $totalStock += $stock;

                    if ($stock > 0) {
                        $inStockCount++;
                    }
                }
            });

        return [
            'total_stock' => round($totalStock, 2),
            'product_count' => $productCount,
            'in_stock_count' => $inStockCount,
            'out_of_stock_count' => $productCount - $inStockCount,
        ];
    }
}
