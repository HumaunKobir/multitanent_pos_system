<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Size;
use App\Services\StockAgingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class StockAgingController extends Controller
{
    public const PERMISSION_VIEW = 'report.stock-aging.view';

    public function __construct(private StockAgingService $aging) {}

    public function __invoke(Request $request): Response
    {
        $this->authorize(self::PERMISSION_VIEW);

        $user = Auth::user();
        $canFilterByBranch = $user?->branch_id === null && $user?->usesAdminPanel();
        $mainBranchId = Branch::resolveMainBranchId();

        $report = $this->aging->report($request);

        return Inertia::render('admin/reports/stock-aging', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
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
}
