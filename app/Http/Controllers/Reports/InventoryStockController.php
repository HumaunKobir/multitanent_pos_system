<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Concerns\ScopesProductStockListing;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InventoryStockController extends Controller
{
    use ScopesProductStockListing;

    public function __invoke(Request $request): Response
    {
        $this->authorize('report.inventory-stock.view');

        $listBranchId = $this->resolveProductListBranchId($request);
        $user = Auth::user();
        $canFilterByBranch = $user?->branch_id === null && $user?->usesAdminPanel();
        $usesAdminPanel = $user?->usesAdminPanel() ?? false;
        $mainBranchId = Branch::resolveMainBranchId();

        $query = $this->buildInventoryStockQuery($request, $listBranchId, $usesAdminPanel);
        $summary = $this->inventoryStockSummary($query, $listBranchId);

        $products = (clone $query)
            ->with([
                'category:id,name',
                'brand:id,name',
                'variations' => fn ($q) => $this->scopeProductListVariations($q, $listBranchId),
            ])
            ->tap(fn ($q) => $this->applyProductListStockAggregates($q, $listBranchId))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Product $product): Product {
                if ($product->source_branch_id !== null) {
                    $product->setAttribute(
                        'submission_stock_summary',
                        $product->submissionBranchStockSummary(),
                    );
                }

                return $product;
            });

        return Inertia::render('admin/reports/inventory-stock', [
            'products' => $products,
            'summary' => $summary,
            'mainBranchId' => $mainBranchId,
            'filters' => array_merge(
                $request->only('search', 'category_id', 'brand_id'),
                $canFilterByBranch ? [
                    'branch_id' => $request->input('branch_id', (string) $mainBranchId),
                ] : [],
            ),
            'branches' => $canFilterByBranch ? Branch::active()->orderBy('name')->pluck('name', 'id') : [],
            'categories' => Category::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    protected function buildInventoryStockQuery(Request $request, ?int $listBranchId, bool $usesAdminPanel): Builder
    {
        return Product::query()
            ->active()
            ->when($listBranchId !== null, fn ($q) => $q->where('branch_id', $listBranchId))
            ->when($usesAdminPanel, fn ($q) => $q->visibleInMainCatalog())
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhereHas('brand', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->brand_id, fn ($q, $b) => $q->where('brand_id', $b));
    }
}
