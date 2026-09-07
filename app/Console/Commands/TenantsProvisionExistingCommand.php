<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\TenantDatabaseManager;
use App\Services\TenantMigrator;
use App\Services\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantsProvisionExistingCommand extends Command
{
    protected $signature = 'tenants:provision-existing
        {--branch= : Only provision this branch id}
        {--copy : Copy branch-scoped rows from the central database into each tenant database}
        {--dry-run : Show actions without creating databases}';

    protected $description = 'Create tenant databases for existing branches and optionally copy their data';

    public function handle(
        TenantDatabaseManager $databases,
        TenantMigrator $migrator,
        TenantProvisioner $provisioner,
    ): int {
        if (! config('tenancy.enabled')) {
            $this->warn('Set TENANCY_ENABLED=true before provisioning existing branches.');

            return self::FAILURE;
        }

        if (! $databases->supportsCreateDatabase()) {
            $this->error('Provisioning requires MySQL or MariaDB.');

            return self::FAILURE;
        }

        $query = Branch::query()->orderBy('id');

        if ($branchId = $this->option('branch')) {
            $query->whereKey($branchId);
        }

        $branches = $query->get();

        if ($branches->isEmpty()) {
            $this->warn('No branches found.');

            return self::SUCCESS;
        }

        $central = config('tenancy.central_connection');

        foreach ($branches as $branch) {
            $this->line("Branch #{$branch->id} {$branch->name}");

            if ($this->option('dry-run')) {
                $name = $branch->database_name ?: $databases->makeDatabaseName($branch->name);
                $this->info("  would use database: {$name}");

                continue;
            }

            try {
                if (! filled($branch->database_name)) {
                    $databaseName = $databases->makeDatabaseName($branch->name);
                    $databases->createDatabase($databaseName);
                    $migrator->migrate($databaseName);
                    $branch->forceFill(['database_name' => $databaseName])->save();
                    $this->info("  created {$databaseName}");
                } else {
                    $migrator->migrate($branch->database_name);
                    $this->info("  migrated {$branch->database_name}");
                }

                if ($this->option('copy')) {
                    $copied = $this->copyBranchData($central, $branch, $databases);
                    $this->info("  copied {$copied} table batches");
                }

                $provisioner->initialize($branch);
            } catch (Throwable $e) {
                $this->error('  failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function copyBranchData(string $central, Branch $branch, TenantDatabaseManager $databases): int
    {
        $databases->configureTenantConnection($branch->database_name);
        $tenant = config('tenancy.tenant_connection', 'tenant');
        $copied = 0;

        $tables = collect(Schema::connection($central)->getTableListing(null, false))
            ->reject(fn (string $table): bool => in_array($table, [
                'users',
                'password_reset_tokens',
                'sessions',
                'cache',
                'cache_locks',
                'jobs',
                'job_batches',
                'failed_jobs',
                'branches',
                'migrations',
                'permissions',
                'roles',
                'model_has_permissions',
                'model_has_roles',
                'role_has_permissions',
                'password_reset_otps',
            ], true))
            ->values();

        foreach ($tables as $table) {
            if (! Schema::connection($central)->hasColumn($table, 'branch_id')) {
                continue;
            }

            if (! Schema::connection($tenant)->hasTable($table)) {
                continue;
            }

            $rows = DB::connection($central)
                ->table($table)
                ->where('branch_id', $branch->id)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            foreach ($rows->chunk(200) as $chunk) {
                $payload = $chunk->map(fn ($row) => (array) $row)->all();
                DB::connection($tenant)->table($table)->insertOrIgnore($payload);
            }

            $copied++;
        }

        return $copied;
    }
}
