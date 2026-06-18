<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

test('role permission seeder creates default roles with permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    $ecommerceRole = Role::query()->where('name', 'Ecommerce Manager')->first();
    $branchRole = Role::query()->where('name', 'Branch Manager')->first();

    expect($ecommerceRole)->not->toBeNull()
        ->and($branchRole)->not->toBeNull()
        ->and($ecommerceRole->hasPermissionTo('online-order.view'))->toBeTrue()
        ->and($ecommerceRole->hasPermissionTo('setting.website.view'))->toBeTrue()
        ->and($ecommerceRole->hasPermissionTo('product.visible-on-store'))->toBeTrue()
        ->and($branchRole->hasPermissionTo('inventory.sell.view'))->toBeTrue()
        ->and($branchRole->hasPermissionTo('party.customer.view'))->toBeTrue()
        ->and($branchRole->hasPermissionTo('report.daily-summary.view'))->toBeTrue()
        ->and($branchRole->hasPermissionTo('inventory.stock-distribution.view'))->toBeFalse();
});

test('role permission seeder is idempotent', function () {
    $this->seed(RolePermissionSeeder::class);
    $roleCount = Role::query()->count();

    $this->seed(RolePermissionSeeder::class);

    expect(Role::query()->count())->toBe($roleCount);
});

test('database seeder creates ecommerce and operating branch users with roles', function () {
    $this->seed(DatabaseSeeder::class);

    EcommerceBranchService::resetResolvedId();

    $ecommerceBranch = Branch::query()
        ->where('name', Branch::ECOMMERCE_BRANCH_NAME)
        ->first();

    $operatingBranch = Branch::query()
        ->where('name', Branch::OPERATING_BRANCH_NAME)
        ->first();

    $ecommerceAdmin = User::query()
        ->where('email', User::ECOMMERCE_BRANCH_USER_EMAIL)
        ->first();

    $branchManager = User::query()
        ->where('email', User::OPERATING_BRANCH_ADMIN_EMAIL)
        ->first();

    expect($ecommerceBranch)->not->toBeNull()
        ->and($operatingBranch)->not->toBeNull()
        ->and($ecommerceAdmin?->branch_id)->toBe($ecommerceBranch->id)
        ->and($branchManager?->branch_id)->toBe($operatingBranch->id)
        ->and($ecommerceAdmin?->hasRole('Ecommerce Manager'))->toBeTrue()
        ->and($branchManager?->hasRole('Branch Manager'))->toBeTrue()
        ->and(User::query()->where('email', User::ECOMMERCE_BRANCH_ADMIN_EMAIL)->exists())->toBeFalse()
        ->and($ecommerceAdmin?->can('online-order.view'))->toBeTrue()
        ->and($branchManager?->can('inventory.sell.create'))->toBeTrue()
        ->and($branchManager?->can('online-order.view'))->toBeFalse();
});

test('database seeded branch users appear in user management list', function () {
    $this->seed(DatabaseSeeder::class);

    $superAdmin = User::query()
        ->where('email', 'superadmin@coolness.com')
        ->firstOrFail();

    $this->actingAs($superAdmin)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($users) => collect($users)->pluck('email')->contains(User::ECOMMERCE_BRANCH_USER_EMAIL)
                && collect($users)->pluck('email')->contains(User::OPERATING_BRANCH_ADMIN_EMAIL)));
});
