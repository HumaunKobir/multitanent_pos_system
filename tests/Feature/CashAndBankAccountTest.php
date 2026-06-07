<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Ledger;
use App\Models\User;
use App\Services\InventoryAccountingService;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function cashBankUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('system seeds cash and bank as top level account', function () {
    SystemAccountService::seed();

    $cashAndBank = SystemAccountService::resolve(SystemAccountKey::CashAndBank);

    expect($cashAndBank->name)->toBe('Cash & Bank');
    expect($cashAndBank->code)->toBe('A001');
    expect($cashAndBank->parent_id)->toBeNull();
    expect($cashAndBank->is_system)->toBeTrue();
});

test('system seeds default cash and bank payment accounts', function () {
    SystemAccountService::seed();

    $cashAndBankId = SystemAccountService::id(SystemAccountKey::CashAndBank);

    $cashInHand = SystemAccountService::resolve(SystemAccountKey::CashInHand);
    $sslCommerz = SystemAccountService::resolve(SystemAccountKey::SslCommerz);

    expect($cashInHand->name)->toBe('Cash in Hand');
    expect($cashInHand->parent_id)->toBe($cashAndBankId);
    expect($cashInHand->is_system)->toBeTrue();

    expect($sslCommerz->name)->toBe('SSLCommerz');
    expect($sslCommerz->code)->toBe('A001-02');
    expect($sslCommerz->parent_id)->toBe($cashAndBankId);
    expect($sslCommerz->is_system)->toBeTrue();
});

test('system seeds purchase returns and default expense accounts', function () {
    SystemAccountService::seed();

    $expensesId = SystemAccountService::id(SystemAccountKey::Expenses);

    $purchaseReturns = SystemAccountService::resolve(SystemAccountKey::PurchaseReturns);
    $rent = SystemAccountService::resolve(SystemAccountKey::RentExpense);
    $salary = SystemAccountService::resolve(SystemAccountKey::SalaryExpense);
    $utilities = SystemAccountService::resolve(SystemAccountKey::UtilitiesExpense);

    expect($purchaseReturns->name)->toBe('Purchase Returns');
    expect($purchaseReturns->code)->toBe('X001-03');
    expect($purchaseReturns->parent_id)->toBe($expensesId);
    expect($purchaseReturns->is_system)->toBeTrue();

    expect($rent->name)->toBe('Rent');
    expect($rent->code)->toBe('X001-04');
    expect($rent->parent_id)->toBe($expensesId);

    expect($salary->name)->toBe('Salary');
    expect($salary->code)->toBe('X001-05');
    expect($salary->parent_id)->toBe($expensesId);

    expect($utilities->name)->toBe('Utilities');
    expect($utilities->code)->toBe('X001-06');
    expect($utilities->parent_id)->toBe($expensesId);
});

test('system accounts cannot be updated or deleted', function () {
    $this->artisan('permissions:sync');

    $user = cashBankUser(['accounts.view', 'accounts.update', 'accounts.delete']);
    SystemAccountService::seed($user->branch_id);
    $cashInHand = SystemAccountService::resolve(SystemAccountKey::CashInHand, $user->branch_id);

    $this->actingAs($user)
        ->patch("/accounts/{$cashInHand->id}", [
            'parent_id' => '',
            'type' => AccountType::Asset->value,
            'name' => 'Changed Cash',
            'status' => CommonStatus::Active->value,
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->delete("/accounts/{$cashInHand->id}")
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHas('error');

    expect($cashInHand->fresh()->name)->toBe('Cash in Hand');
});

test('payment account scope only includes cash and bank children', function () {
    $user = cashBankUser();
    SystemAccountService::seed($user->branch_id);

    $cash = seedAccountingAccounts(user: $user);

    $this->actingAs($user);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $user->branch_id);

    $paymentAccountIds = ChartOfAccount::query()->paymentAccount()->pluck('id')->all();

    expect($paymentAccountIds)->toContain($cash->id);
    expect($paymentAccountIds)->not->toContain($inventory->id);
});

test('customer due collection posts to cash and bank child account', function () {
    $this->artisan('permissions:sync');

    $user = cashBankUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 5000,
    ]);

    $this->actingAs($user);

    app(InventoryAccountingService::class)->postCustomerOpeningBalance(
        $customer,
        5000,
        '2026-06-07',
    );

    $this->post('/party/customer-due-collection', [
        'customer_id' => $customer->id,
        'amount' => 2000,
        'payment_account_id' => $cash->id,
        'date' => '2026-06-07',
        'comment' => 'Due collection',
    ])
        ->assertRedirect();

    $payment = CustomerPayment::query()->latest('id')->first();

    expect($payment)->not->toBeNull();

    $ledger = Ledger::query()
        ->where('account_id', $cash->id)
        ->where('debit', '2000.00')
        ->first();

    expect($ledger)->not->toBeNull();
});

test('customer due collection rejects account not under cash and bank', function () {
    $this->artisan('permissions:sync');

    $user = cashBankUser([
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    SystemAccountService::seed($user->branch_id);

    ChartOfAccount::$skipCodeGeneration = true;

    $invalidCash = ChartOfAccount::query()->firstOrCreate(
        ['code' => 'A001-INVALID-CASH', ...ChartOfAccount::panelSourceAttributes($user->branch_id)],
        [
            'parent_id' => SystemAccountService::resolve(SystemAccountKey::Inventory, $user->branch_id)->id,
            'name' => 'Invalid Cash Account',
            'type' => AccountType::Asset,
            'status' => CommonStatus::Active,
            'current_balance' => 0,
        ],
    );

    ChartOfAccount::$skipCodeGeneration = false;

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 5000,
    ]);

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'amount' => 2000,
            'payment_account_id' => $invalidCash->id,
            'date' => '2026-06-07',
            'comment' => 'Due collection',
        ])
        ->assertSessionHasErrors('payment_account_id');
});
