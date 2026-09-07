<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TenantDatabaseManager
{
    public function makeDatabaseName(string $branchName): string
    {
        $prefix = (string) config('tenancy.database_prefix', 'tenant_');
        $slug = Str::slug($branchName, '_');

        if ($slug === '') {
            throw new InvalidArgumentException('Branch name cannot produce a valid database name.');
        }

        $base = $prefix.$slug;
        $candidate = $base;
        $suffix = 1;

        while ($this->databaseNameExists($candidate)) {
            $candidate = $base.'_'.$suffix;
            $suffix++;
        }

        $this->assertSafeDatabaseName($candidate);

        return $candidate;
    }

    public function assertSafeDatabaseName(string $name): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{1,62}$/', $name)) {
            throw new InvalidArgumentException("Unsafe tenant database name [{$name}].");
        }

        $central = (string) config('database.connections.'.config('tenancy.central_connection').'.database');

        if ($name === $central) {
            throw new InvalidArgumentException('Tenant database name cannot match the central database.');
        }
    }

    public function databaseNameExists(string $name): bool
    {
        try {
            if (Schema::connection(config('tenancy.central_connection'))->hasTable('branches')
                && Schema::connection(config('tenancy.central_connection'))->hasColumn('branches', 'database_name')
                && Branch::query()->where('database_name', $name)->exists()) {
                return true;
            }
        } catch (Throwable) {
            // Central DB may be unavailable during unit checks.
        }

        if (! $this->supportsCreateDatabase()) {
            return false;
        }

        try {
            $connection = $this->adminConnectionName();
            $schema = DB::connection($connection)->select(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$name]
            );

            return $schema !== [];
        } catch (Throwable) {
            return false;
        }
    }

    public function createDatabase(string $databaseName): void
    {
        $this->assertSafeDatabaseName($databaseName);

        if (! $this->supportsCreateDatabase()) {
            throw new RuntimeException('Tenant database provisioning requires MySQL or MariaDB.');
        }

        $connection = $this->adminConnectionName();
        $charset = config("database.connections.{$connection}.charset", 'utf8mb4');
        $collation = config("database.connections.{$connection}.collation", 'utf8mb4_unicode_ci');

        DB::connection($connection)->statement(
            sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                $databaseName,
                $charset,
                $collation,
            )
        );
    }

    public function dropDatabase(string $databaseName): void
    {
        $this->assertSafeDatabaseName($databaseName);

        if (! $this->supportsCreateDatabase()) {
            throw new RuntimeException('Tenant database drop requires MySQL or MariaDB.');
        }

        $connection = $this->adminConnectionName();

        DB::connection($connection)->statement(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
    }

    public function configureTenantConnection(string $databaseName): void
    {
        $this->assertSafeDatabaseName($databaseName);

        $tenant = config('tenancy.tenant_connection', 'tenant');
        $central = config('tenancy.central_connection');
        $base = config("database.connections.{$central}");

        if (! is_array($base)) {
            throw new RuntimeException("Central connection [{$central}] is not configured.");
        }

        config([
            "database.connections.{$tenant}" => array_merge($base, [
                'database' => $databaseName,
            ]),
        ]);

        DB::purge($tenant);
    }

    public function supportsCreateDatabase(): bool
    {
        $central = config('tenancy.central_connection');
        $driver = config("database.connections.{$central}.driver");

        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    private function adminConnectionName(): string
    {
        return (string) (config('tenancy.admin_connection') ?: config('tenancy.central_connection'));
    }
}
