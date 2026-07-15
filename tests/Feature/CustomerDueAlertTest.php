<?php

use App\Enums\CustomerDueAlertStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDueAlert;
use App\Models\Sell;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function dueAlertUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('guests cannot access customer due alerts', function () {
    $this->get('/party/customer-due-alert')->assertRedirect(route('login'));
});

test('user without permission cannot view customer due alerts', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser();

    $this->actingAs($user)
        ->get('/party/customer-due-alert')
        ->assertForbidden();
});

test('user can create a customer due alert', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser([
        'party.customer-due-alert.view',
        'party.customer-due-alert.create',
    ]);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 500]);

    $this->actingAs($user)
        ->post('/party/customer-due-alert', [
            'customer_id' => $customer->id,
            'due_given_date' => '2026-07-01',
            'status' => CustomerDueAlertStatus::Unpaid->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CustomerDueAlert::query()->where('customer_id', $customer->id)->exists())->toBeTrue();

    $alert = CustomerDueAlert::query()->where('customer_id', $customer->id)->first();
    expect($alert->branch_id)->toBe($user->branch_id);
    expect($alert->due_given_date->format('Y-m-d'))->toBe('2026-07-01');
    expect($alert->status)->toBe(CustomerDueAlertStatus::Unpaid);
});

test('due alert index includes linked sale invoice number', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser(['party.customer-due-alert.view']);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 500]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'sell_id' => $sell->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid->value,
    ]);

    $this->actingAs($user)
        ->get('/party/customer-due-alert')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/customer-due-alert/index')
            ->where('alerts.data.0.invoice_number', $sell->invoice_number)
            ->has('dueSales'));
});

test('user can create a customer due alert linked to a sale invoice', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser([
        'party.customer-due-alert.view',
        'party.customer-due-alert.create',
    ]);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 500]);
    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 800,
        'paid_amount' => 300,
        'type' => SaleType::Sale,
    ]);

    $this->actingAs($user)
        ->post('/party/customer-due-alert', [
            'customer_id' => $customer->id,
            'sell_id' => $sell->id,
            'due_given_date' => '2026-07-01',
            'status' => CustomerDueAlertStatus::Unpaid->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $alert = CustomerDueAlert::query()->where('customer_id', $customer->id)->first();
    expect($alert->sell_id)->toBe($sell->id);
});

test('cannot create due alert for customer with no outstanding due', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser([
        'party.customer-due-alert.view',
        'party.customer-due-alert.create',
    ]);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    $this->actingAs($user)
        ->post('/party/customer-due-alert', [
            'customer_id' => $customer->id,
            'due_given_date' => '2026-07-01',
            'status' => CustomerDueAlertStatus::Unpaid->value,
        ])
        ->assertSessionHasErrors('customer_id');

    expect(CustomerDueAlert::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('user can update a customer due alert', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser([
        'party.customer-due-alert.view',
        'party.customer-due-alert.update',
    ]);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $alert = CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid->value,
    ]);

    $this->actingAs($user)
        ->patch("/party/customer-due-alert/{$alert->id}", [
            'customer_id' => $customer->id,
            'due_given_date' => '2026-08-01',
            'status' => CustomerDueAlertStatus::Paid->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($alert->fresh()->due_given_date->format('Y-m-d'))->toBe('2026-08-01');
    expect($alert->fresh()->status)->toBe(CustomerDueAlertStatus::Paid);
});

test('user can delete a customer due alert', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser([
        'party.customer-due-alert.view',
        'party.customer-due-alert.delete',
    ]);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $alert = CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid->value,
    ]);

    $this->actingAs($user)
        ->delete("/party/customer-due-alert/{$alert->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CustomerDueAlert::find($alert->id))->toBeNull();
});

test('branch user cannot modify alert from another branch', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser([
        'party.customer-due-alert.update',
        'party.customer-due-alert.delete',
    ]);
    $otherBranch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $otherBranch->id]);

    $alert = CustomerDueAlert::create([
        'branch_id' => $otherBranch->id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid->value,
    ]);

    $this->actingAs($user)
        ->delete("/party/customer-due-alert/{$alert->id}")
        ->assertNotFound();

    $this->actingAs($user)
        ->patch("/party/customer-due-alert/{$alert->id}", [
            'customer_id' => $customer->id,
            'due_given_date' => '2026-08-01',
            'status' => CustomerDueAlertStatus::Unpaid->value,
        ])
        ->assertNotFound();
});

test('api returns active due alert for customer', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser(['party.customer.view']);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid->value,
    ]);

    $this->actingAs($user)
        ->getJson("/api/customers/{$customer->id}/due-alert")
        ->assertSuccessful()
        ->assertJson([
            'active' => [
                'due_given_date' => '2026-07-01',
                'status' => CustomerDueAlertStatus::Unpaid->value,
            ],
        ]);
});

test('api returns null when customer has no active due alert', function () {
    $this->artisan('permissions:sync');

    $user = dueAlertUser(['party.customer.view']);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $response = $this->actingAs($user)
        ->getJson("/api/customers/{$customer->id}/due-alert");

    $response->assertSuccessful();
    expect($response->json('active'))->toBeNull();
});
