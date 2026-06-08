<?php

namespace App\Http\Middleware;

use App\Services\EcommerceBranchService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEcommercePanel
{
    public function __construct(private EcommerceBranchService $ecommerceBranch) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isBranchUser() && $this->ecommerceBranch->isEcommerceBranch($user->branch_id)) {
            return $next($request);
        }

        return $user?->isBranchUser()
            ? redirect()->route('branch-panel.dashboard')
            : redirect()->route('dashboard');
    }
}
