<?php

use App\Enums\CustomerDueAlertStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDueAlert;
use App\Models\User;
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
