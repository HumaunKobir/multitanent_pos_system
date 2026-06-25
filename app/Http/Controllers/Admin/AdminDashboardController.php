<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DashboardSalesPeriod;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(public DashboardService $dashboard) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user?->can('dashboard.view')) {
            return redirect($user->defaultLandingUrl());
        }

        $period = DashboardSalesPeriod::tryFromInput($request->input('period'));
        $overview = $this->dashboard->adminOverview();

        return Inertia::render('admin/dashboard', [
            'today' => $overview['today'],
            'kpis' => $overview['kpis'],
            'branchSales' => $overview['branch_sales'],
            'salesTrend' => $overview['sales_trend'],
            'collection' => $overview['collection'],
            'sellReport' => $this->dashboard->sellReport(
                $period,
                null,
                $request->input('date_from'),
                $request->input('date_to'),
            ),
        ]);
    }
}
