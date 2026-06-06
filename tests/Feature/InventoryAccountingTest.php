<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function accountingUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('purchase posts balanced journal with inventory input vat and payable lines', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create']);
    $cash = seedAccountingAccounts();

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '10',
            'paid_amount' => '550',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '1000',
                    'quantity' => '1',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();
    expect($purchase)->not->toBeNull();

    $transaction = Transaction::query()
        ->where('source_type', Purchase::class)
        ->where('source_id', $purchase->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $totalDebit = $ledgers->sum(fn (Ledger $line) => (float) $line->debit);
    $totalCredit = $ledgers->sum(fn (Ledger $line) => (float) $line->credit);

    expect(round($totalDebit, 2))->toBe(round($totalCredit, 2));
    expect($ledgers->where('debit', '>', 0)->count())->toBeGreaterThan(0);
    expect($ledgers->where('credit', '>', 0)->count())->toBeGreaterThan(0);

    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(550.0);
});

test('supplier payment posts payable debit and cash credit', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
    ]);
    $cash = seedAccountingAccounts();

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 2000,
    ]);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'amount' => 500,
            'payment_account_id' => $cash->id,
            'comment' => 'GL payment',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = SupplierPayment::query()->latest('id')->first();
    expect($payment)->not->toBeNull();

    $transaction = Transaction::query()
        ->where('source_type', SupplierPayment::class)
        ->where('source_id', $payment->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect(round($ledgers->sum('debit'), 2))->toBe(round($ledgers->sum('credit'), 2));
});

test('supplier opening balance creates payable journal entry', function () {
    $this->artisan('permissions:sync');
    seedAccountingAccounts();

    $user = accountingUser(['party.supplier.create']);

    $this->actingAs($user)
        ->post('/party/supplier', [
            'name' => 'Opening Supplier '.fake()->unique()->numerify('####'),
            'phone' => fake()->unique()->numerify('01#########'),
            'company_name' => 'Test Co',
            'address' => 'Dhaka',
            'opening_balance' => '1500',
        ])
        ->assertRedirect();

    $supplier = Supplier::query()->latest('id')->first();
    expect($supplier)->not->toBeNull();
    expect((float) $supplier->balance)->toBe(1500.0);

    $transaction = Transaction::query()
        ->where('source_type', Supplier::class)
        ->where('source_id', $supplier->id)
        ->first();

    expect($transaction)->not->toBeNull();
});

test('customer opening balance creates receivable journal entry', function () {
    $this->artisan('permissions:sync');
    seedAccountingAccounts();

    $user = accountingUser(['party.customer.create']);

    $this->actingAs($user)
        ->post('/party/customer', [
            'name' => 'Opening Customer '.fake()->unique()->numerify('####'),
            'phone' => fake()->unique()->numerify('01#########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => 'Dhaka',
            'opening_balance' => '800',
            'is_default' => '0',
            'status' => 1,
        ])
        ->assertRedirect();

    $customer = Customer::query()->latest('id')->first();
    expect($customer)->not->toBeNull();
    expect((float) $customer->balance)->toBe(800.0);

    $transaction = Transaction::query()
        ->where('source_type', Customer::class)
        ->where('source_id', $customer->id)
        ->first();

    expect($transaction)->not->toBeNull();
});

test('account opening balance posts ledger entry instead of direct balance only', function () {
    $this->artisan('permissions:sync');
    seedAccountingAccounts();

    $user = accountingUser(['accounts.create']);

    $this->actingAs($user)
        ->post('/accounts', [
            'parent_id' => SystemAccountService::resolve(SystemAccountKey::CurrentAssets)->id,
            'type' => AccountType::Asset->value,
            'name' => 'Petty Cash '.fake()->unique()->word(),
            'status' => CommonStatus::Active->value,
            'opening_balance' => '2500',
        ])
        ->assertRedirect(route('accounts.index'));

    $account = ChartOfAccount::query()->latest('id')->first();
    expect($account)->not->toBeNull();
    expect((float) $account->current_balance)->toBe(2500.0);

    $transaction = Transaction::query()
        ->where('source_type', ChartOfAccount::class)
        ->where('source_id', $account->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect(Ledger::query()->where('transaction_id', $transaction->id)->count())->toBeGreaterThan(0);
});

test('purchase delete reverses accounting transaction', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create', 'inventory.purchase.delete']);
    $cash = seedAccountingAccounts();

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    Batch::factory()->for($product)->withStock(50)->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)->post('/inventory/purchase', [
        'supplier_id' => $supplier->id,
        'date' => now()->format('Y-m-d'),
        'discount' => '0',
        'vat' => '0',
        'paid_amount' => '0',
        'comment' => null,
        'items' => [[
            'product_id' => $product->id,
            'variation_id' => null,
            'unit_price' => '100',
            'quantity' => '1',
            'free_quantity' => '0',
        ]],
    ]);

    $purchase = Purchase::query()->latest('id')->first();
    $transactionId = Transaction::query()
        ->where('source_type', Purchase::class)
        ->where('source_id', $purchase->id)
        ->value('id');

    expect($transactionId)->not->toBeNull();

    $this->actingAs($user)
        ->delete("/inventory/purchase/{$purchase->id}")
        ->assertRedirect(route('inventory.purchase.index'));

    expect(Transaction::find($transactionId))->toBeNull();
});

test('inventory purchase journal remains balanced', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create']);
    $cash = seedAccountingAccounts();

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    Batch::factory()->for($product)->withStock(10)->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)->post('/inventory/purchase', [
        'supplier_id' => $supplier->id,
        'date' => now()->format('Y-m-d'),
        'discount' => '0',
        'vat' => '0',
        'paid_amount' => '500',
        'payment_account_id' => $cash->id,
        'items' => [[
            'product_id' => $product->id,
            'variation_id' => null,
            'unit_price' => '1000',
            'quantity' => '1',
            'free_quantity' => '0',
        ]],
    ])->assertRedirect();

    $purchase = Purchase::query()->latest('id')->first();
    expect($purchase)->not->toBeNull();

    $transaction = Transaction::query()
        ->where('source_type', Purchase::class)
        ->where('source_id', $purchase->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect(round($ledgers->sum('debit'), 2))->toBe(round($ledgers->sum('credit'), 2));
});
