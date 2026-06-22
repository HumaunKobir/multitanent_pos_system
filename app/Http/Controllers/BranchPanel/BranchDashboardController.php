<?php

namespace App\Http\Controllers\BranchPanel;

use App\Enums\DashboardSalesPeriod;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\StorageUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BranchDashboardController extends Controller
{
    public function __construct(public DashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $user = Auth::user();

        if (! $user?->can('dashboard.view')) {
            return Inertia::render('branch-panel/dashboard', [
                'today' => now()->toDateString(),
                'branchName' => $user?->branch?->name ?? 'Branch',
                'branchLogoUrl' => StorageUrl::public($user?->branch?->logo),
                'sections' => null,
                'limitedAccess' => true,
            ]);
        }

        $period = DashboardSalesPeriod::tryFromInput($request->input('period'));
        $overview = $this->dashboard->branchOverview(
            $user,
            $period,
            $request->input('date_from'),
            $request->input('date_to'),
        );

        return Inertia::render('branch-panel/dashboard', [
            'today' => $overview['today'],
            'branchName' => $overview['branch_name'],
            'sections' => $overview['sections'],
            'limitedAccess' => false,
        ]);
    }
}
