<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\DefaultCustomerService;
use Spatie\Permission\Models\Permission;

function branchAdminWithPermission(string $permission): User
{
    $user = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);

    return $user;
}

test('creating a branch seeds a default walk-in customer', function () {
    $branch = Branch::factory()->create([
        'phone' => '01712345678',
    ]);

    $customer = Customer::query()
        ->where('branch_id', $branch->id)
        ->where('is_default', true)
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe(DefaultCustomerService::WALK_IN_CUSTOMER_NAME)
        ->and($customer->phone)->toBe('01712345678')
        ->and($customer->is_default)->toBeTrue();
});

test('storing a branch via admin creates the default walk-in customer', function () {
    $admin = branchAdminWithPermission('branch.create');

    $this->actingAs($admin)
        ->post(route('branch.store'), [
            'name' => 'New Outlet '.fake()->unique()->word(),
            'phone' => '01898765432',
            'address' => 'Dhaka, Bangladesh',
        ])
        ->assertRedirect(route('branch.index'));

    $branch = Branch::query()->where('phone', '01898765432')->first();

    expect($branch)->not->toBeNull();

    $customer = Customer::query()
        ->where('branch_id', $branch->id)
        ->where('is_default', true)
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe(DefaultCustomerService::WALK_IN_CUSTOMER_NAME)
        ->and($customer->phone)->toBe('01898765432')
        ->and($customer->is_default)->toBeTrue();
});

test('default customer seed is idempotent for a branch', function () {
    $branch = Branch::factory()->create();

    DefaultCustomerService::seed($branch);
    DefaultCustomerService::seed($branch);

    expect(
        Customer::query()
            ->where('branch_id', $branch->id)
            ->where('is_default', true)
            ->count()
    )->toBe(1);
});
