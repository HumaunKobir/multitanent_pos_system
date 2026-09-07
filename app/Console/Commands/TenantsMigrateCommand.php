<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\TenantMigrator;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;

class TenantsMigrateCommand extends Command
{
    protected $signature = 'tenants:migrate {--branch= : Branch id or database_name}';

    protected $description = 'Run tenant migrations for one or all provisioned branches';

    public function handle(TenantMigrator $migrator, TenantProvisioner $provisioner): int
    {
        if (! config('tenancy.enabled')) {
            $this->warn('TENANCY_ENABLED is false. Enable it before migrating tenants.');

            return self::FAILURE;
        }

        $query = Branch::query()->whereNotNull('database_name');

        if ($branchFilter = $this->option('branch')) {
            $query->where(function ($q) use ($branchFilter): void {
                $q->whereKey($branchFilter)->orWhere('database_name', $branchFilter);
            });
        }

        $branches = $query->get();

        if ($branches->isEmpty()) {
            $this->warn('No provisioned branches found.');

            return self::SUCCESS;
        }

        foreach ($branches as $branch) {
            $this->info("Migrating {$branch->name} ({$branch->database_name})...");
            $migrator->migrate($branch->database_name);
            $provisioner->initialize($branch);
        }

        $this->info('Tenant migrations complete.');

        return self::SUCCESS;
    }
}
