<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEcommercePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->canAccessEcommercePanel()) {
            return $next($request);
        }

        return $user?->usesBranchPanel()
            ? redirect()->route('branch-panel.dashboard')
            : redirect()->route('dashboard');
    }
}
