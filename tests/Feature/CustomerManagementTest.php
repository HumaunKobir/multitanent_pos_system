<?php

use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sell;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function customerManagementUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('customer can be created with opening balance from admin form', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.create']);
    $phone = fake()->unique()->numerify('01#########');

    $this->actingAs($user)
        ->post('/party/customer', [
            'name' => 'Due Customer',
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'opening_balance' => '1250',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('phone', $phone)->first();

    expect($customer)->not->toBeNull();
    expect((float) $customer->balance)->toBe(1250.0);
});

test('customer can be created with phone only from admin form', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.create']);
    $phone = fake()->unique()->numerify('01#########');

    $this->actingAs($user)
        ->post('/party/customer', [
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'opening_balance' => '',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('phone', $phone)->first();

    expect($customer)->not->toBeNull();
    expect($customer->name)->toBeNull();
});

test('customer can be created with phone only from sales api', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.create']);
    $phone = fake()->unique()->numerify('01#########');

    $this->actingAs($user)
        ->postJson('/api/customers', [
            'phone' => $phone,
        ])
        ->assertCreated()
        ->assertJson([
            'phone' => $phone,
            'name' => null,
        ]);
});

test('customer creation requires phone', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.create']);

    $this->actingAs($user)
        ->post('/party/customer', [
            'name' => 'No Phone Customer',
            'email' => '',
            'address' => '',
            'opening_balance' => '',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertSessionHasErrors('phone');
});

test('same phone can be used in different branches', function () {
    $this->artisan('permissions:sync');

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $phone = fake()->unique()->numerify('01#########');

    $userA = User::factory()->create(['branch_id' => $branchA->id]);
    Permission::findOrCreate('party.customer.create', 'web');
    $userA->givePermissionTo('party.customer.create');

    $userB = User::factory()->create(['branch_id' => $branchB->id]);
    $userB->givePermissionTo('party.customer.create');

    $this->actingAs($userA)
        ->post('/party/customer', [
            'name' => 'Branch A Customer',
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'opening_balance' => '',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertRedirect();

    $this->actingAs($userB)
        ->post('/party/customer', [
            'name' => 'Branch B Customer',
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'opening_balance' => '',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertRedirect();

    expect(Customer::query()->where('phone', $phone)->count())->toBe(2);
});

test('duplicate phone is rejected within the same branch', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.create']);
    $phone = fake()->unique()->numerify('01#########');

    $this->actingAs($user)
        ->post('/party/customer', [
            'name' => 'First Customer',
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'opening_balance' => '',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post('/party/customer', [
            'name' => 'Duplicate Customer',
            'phone' => $phone,
            'email' => '',
            'address' => '',
            'opening_balance' => '',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertSessionHasErrors('phone');
});

test('user without permission cannot download customer report', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser();
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->get(route('party.customer.report', $customer))
        ->assertForbidden();
});

test('user can download customer excel report', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.view']);
    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Report Customer',
        'balance' => 1500,
    ]);

    Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'type' => SaleType::Sale,
        'gross_amount' => 2000,
        'paid_amount' => 500,
    ]);

    CustomerPayment::query()->create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'date' => now()->format('Y-m-d'),
        'amount' => 300,
        'comment' => 'Partial collection',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('party.customer.report', $customer))
        ->assertSuccessful()
        ->assertDownload('customer-report-report-customer-'.now()->format('Y-m-d').'.xlsx');
});

test('branch user cannot download report for customer from another branch', function () {
    $this->artisan('permissions:sync');

    $user = customerManagementUser(['party.customer.view']);
    $otherBranch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $otherBranch->id]);

    $this->actingAs($user)
        ->get(route('party.customer.report', $customer))
        ->assertNotFound();
});
