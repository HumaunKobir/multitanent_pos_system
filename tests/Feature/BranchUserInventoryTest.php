<?php

use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Sell;
use App\Models\User;

test('branch user only sees their own sells in index', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $userOne = User::factory()->create(['branch_id' => $branch->id]);
    $userTwo = User::factory()->create(['branch_id' => $branch->id]);
    $userOne->givePermissionTo('inventory.sell.view');

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $userOne->id,
        'type' => SaleType::Sale,
    ]);
    Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $userTwo->id,
        'type' => SaleType::Sale,
    ]);

    $this->actingAs($userOne)
        ->get('/inventory/sell')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/sell/index')
            ->has('sells.data', 1));
});

test('branch user only sees their own purchases in index', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $userOne = User::factory()->create(['branch_id' => $branch->id]);
    $userTwo = User::factory()->create(['branch_id' => $branch->id]);
    $userOne->givePermissionTo('inventory.purchase.view');

    Purchase::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $userOne->id,
        'date' => now()->toDateString(),
        'gross_amount' => 100,
        'paid_amount' => 100,
        'due_amount' => 0,
        'serial' => 'INVP00000001',
    ]);
    Purchase::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $userTwo->id,
        'date' => now()->toDateString(),
        'gross_amount' => 200,
        'paid_amount' => 200,
        'due_amount' => 0,
        'serial' => 'INVP00000002',
    ]);

    $this->actingAs($userOne)
        ->get('/inventory/purchase')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('purchases.data', 1));
});

test('super admin can find branch user sells by customer search', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('inventory.sell.view');

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Super Admin Lookup '.fake()->unique()->numerify('###'),
    ]);

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => User::factory()->create()->id,
        'customer_id' => $customer->id,
        'type' => SaleType::Sale,
    ]);

    $this->actingAs($admin)
        ->get('/inventory/sell?search='.urlencode($customer->name))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/sell/index')
            ->has('sells.data', 1));
});
