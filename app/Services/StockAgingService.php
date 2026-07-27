<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ScopesProductStockListing;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockAgingService
{
    use ScopesProductStockListing;

    public const BUCKET_0_30 = '0-30';

    public const BUCKET_31_60 = '31-60';

    public const BUCKET_61_90 = '61-90';

    public const BUCKET_90_PLUS = '90+';

    /**
     * @return array{
     *     summary: array{0-30: float, 31-60: float, 61-90: float, 90+: float, total: float},
     *     rows: list<array{
     *         id: int,
     *         name: string,
     *         code: ?string,
     *         category: ?string,
     *         brand: ?string,
     *         qty_0_30: float,
     *         qty_31_60: float,
     *         qty_61_90: float,
     *         qty_90_plus: float,
     *         total_qty: float
     *     }>
     * }
     */
    public function report(Request $request): array
    {
        $listBranchId = $this->resolveProductListBranchId($request);
        $usesAdminPanel = Auth::user()?->usesAdminPanel() ?? false;
        $asOf = Carbon::now()->startOfDay();

        $summary = [
            self::BUCKET_0_30 => 0.0,
            self::BUCKET_31_60 => 0.0,
            self::BUCKET_61_90 => 0.0,
            self::BUCKET_90_PLUS => 0.0,
            'total' => 0.0,
        ];
        $rows = [];

        $this->buildQuery($request, $listBranchId, $usesAdminPanel)
            ->with([
                'category:id,name',
                'brand:id,name',
                'variations' => fn ($q) => $this->scopeProductListVariations($q, $listBranchId),
                'batches' => function ($q) use ($listBranchId): void {
                    $q->select([
                        'id',
                        'product_id',
                        'available',
                        'purchase_price',
                        'branch_id',
                        'created_at',
                        'updated_at',
                    ]);
                    $this->scopeProductListBatchStock($q, $listBranchId);
                    $q->where('available', '>', 0);
                },
            ])
            ->orderBy('name')
            ->chunkById(100, function ($products) use (&$summary, &$rows, $asOf): void {
                foreach ($products as $product) {
                    $buckets = $this->ageProductStock($product, $asOf);
                    $total = array_sum($buckets);

                    if ($total <= 0) {
                        continue;
                    }

                    foreach ($buckets as $key => $qty) {
                        $summary[$key] = round($summary[$key] + $qty, 2);
                    }
                    $summary['total'] = round($summary['total'] + $total, 2);

                    $rows[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'code' => $product->code,
                        'category' => $product->category?->name,
                        'brand' => $product->brand?->name,
                        'qty_0_30' => round($buckets[self::BUCKET_0_30], 2),
                        'qty_31_60' => round($buckets[self::BUCKET_31_60], 2),
                        'qty_61_90' => round($buckets[self::BUCKET_61_90], 2),
                        'qty_90_plus' => round($buckets[self::BUCKET_90_PLUS], 2),
                        'total_qty' => round($total, 2),
                    ];
                }
            });

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{0-30: float, 31-60: float, 61-90: float, 90+: float}
     */
    protected function ageProductStock(Product $product, Carbon $asOf): array
    {
        $buckets = [
            self::BUCKET_0_30 => 0.0,
            self::BUCKET_31_60 => 0.0,
            self::BUCKET_61_90 => 0.0,
            self::BUCKET_90_PLUS => 0.0,
        ];

        $variations = $product->relationLoaded('variations')
            ? $product->variations->filter(fn ($variation): bool => (float) $variation->stock > 0)
            : collect();

        if ($variations->isNotEmpty()) {
            foreach ($variations as $variation) {
                $ageDate = $variation->updated_at
                    ?? $variation->created_at
                    ?? $product->updated_at
                    ?? $product->created_at;
                $this->addToBucket($buckets, $ageDate, $asOf, (float) $variation->stock);
            }

            return $buckets;
        }

        $batches = $product->relationLoaded('batches')
            ? $product->batches->filter(fn ($batch): bool => (float) $batch->available > 0)
            : collect();

        foreach ($batches as $batch) {
            $ageDate = $batch->created_at ?? $batch->updated_at;
            $this->addToBucket($buckets, $ageDate, $asOf, (float) $batch->available);
        }

        return $buckets;
    }

    /**
     * @param  array{0-30: float, 31-60: float, 61-90: float, 90+: float}  $buckets
     */
    protected function addToBucket(array &$buckets, mixed $ageDate, Carbon $asOf, float $qty): void
    {
        if ($qty <= 0 || $ageDate === null) {
            return;
        }

        $days = Carbon::parse($ageDate)->startOfDay()->diffInDays($asOf);
        $key = $this->bucketForDays((int) $days);
        $buckets[$key] += $qty;
    }

    protected function bucketForDays(int $days): string
    {
        if ($days <= 30) {
            return self::BUCKET_0_30;
        }

        if ($days <= 60) {
            return self::BUCKET_31_60;
        }

        if ($days <= 90) {
            return self::BUCKET_61_90;
        }

        return self::BUCKET_90_PLUS;
    }

    protected function buildQuery(Request $request, ?int $listBranchId, bool $usesAdminPanel): Builder
    {
        $query = Product::query()
            ->active()
            ->when($listBranchId !== null, fn ($q) => $q->where('branch_id', $listBranchId))
            ->when($usesAdminPanel, fn ($q) => $q->visibleInMainCatalog())
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->brand_id, fn ($q, $b) => $q->where('brand_id', $b));

        $this->applyProductSizeFilter($query, $request->input('size_id'));

        return $query;
    }
}
