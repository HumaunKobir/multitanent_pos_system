<?php

namespace App\Http\Controllers\BranchPanel;

use App\Http\Controllers\Controller;
use App\Services\BranchSubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionSuspendedController extends Controller
{
    public function __construct(private BranchSubscriptionService $subscriptionService) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $branch = $user?->branch;

        $summary = $branch !== null
            ? $this->subscriptionService->getSubscriptionSummary($branch)
            : [];

        return Inertia::render('branch-panel/subscription-suspended', [
            'subscription' => $summary,
        ]);
    }
}
