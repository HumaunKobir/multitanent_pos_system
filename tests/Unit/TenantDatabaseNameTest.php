<?php

use App\Services\TenantDatabaseManager;
use Tests\TestCase;

uses(TestCase::class);

test('makeDatabaseName slugifies branch names with prefix', function () {
    $manager = app(TenantDatabaseManager::class);

    $name = $manager->makeDatabaseName('Gulshan Outlet '.fake()->unique()->numerify('###'));

    expect($name)
        ->toStartWith('tenant_')
        ->toContain('gulshan_outlet');
});

test('assertSafeDatabaseName rejects unsafe names', function () {
    $manager = app(TenantDatabaseManager::class);

    expect(fn () => $manager->assertSafeDatabaseName('Bad-Name!'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $manager->assertSafeDatabaseName('1starts_with_number'))
        ->toThrow(InvalidArgumentException::class);
});

test('assertSafeDatabaseName accepts valid tenant names', function () {
    $manager = app(TenantDatabaseManager::class);

    $manager->assertSafeDatabaseName('tenant_gulshan_outlet');

    expect(true)->toBeTrue();
});
