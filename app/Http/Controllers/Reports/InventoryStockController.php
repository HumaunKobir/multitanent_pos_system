<?php

namespace App\Http\Controllers\Reports;

use App\Concerns\ExportsFilteredList;
use App\Http\Controllers\Concerns\ScopesProductStockListing;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryStockController extends Controller
{
    use ExportsFilteredList;
    use ScopesProductStockListing;

    public const PERMISSION_VIEW = 'report.inventory-stock.view';

    public function __invoke(Request $request): Response
    {
        $this->authorize(self::PERMISSION_VIEW);

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
                'batches' => function ($q) use ($listBranchId): void {
                    $q->select(['id', 'product_id', 'available', 'purchase_price', 'branch_id']);
                    $this->scopeProductListBatchStock($q, $listBranchId);
                },
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

                $valuation = $this->resolveProductStockValuation($product);
                $product->setAttribute('stock_qty', $valuation['qty']);
                $product->setAttribute('cost_value', $valuation['cost']);
                $product->setAttribute('selling_value', $valuation['selling']);
                $product->setAttribute('expected_profit', $valuation['profit']);

                return $product;
            });

        return Inertia::render('admin/reports/inventory-stock', [
            'products' => $products,
            'summary' => $summary,
            'mainBranchId' => $mainBranchId,
            'filters' => array_merge(
                $request->only('search', 'category_id', 'brand_id', 'size_id', 'product_id', 'date_from', 'date_to'),
                $canFilterByBranch ? [
                    'branch_id' => $request->input('branch_id', (string) $mainBranchId),
                ] : [],
            ),
            'branches' => $canFilterByBranch ? Branch::active()->orderBy('name')->pluck('name', 'id') : [],
            'categories' => Category::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'sizes' => Size::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'productOptions' => Product::query()
                ->active()
                ->when($listBranchId !== null, fn ($q) => $q->where('branch_id', $listBranchId))
                ->when($usesAdminPanel, fn ($q) => $q->visibleInMainCatalog())
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'code'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'label' => $product->code
                        ? "{$product->name} ({$product->code})"
                        : $product->name,
                ])
                ->all(),
        ]);
    }

    protected function buildInventoryStockQuery(Request $request, ?int $listBranchId, bool $usesAdminPanel): Builder
    {
        $query = Product::query()
            ->active()
            ->when($listBranchId !== null, fn ($q) => $q->where('branch_id', $listBranchId))
            ->when($usesAdminPanel, fn ($q) => $q->visibleInMainCatalog())
            ->when($request->filled('product_id'), fn ($q) => $q->whereKey((int) $request->input('product_id')))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhereHas('brand', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            }))
            ->when($request->category_id, fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->brand_id, fn ($q, $b) => $q->where('brand_id', $b));

        $this->applyProductSizeFilter($query, $request->input('size_id'));
        $this->applyCreatedAtDateFilters($query, $request);

        return $query;
    }

    /**
     * @return Collection<int, list<string|int|float>>
     */
    private function exportRows(Request $request): Collection
    {
        $listBranchId = $this->resolveProductListBranchId($request);
        $usesAdminPanel = Auth::user()?->usesAdminPanel() ?? false;

        $products = $this->buildInventoryStockQuery($request, $listBranchId, $usesAdminPanel)
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
            ->limit(self::LIST_EXPORT_LIMIT)
            ->get();

        return $products->values()->map(function (Product $product, int $index): array {
            $valuation = $this->resolveProductStockValuation($product);

            return [
                $index + 1,
                $product->name,
                $product->code ?? '—',
                $product->category?->name ?? '—',
                $product->brand?->name ?? '—',
                $valuation['qty'],
                $valuation['qty'] > 0 ? 'In Stock' : 'Out of Stock',
                $valuation['cost'],
                $valuation['selling'],
                $valuation['profit'],
                optional($product->created_at)?->format('Y-m-d H:i') ?? '—',
            ];
        });
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->downloadListExcel(
            'inventory-stock',
            ['#', 'Name', 'Code', 'Category', 'Brand', 'Stock', 'Status', 'Cost Value', 'Selling Value', 'Expected Profit', 'Created At'],
            $this->exportRows($request),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->downloadListPdf(
            'Inventory Stock',
            ['#', 'Name', 'Code', 'Category', 'Brand', 'Stock', 'Status', 'Cost Value', 'Selling Value', 'Expected Profit', 'Created At'],
            $this->exportRows($request),
        );
    }

    public function exportPrint(Request $request): SymfonyResponse
    {
        $this->authorize(self::PERMISSION_VIEW);

        return $this->printListHtml(
            'Inventory Stock',
            ['#', 'Name', 'Code', 'Category', 'Brand', 'Stock', 'Status', 'Cost Value', 'Selling Value', 'Expected Profit', 'Created At'],
            $this->exportRows($request),
        );
    }
}
