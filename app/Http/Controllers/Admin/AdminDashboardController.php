<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(public DashboardService $dashboard) {}

    public function __invoke(): Response
    {
        $overview = $this->dashboard->adminOverview();

        return Inertia::render('admin/dashboard', [
            'today' => $overview['today'],
            'kpis' => $overview['kpis'],
            'branchSales' => $overview['branch_sales'],
            'salesTrend' => $overview['sales_trend'],
            'collection' => $overview['collection'],
        ]);
    }
}
