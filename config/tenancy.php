<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Hybrid Tenancy
    |--------------------------------------------------------------------------
    |
    | When false, the app behaves as a single shared database (legacy mode).
    | When true, users/branches stay on the central connection and business
    | data is read/written on the per-branch "tenant" connection.
    |
    */

    'enabled' => (bool) env('TENANCY_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Connection Names
    |--------------------------------------------------------------------------
    */

    'central_connection' => env('TENANCY_CENTRAL_CONNECTION', env('DB_CONNECTION', 'mysql')),

    'tenant_connection' => env('TENANCY_TENANT_CONNECTION', 'tenant'),

    /*
    |--------------------------------------------------------------------------
    | Database Name Prefix
    |--------------------------------------------------------------------------
    |
    | Tenant database names are "{prefix}{slug}". Prefix avoids reserved names
    | and collisions with the central database.
    |
    */

    'database_prefix' => env('TENANCY_DATABASE_PREFIX', 'tenant_'),

    /*
    |--------------------------------------------------------------------------
    | Privileged Connection for CREATE DATABASE
    |--------------------------------------------------------------------------
    |
    | Optional connection name used only to create/drop databases. Falls back
    | to the central connection when null.
    |
    */

    'admin_connection' => env('TENANCY_ADMIN_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Migration Paths
    |--------------------------------------------------------------------------
    */

    'central_migrations_path' => database_path('migrations/central'),

    'tenant_migrations_path' => database_path('migrations/tenant'),

];
