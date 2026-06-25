<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sell;
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

function customerDueSale(User $user, Customer $customer): Sell
{
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    Batch::factory()->for($product)->withStock(50)->create(['branch_id' => $user->branch_id]);

    test()->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '0',
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '5000',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();

    expect($sell)->not->toBeNull();
    expect(max(0, (float) $sell->net_amount - (float) $sell->paid_amount))->toBeGreaterThan(0);

    return $sell;
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

test('user can record customer due collection against invoices and reduce customer due', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
        'inventory.sell.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $sell = customerDueSale($user, $customer);
    $invoiceDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);
    $collectionAmount = min(2000, $invoiceDue);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => '2026-06-07',
            'payment_account_id' => $cash->id,
            'comment' => 'Partial collection',
            'allocations' => [
                ['sell_id' => $sell->id, 'amount' => $collectionAmount],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $customer->refresh();
    $sell->refresh();

    expect((float) $customer->balance)->toBe($invoiceDue - $collectionAmount);
    expect((float) $sell->paid_amount)->toBe((float) $collectionAmount);

    $payment = CustomerPayment::query()->where('customer_id', $customer->id)->first();

    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe((float) $collectionAmount);
    expect($payment->comment)->toBe('Partial collection');
    expect($payment->created_by)->toBe($user->id);
    expect($payment->allocations)->toHaveCount(1);
});

test('collection allocation cannot exceed invoice due amount', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
        'inventory.sell.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $sell = customerDueSale($user, $customer);
    $invoiceDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => '2026-06-07',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['sell_id' => $sell->id, 'amount' => $invoiceDue + 1],
            ],
        ])
        ->assertSessionHasErrors('allocations.0.amount');

    expect((float) $customer->fresh()->balance)->toBe($invoiceDue);
    expect(CustomerPayment::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('user can delete customer due collection and restore customer and invoice due', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.delete',
        'inventory.sell.create',
    ]);
    seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $sell = customerDueSale($user, $customer);
    $invoiceDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);
    $collectionAmount = min(2000, $invoiceDue);

    $payment = CustomerPayment::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'date' => '2026-06-07',
        'amount' => $collectionAmount,
        'serial' => 'INVCP00000001',
        'created_by' => $user->id,
    ]);

    CustomerPaymentAllocation::create([
        'customer_payment_id' => $payment->id,
        'sell_id' => $sell->id,
        'amount' => $collectionAmount,
    ]);

    $sell->update(['paid_amount' => $collectionAmount]);
    $customer->update(['balance' => $invoiceDue - $collectionAmount]);
    $startingBalance = (float) $customer->fresh()->balance;

    $this->actingAs($user)
        ->delete("/party/customer-due-collection/{$payment->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(CustomerPayment::find($payment->id))->toBeNull();
    expect((float) $customer->fresh()->balance)->toBe($startingBalance + $collectionAmount);
    expect((float) $sell->fresh()->paid_amount)->toBe(0.0);
});

test('user can update customer due collection allocations and customer due', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
        'party.customer-due-collection.update',
        'inventory.sell.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $firstSell = customerDueSale($user, $customer);
    $secondSell = customerDueSale($user, $customer);
    $firstDue = max(0, (float) $firstSell->net_amount - (float) $firstSell->paid_amount);
    $secondDue = max(0, (float) $secondSell->net_amount - (float) $secondSell->paid_amount);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => '2026-06-07',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['sell_id' => $firstSell->id, 'amount' => 300],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = CustomerPayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/party/customer-due-collection/{$payment->id}", [
            'customer_id' => $customer->id,
            'date' => '2026-06-08',
            'payment_account_id' => $cash->id,
            'comment' => 'Updated collection',
            'allocations' => [
                ['sell_id' => $firstSell->id, 'amount' => 500],
                ['sell_id' => $secondSell->id, 'amount' => 400],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment->refresh();

    expect((float) $payment->amount)->toBe(900.0);
    expect($payment->comment)->toBe('Updated collection');
    expect((float) $customer->fresh()->balance)->toBe($firstDue + $secondDue - 900.0);
    expect((float) $firstSell->fresh()->paid_amount)->toBe(500.0);
    expect((float) $secondSell->fresh()->paid_amount)->toBe(400.0);
    expect($payment->fresh()->allocations)->toHaveCount(2);
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
        'inventory.sell.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $sell = customerDueSale($user, $customer);
    $invoiceDue = max(0, (float) $sell->net_amount - (float) $sell->paid_amount);
    $collectionAmount = min(500, $invoiceDue);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'comment' => 'GL collection',
            'allocations' => [
                ['sell_id' => $sell->id, 'amount' => $collectionAmount],
            ],
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

test('customer due collection index includes payment account from journal', function () {
    $this->artisan('permissions:sync');

    $user = customerDueCollectionUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
        'inventory.sell.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $sell = customerDueSale($user, $customer);
    $collectionAmount = min(500, max(0, (float) $sell->net_amount - (float) $sell->paid_amount));

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['sell_id' => $sell->id, 'amount' => $collectionAmount],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->get(route('party.customer-due-collection.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/customer-due-collection/index')
            ->has('payments.data', 1)
            ->where('payments.data.0.payment_account_id', $cash->id)
            ->where('payments.data.0.payment_account_label', fn ($label) => is_string($label) && $label !== ''));
});
