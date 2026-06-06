<?php

namespace App\Http\Controllers\BranchPanel;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class BranchDashboardController extends Controller
{
    public function __construct(public DashboardService $dashboard) {}

    public function __invoke(): Response
    {
        $user = auth()->user();
        $overview = $this->dashboard->branchOverview($user);

        return Inertia::render('branch-panel/dashboard', [
            'today' => $overview['today'],
            'branchName' => $overview['branch_name'],
            'sections' => $overview['sections'],
        ]);
    }
}
