<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\ScopesProductStockListing;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StockValuationController extends Controller
{
    use ScopesProductStockListing;

    public const PERMISSION_VIEW = 'report.stock-valuation.view';

    public function __invoke(Request $request): Response
    {
        $this->authorize(self::PERMISSION_VIEW);

        $listBranchId = $this->resolveProductListBranchId($request);
        $user = Auth::user();
        $canFilterByBranch = $user?->branch_id === null && $user?->usesAdminPanel();
        $usesAdminPanel = $user?->usesAdminPanel() ?? false;
        $mainBranchId = Branch::resolveMainBranchId();

        $query = $this->buildQuery($request, $listBranchId, $usesAdminPanel);
        $summary = $this->inventoryStockSummary($query, $listBranchId);

        $rows = (clone $query)
            ->with([
                'category:id,name',
                'brand:id,name',
                'variations' => fn ($q) => $this->scopeProductListVariations($q, $listBranchId),
                'batches' => function ($q) use ($listBranchId): void {
                    $q->select(['id', 'product_id', 'available', 'purchase_price', 'branch_id']);
                    $this->scopeProductListBatchStock($q, $listBranchId);
                },
            ])
            ->tap(fn ($q) => $this->applyProductListStockAggregates($q, $listBranchId))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString()
            ->through(function (Product $product): array {
                $valuation = $this->resolveProductStockValuation($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'category' => $product->category?->name,
                    'brand' => $product->brand?->name,
                    'qty' => $valuation['qty'],
                    'unit_cost' => $valuation['unit_cost'],
                    'cost_value' => $valuation['cost'],
                    'selling_value' => $valuation['selling'],
                    'profit' => $valuation['profit'],
                ];
            });

        return Inertia::render('admin/reports/stock-valuation', [
            'rows' => $rows,
            'summary' => [
                'product_count' => $summary['product_count'],
                'total_qty' => $summary['total_stock'],
                'total_cost_value' => $summary['total_cost_value'],
                'total_selling_value' => $summary['total_selling_value'],
                'expected_gross_profit' => $summary['expected_gross_profit'],
            ],
            'mainBranchId' => $mainBranchId,
            'filters' => array_merge(
                $request->only('search', 'category_id', 'brand_id', 'size_id'),
                $canFilterByBranch ? [
                    'branch_id' => $request->input('branch_id', (string) $mainBranchId),
                ] : [],
            ),
            'branches' => $canFilterByBranch ? Branch::active()->orderBy('name')->pluck('name', 'id') : [],
            'categories' => Category::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'sizes' => Size::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'isBranchScoped' => ! $canFilterByBranch,
        ]);
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
