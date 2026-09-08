<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchSubscription
{
    public function __construct(private BranchSubscriptionService $subscriptionService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! ($user instanceof User)) {
            return $next($request);
        }

        // Superadmins and Central Admin / Main Branch users are always unrestricted
        if ($user->usesAdminPanel() || $user->bypassesPermissionChecks()) {
            return $next($request);
        }

        $branch = $user->branch;

        if ($branch === null || Branch::isMainBranch($branch->id)) {
            return $next($request);
        }

        $summary = $this->subscriptionService->getSubscriptionSummary($branch);

        // If completely suspended or locked
        if ($summary['is_suspended']) {
            if ($request->routeIs('branch-panel.subscription-suspended') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('branch-panel.subscription-suspended');
        }

        // If already on suspended page but subscription is actually active, redirect to dashboard
        if ($request->routeIs('branch-panel.subscription-suspended') && ! $summary['is_suspended']) {
            return redirect()->route('branch-panel.dashboard');
        }

        // Sales restriction enforcement
        if (! empty($summary['is_sales_restricted'])) {
            // Block navigating to POS / Sell Create screen
            if ($request->routeIs('inventory.sell.create') || $request->is('inventory/sell/create')) {
                return redirect()->route('branch-panel.dashboard')->with('error', 'POS & Sales Checkout is currently disabled due to an overdue subscription payment. Please settle your renewal fee to restore sales access.');
            }

            // Block mutating sales routes
            if (! $request->isMethodSafe()) {
                if ($request->is('inventory/sell*') || $request->is('inventory/sale-return*') || $request->is('inventory/product-exchange*') || $request->is('pos*')) {
                    if ($request->header('X-Inertia')) {
                        return back()->with('error', 'Sales & POS transactions are restricted due to overdue subscription payment. Please clear payment to restore full access.');
                    }

                    return response()->json([
                        'message' => 'Sales & POS transactions are restricted due to overdue subscription payment.',
                    ], 403);
                }
            }
        }

        // Read-only mode: block all mutating requests
        if (! empty($summary['is_read_only']) && ! $request->isMethodSafe()) {
            if (! $request->routeIs('logout')) {
                if ($request->header('X-Inertia')) {
                    return back()->with('error', 'Action blocked: This branch is in read-only mode due to overdue subscription payment.');
                }

                return response()->json([
                    'message' => 'Action blocked: This branch is in read-only mode due to overdue subscription payment.',
                ], 403);
            }
        }

        return $next($request);
    }
}
