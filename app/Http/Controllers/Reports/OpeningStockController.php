<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductInitialStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OpeningStockController extends Controller
{
    public const PERMISSION_VIEW = 'report.opening-stock.view';

    public function __invoke(Request $request): Response
    {
        $this->authorize(self::PERMISSION_VIEW);

        $user = Auth::user();
        $canFilterByBranch = $user?->branch_id === null && $user?->usesAdminPanel();
        $mainBranchId = Branch::resolveMainBranchId();
        $branchId = $this->resolveBranchId($request, $canFilterByBranch, $user?->branch_id);

        $query = ProductInitialStock::query()
            ->with([
                'product:id,name,code,brand_id,category_id,branch_id,initial_stock_supplier_id,initial_stock_paid_amount',
                'product.brand:id,name',
                'product.category:id,name',
                'product.initialStockSupplier:id,name',
                'variation:id,sku,variation_data',
                'branch:id,name',
            ])
            ->where('quantity', '>', 0)
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $search = (string) $request->input('search');
                $q->whereHas('product', function (Builder $productQuery) use ($search): void {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function (Builder $q) use ($request): void {
                $q->whereHas('product', fn (Builder $productQuery) => $productQuery->where('category_id', $request->integer('category_id')));
            })
            ->when($request->filled('brand_id'), function (Builder $q) use ($request): void {
                $q->whereHas('product', fn (Builder $productQuery) => $productQuery->where('brand_id', $request->integer('brand_id')));
            })
            ->latest('id');

        $summaryQuery = clone $query;
        $summaryRows = (clone $summaryQuery)->get(['quantity', 'unit_cost']);
        $totalQty = round((float) $summaryRows->sum('quantity'), 2);
        $totalValue = round((float) $summaryRows->sum(
            fn (ProductInitialStock $row): float => (float) $row->quantity * (float) $row->unit_cost,
        ), 2);

        $rows = $query
            ->paginate(50)
            ->withQueryString()
            ->through(function (ProductInitialStock $row): array {
                $qty = (float) $row->quantity;
                $unitCost = (float) $row->unit_cost;
                $variationLabel = null;

                if (is_array($row->variation?->variation_data) && filled($row->variation->variation_data['label'] ?? null)) {
                    $variationLabel = (string) $row->variation->variation_data['label'];
                } elseif ($row->variation?->sku) {
                    $variationLabel = (string) $row->variation->sku;
                }

                return [
                    'id' => $row->id,
                    'product' => $row->product?->name ?? '—',
                    'code' => $row->product?->code ?? '—',
                    'variation' => $variationLabel,
                    'category' => $row->product?->category?->name,
                    'brand' => $row->product?->brand?->name,
                    'branch' => $row->branch?->name ?? '—',
                    'supplier' => $row->product?->initialStockSupplier?->name,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'stock_value' => round($qty * $unitCost, 2),
                    'paid_amount' => round((float) ($row->product?->initial_stock_paid_amount ?? 0), 2),
                    'product_id' => $row->product_id,
                ];
            });

        return Inertia::render('admin/reports/opening-stock', [
            'rows' => $rows,
            'summary' => [
                'line_count' => $summaryRows->count(),
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
            ],
            'mainBranchId' => $mainBranchId,
            'isBranchScoped' => ! $canFilterByBranch,
            'filters' => array_merge(
                $request->only('search', 'category_id', 'brand_id'),
                $canFilterByBranch ? [
                    'branch_id' => $request->input('branch_id', (string) $mainBranchId),
                ] : [],
            ),
            'branches' => $canFilterByBranch
                ? Branch::query()->active()->orderBy('name')->pluck('name', 'id')
                : [],
            'categories' => Category::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
            'brands' => Brand::forCatalogPanel()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    private function resolveBranchId(Request $request, bool $canFilterByBranch, ?int $userBranchId): ?int
    {
        if (! $canFilterByBranch) {
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
}
