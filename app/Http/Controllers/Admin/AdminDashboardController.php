<?php

namespace App\Http\Controllers\Admin;

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
            return Inertia::render('admin/dashboard', $this->dashboard->welcomeOverview($user));
        }

        $overview = $this->dashboard->saasOverview();

        return Inertia::render('admin/dashboard', [
            'today' => $overview['today'],
            'kpis' => $overview['kpis'],
            'statusBreakdown' => $overview['status_breakdown'],
            'recentPending' => $overview['recent_pending'],
            'links' => $overview['links'],
        ]);
    }
}
