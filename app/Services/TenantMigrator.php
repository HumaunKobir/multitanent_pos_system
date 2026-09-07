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

        $migrator = app('migrator');
        $reflection = new \ReflectionProperty($migrator, 'paths');
        $reflection->setAccessible(true);
        $originalPaths = $reflection->getValue($migrator);

        try {
            $reflection->setValue($migrator, []);

            Artisan::call('migrate', [
                '--database' => $connection,
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        } finally {
            $reflection->setValue($migrator, $originalPaths);
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
