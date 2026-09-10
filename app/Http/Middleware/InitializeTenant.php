<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Services\EcommerceBranchService;
use App\Services\TenantProvisioner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenant
{
    public function __construct(private TenantProvisioner $provisioner) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tenancy.enabled')) {
            return $next($request);
        }

        $branch = $this->resolveBranch($request);

        if ($branch !== null) {
            $this->provisioner->initialize($branch);
        } else {
            $this->provisioner->initializeCentral();
        }

        return $next($request);
    }

    private function resolveBranch(Request $request): ?Branch
    {
        if ($this->isStorefrontRequest($request)) {
            return Branch::query()->find(EcommerceBranchService::resolveIdStatic());
        }

        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $switchId = $request->session()->get('tenant_branch_id');

        if ($switchId && ($user->usesAdminPanel() || $user->isSuperAdmin())) {
            $switched = Branch::query()->find($switchId);

            if ($switched !== null && filled($switched->database_name)) {
                return $switched;
            }
        }

        if ($user->branch_id) {
            return Branch::query()->find($user->branch_id);
        }

        return Branch::query()->find(Branch::resolveMainBranchId());
    }

    private function isStorefrontRequest(Request $request): bool
    {
        if ($request->is('customer/*') || $request->routeIs('customer.*')) {
            return true;
        }

        $route = $request->route()?->getName();

        if ($route !== null && str_starts_with($route, 'store.')) {
            return true;
        }

        return $request->is('/')
            || $request->is('shop')
            || $request->is('shop/*')
            || $request->is('cart')
            || $request->is('cart/*')
            || $request->is('checkout')
            || $request->is('checkout/*')
            || $request->is('product/*')
            || $request->is('sslcommerz/*');
    }
}
