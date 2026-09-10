<?php

namespace App\Services;

use App\Models\Branch;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Throwable;

class TenantProvisioner
{
    public function __construct(
        private TenantDatabaseManager $databases,
        private TenantMigrator $migrator,
    ) {}

    public function provision(Branch $branch): Branch
    {
        if (! config('tenancy.enabled')) {
            return $branch;
        }

        if (filled($branch->database_name)) {
            $this->databases->configureTenantConnection($branch->database_name);
            $this->migrator->migrate($branch->database_name);

            return $branch;
        }

        $databaseName = $this->databases->makeDatabaseName($branch->name);

        try {
            $this->databases->createDatabase($databaseName);
            $this->migrator->migrate($databaseName);

            $branch->forceFill(['database_name' => $databaseName])->save();

            $this->seedTenant($branch);
        } catch (Throwable $e) {
            if ($this->databases->supportsCreateDatabase()) {
                try {
                    $this->databases->dropDatabase($databaseName);
                } catch (Throwable) {
                    // Best-effort cleanup.
                }
            }

            throw $e;
        }

        return $branch->refresh();
    }

    public function initialize(Branch $branch): void
    {
        if (! config('tenancy.enabled')) {
            TenantContext::set($branch);

            return;
        }

        if (! filled($branch->database_name)) {
            // Main / SaaS panel shares the central DB. Remap the tenant connection so
            // UsesTenantConnection models (COA, ledgers, transactions) hit central —
            // otherwise a prior client-branch switch leaves posts on the wrong database.
            $this->databases->configureCentralTenantConnection();
            TenantContext::set($branch);

            return;
        }

        $this->databases->configureTenantConnection($branch->database_name);
        TenantContext::set($branch);
        DB::setDefaultConnection(config('tenancy.central_connection'));
    }

    /**
     * Reset tenant connection + context to the central (SuperAdmin) panel.
     */
    public function initializeCentral(): void
    {
        TenantContext::clear();

        if (config('tenancy.enabled')) {
            $this->databases->configureCentralTenantConnection();
            DB::setDefaultConnection(config('tenancy.central_connection'));
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function usingBranch(Branch $branch, callable $callback): mixed
    {
        $previous = TenantContext::branch();

        try {
            $this->initialize($branch);

            return $callback();
        } finally {
            if ($previous !== null) {
                $this->initialize($previous);
            } else {
                $this->initializeCentral();
            }
        }
    }

    private function seedTenant(Branch $branch): void
    {
        $this->initialize($branch);

        DB::connection(config('tenancy.tenant_connection', 'tenant'))
            ->table('branches')
            ->updateOrInsert(
                ['id' => $branch->id],
                [
                    'name' => $branch->name,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                    'status' => $branch->status?->value ?? $branch->status ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

        SystemAccountService::seed($branch->id);
        DefaultCustomerService::seed($branch);
    }
}
