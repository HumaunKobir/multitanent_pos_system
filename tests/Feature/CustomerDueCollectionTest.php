<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryAccountingService;
use Spatie\Permission\Models\Permission;

function customerWithReceivable(User $user, float $due): Customer
{
    seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => $due,
    ]);

    test()->actingAs($user);

    app(InventoryAccountingService::class)->postCustomerOpeningBalance(
        $customer,
        $due,
        now()->format('Y-m-d'),
    );

    return $customer;
}

function customerDueCollectionUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('guests cannot access customer due collection', function () {
    $this->get('/party/customer-due-collection')->assertRedirect(route('login'));
});

test('user without permission cannot view customer due collection', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser();

    $this->actingAs($user)
        ->get('/party/customer-due-collection')
        ->assertForbidden();
});

test('user can record customer due collection and reduce customer due', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    $customer = customerWithReceivable($user, 5000);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => '2026-06-07',
            'amount' => 2000,
            'payment_account_id' => $cash->id,
            'comment' => 'Partial collection',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $customer->refresh();

    expect((float) $customer->balance)->toBe(3000.0);

    $payment = CustomerPayment::query()->where('customer_id', $customer->id)->first();

    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(2000.0);
    expect($payment->comment)->toBe('Partial collection');
    expect($payment->created_by)->toBe($user->id);
});

test('collection amount cannot exceed customer due balance', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 100,
    ]);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => '2026-06-07',
            'amount' => 500,
            'payment_account_id' => $cash->id,
        ])
        ->assertSessionHasErrors('amount');

    expect((float) $customer->fresh()->balance)->toBe(100.0);
    expect(CustomerPayment::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('user can delete customer due collection and restore customer due', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.delete',
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 3000,
    ]);

    $payment = CustomerPayment::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'date' => '2026-06-07',
        'amount' => 2000,
        'serial' => 'INVCP00000001',
        'created_by' => $user->id,
    ]);

    $customer->update(['balance' => 1000]);

    $this->actingAs($user)
        ->delete("/party/customer-due-collection/{$payment->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CustomerPayment::find($payment->id))->toBeNull();
    expect((float) $customer->fresh()->balance)->toBe(3000.0);
});

test('branch user cannot delete collection from another branch', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser(['party.customer-due-collection.delete']);
    $otherBranch = Branch::factory()->create();

    $payment = CustomerPayment::create([
        'branch_id' => $otherBranch->id,
        'customer_id' => Customer::factory()->create(['branch_id' => $otherBranch->id])->id,
        'date' => '2026-06-07',
        'amount' => 500,
        'serial' => 'INVCP00000099',
    ]);

    $this->actingAs($user)
        ->delete("/party/customer-due-collection/{$payment->id}")
        ->assertNotFound();
});

test('customer due collection posts cash debit and receivable credit', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    $customer = customerWithReceivable($user, 2000);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'amount' => 500,
            'payment_account_id' => $cash->id,
            'comment' => 'GL collection',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = CustomerPayment::query()->latest('id')->first();
    expect($payment)->not->toBeNull();

    $transaction = Transaction::query()
        ->where('source_type', CustomerPayment::class)
        ->where('source_id', $payment->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect(round($ledgers->sum('debit'), 2))->toBe(round($ledgers->sum('credit'), 2));
});
