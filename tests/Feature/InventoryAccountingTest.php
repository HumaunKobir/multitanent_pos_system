<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SaleType;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sell;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\ReportService;
use App\Services\SystemAccountService;
use Inertia\Testing\AssertableInertia as Assert;
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

test('purchase posts balanced journal with inventory and payable lines', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

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

test('purchase with full discount creates no journal entry', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create']);
    seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '1000',
            'vat' => '0',
            'paid_amount' => '0',
            'payment_account_id' => null,
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

    expect($transaction)->toBeNull();
});

test('supplier payment posts payable debit and cash credit', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
    ]);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 2000,
    ]);

    $this->actingAs($user);

    app(InventoryAccountingService::class)->postSupplierOpeningBalance(
        $supplier,
        2000,
        now()->format('Y-m-d'),
    );

    $this->post('/party/supplier-payment', [
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

    $user = accountingUser(['party.supplier.create']);
    seedAccountingAccounts(user: $user);

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

    $user = accountingUser(['party.customer.create']);
    seedAccountingAccounts(user: $user);

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

    $user = accountingUser(['accounts.create']);
    seedAccountingAccounts(user: $user);

    $this->actingAs($user)
        ->post('/accounts', [
            'parent_id' => SystemAccountService::resolve(SystemAccountKey::CashAndBank, $user->branch_id)->id,
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
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

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
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

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

test('purchase create with insufficient payment account balance returns warning and does not create purchase', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create']);
    $cash = seedAccountingAccounts(0, $user->branch_id);
    $cash->update(['current_balance' => 0]);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $purchaseCountBefore = Purchase::query()->count();

    $this->actingAs($user)
        ->from(route('inventory.purchase.create'))
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
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
        ->assertRedirect(route('inventory.purchase.create'))
        ->assertSessionHas('warning')
        ->assertSessionMissing('success');

    expect(Purchase::query()->count())->toBe($purchaseCountBefore);
});

test('purchase can be created fully on due with empty paid amount', function () {
    $this->artisan('permissions:sync');

    $user = accountingUser(['inventory.purchase.create']);
    seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->from(route('inventory.purchase.create'))
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '',
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '1',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'))
        ->assertSessionHas('success');

    $purchase = Purchase::query()->latest('id')->first();

    expect($purchase)->not->toBeNull();
    expect((float) $purchase->paid_amount)->toBe(0.0);
    expect((float) $purchase->due_amount)->toBe(200.0);

    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(200.0);
});

test('purchase edit page pre-fills payment account from journal', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = accountingUser(['inventory.purchase.create', 'inventory.purchase.update']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

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
            'vat' => '0',
            'paid_amount' => '550',
            'payment_account_id' => $cash->id,
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

    $this->actingAs($user)
        ->get("/inventory/purchase/{$purchase->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/edit')
            ->where('purchase.payment_account_id', $cash->id));
});

test('sale with discount posts product sales gross and discount applied expense', function () {
    $user = accountingUser();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);
    $batch = Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);

    $sell = Sell::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 500,
        'discount' => 50,
        'discount_type' => 'flat',
        'discount_value' => 50,
        'special_discount_amount' => 0,
        'vat' => 0,
        'paid_amount' => 450,
        'type' => SaleType::Sale,
    ]);

    $sell->products()->create([
        'branch_id' => $user->branch_id,
        'product_id' => $product->id,
        'variation_id' => null,
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'batches' => [(string) $batch->id => 5.0],
    ]);

    $sell->load('products');
    $cogs = app(InventoryCostService::class)->costForSell($sell);

    app(InventoryAccountingService::class)->postSale(
        $sell->fresh(['customer']),
        [['payment_account_id' => $cash->id, 'amount' => 450.0]],
        $cogs,
    );

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $salesId = SystemAccountService::id(SystemAccountKey::ProductSales, $user->branch_id);
    $discountId = SystemAccountService::id(SystemAccountKey::DiscountApplied, $user->branch_id);

    expect(round((float) $ledgers->where('account_id', $salesId)->sum('credit'), 2))->toBe(500.0);
    expect(round((float) $ledgers->where('account_id', $discountId)->sum('debit'), 2))->toBe(50.0);
    expect(round((float) $ledgers->sum('debit'), 2))->toBe(round((float) $ledgers->sum('credit'), 2));

    $this->actingAs($user);

    $date = now()->format('Y-m-d');
    $pl = app(ReportService::class)->profitAndLoss($date, $date, $user->branch_id);
    expect((float) $pl['sales_revenue'])->toBe(500.0);
    expect((float) $pl['sales_discounts'])->toBe(50.0);
    expect((float) $pl['net_sales'])->toBe(450.0);

    $bs = app(ReportService::class)->balanceSheet($date);
    expect($bs['is_balanced'])->toBeTrue();
});

test('profit and loss includes output vat collected in the period', function () {
    $user = accountingUser();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);
    $batch = Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);

    $sell = Sell::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 50,
        'paid_amount' => 1050,
        'type' => SaleType::Sale,
    ]);

    $sell->products()->create([
        'branch_id' => $user->branch_id,
        'product_id' => $product->id,
        'variation_id' => null,
        'quantity' => 10,
        'unit_price' => 100,
        'discount' => 0,
        'batches' => [(string) $batch->id => 10.0],
    ]);

    $sell->load('products');
    $cogs = app(InventoryCostService::class)->costForSell($sell);

    app(InventoryAccountingService::class)->postSale(
        $sell->fresh(['customer']),
        [['payment_account_id' => $cash->id, 'amount' => 1050.0]],
        $cogs,
    );

    $this->actingAs($user);

    $date = now()->format('Y-m-d');
    $pl = app(ReportService::class)->profitAndLoss($date, $date, $user->branch_id);

    expect((float) $pl['sales_revenue'])->toBe(1000.0)
        ->and((float) $pl['output_vat'])->toBe(50.0)
        ->and((float) $pl['net_sales'])->toBe(1000.0);
});

test('sale posts commercial discount and coin redeem to coin discount applied', function () {
    $user = accountingUser();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);
    $batch = Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);

    $sell = Sell::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'discount' => 50,
        'discount_type' => 'flat',
        'discount_value' => 50,
        'special_discount_amount' => 0,
        'coin_discount_amount' => 30,
        'coins_redeemed' => 30,
        'coins_earned' => 0,
        'vat' => 0,
        'paid_amount' => 920,
        'type' => SaleType::Sale,
    ]);

    $sell->products()->create([
        'branch_id' => $user->branch_id,
        'product_id' => $product->id,
        'variation_id' => null,
        'quantity' => 10,
        'unit_price' => 100,
        'discount' => 0,
        'batches' => [(string) $batch->id => 10.0],
    ]);

    $sell->load('products');
    $cogs = app(InventoryCostService::class)->costForSell($sell);

    app(InventoryAccountingService::class)->postSale(
        $sell->fresh(['customer']),
        [['payment_account_id' => $cash->id, 'amount' => 920.0]],
        $cogs,
    );

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $discountId = SystemAccountService::id(SystemAccountKey::DiscountApplied, $user->branch_id);
    $coinDiscountId = SystemAccountService::id(SystemAccountKey::CoinDiscountApplied, $user->branch_id);
    $coinPayableId = SystemAccountService::id(SystemAccountKey::CustomerCoinPayable, $user->branch_id);
    $salesId = SystemAccountService::id(SystemAccountKey::ProductSales, $user->branch_id);

    expect(round((float) $ledgers->where('account_id', $salesId)->sum('credit'), 2))->toBe(1030.0);
    expect(round((float) $ledgers->where('account_id', $discountId)->sum('debit'), 2))->toBe(50.0);
    expect(round((float) $ledgers->where('account_id', $coinDiscountId)->sum('debit'), 2))->toBe(30.0);
    expect(round((float) $ledgers->where('account_id', $coinPayableId)->sum('debit'), 2))->toBe(30.0);
    expect(round((float) $ledgers->sum('debit'), 2))->toBe(round((float) $ledgers->sum('credit'), 2));

    $this->actingAs($user);
    $date = now()->format('Y-m-d');
    $pl = app(ReportService::class)->profitAndLoss($date, $date, $user->branch_id);

    expect((float) $pl['sales_discounts'])->toBe(80.0)
        ->and((float) $pl['net_sales'])->toBe(950.0);
});
