<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TenantMigrator
{
    public function __construct(private TenantDatabaseManager $databases) {}

    public function migrate(string $databaseName): void
    {
        $this->databases->configureTenantConnection($databaseName);

        $connection = config('tenancy.tenant_connection', 'tenant');

        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            Artisan::call('migrate', [
                '--database' => $connection,
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        } finally {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
