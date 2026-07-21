<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\ChartOfAccount;
use App\Services\SystemAccountService;
use Database\Seeders\ChartOfAccountsSeeder;

test('chart of accounts seeder creates parent accounts for each type', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $parentsByType = [
        AccountType::Asset->value => [
            SystemAccountKey::CashAndBank,
            SystemAccountKey::Inventory,
            SystemAccountKey::AccountsReceivable,
        ],
        AccountType::Liability->value => [
            SystemAccountKey::AccountsPayable,
            SystemAccountKey::LoansPayable,
            SystemAccountKey::AdvanceFromCustomer,
            SystemAccountKey::TaxesPayable,
        ],
        AccountType::Equity->value => [
            SystemAccountKey::OwnersCapital,
            SystemAccountKey::RetainedEarnings,
            SystemAccountKey::CurrentYearEarnings,
            SystemAccountKey::OwnersDrawings,
            SystemAccountKey::OpeningBalanceEquity,
        ],
        AccountType::Income->value => [
            SystemAccountKey::SalesRevenue,
            SystemAccountKey::OtherIncome,
        ],
        AccountType::Expenses->value => [SystemAccountKey::Expenses],
    ];

    foreach ($parentsByType as $type => $parentKeys) {
        foreach ($parentKeys as $parentKey) {
            $account = SystemAccountService::resolve($parentKey);

            expect($account->type->value)->toBe($type);
            expect($account->parent_id)->toBeNull();
            expect($account->is_system)->toBeTrue();
            expect($account->name)->toBe($parentKey->defaultName());
        }
    }
});

test('chart of accounts seeder creates child accounts under parent heads', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $childrenByParent = [
        [SystemAccountKey::CashAndBank, [
            SystemAccountKey::CashInHand,
            SystemAccountKey::SslCommerz,
            SystemAccountKey::Bkash,
            SystemAccountKey::Nagad,
        ]],
        [SystemAccountKey::Inventory, [
            SystemAccountKey::ProductInventory,
        ]],
        [SystemAccountKey::AccountsReceivable, [
            SystemAccountKey::CustomerReceivables,
            SystemAccountKey::IntercompanyReceivable,
        ]],
        [SystemAccountKey::AccountsPayable, [
            SystemAccountKey::SupplierPayables,
            SystemAccountKey::IntercompanyPayable,
        ]],
        [SystemAccountKey::TaxesPayable, [SystemAccountKey::OutputVat]],
        [SystemAccountKey::OpeningBalanceEquity, [SystemAccountKey::OpeningBalanceClearing]],
        [SystemAccountKey::SalesRevenue, [
            SystemAccountKey::ProductSales,
            SystemAccountKey::SalesReturns,
        ]],
        [SystemAccountKey::Expenses, [
            SystemAccountKey::CostOfGoodsSold,
            SystemAccountKey::InventoryDamage,
            SystemAccountKey::PurchaseReturns,
            SystemAccountKey::RentExpense,
            SystemAccountKey::SalaryExpense,
            SystemAccountKey::UtilitiesExpense,
            SystemAccountKey::DiscountApplied,
        ]],
    ];

    foreach ($childrenByParent as [$parentKey, $childKeys]) {
        $parentId = SystemAccountService::id($parentKey);

        foreach ($childKeys as $childKey) {
            $child = SystemAccountService::resolve($childKey);

            expect($child->parent_id)->toBe($parentId);
            expect($child->type)->toBe($childKey->accountType());
            expect($child->is_system)->toBeTrue();
        }
    }
});

test('chart of accounts seeder does not create input vat or wrapper categories', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    expect(ChartOfAccount::query()->where('account_number', 'SYS:input_vat')->exists())->toBeFalse();
    expect(ChartOfAccount::query()->where('account_number', 'SYS:current_assets')->exists())->toBeFalse();
    expect(ChartOfAccount::query()->where('account_number', 'SYS:current_liabilities')->exists())->toBeFalse();
    expect(ChartOfAccount::query()->where('account_number', 'SYS:income')->exists())->toBeFalse();
    expect(ChartOfAccount::query()->where('account_number', 'SYS:equity')->exists())->toBeFalse();

    $cashAndBank = SystemAccountService::resolve(SystemAccountKey::CashAndBank);
    $inventory = SystemAccountService::resolve(SystemAccountKey::Inventory);
    $productInventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory);
    $customerReceivables = SystemAccountService::resolve(SystemAccountKey::CustomerReceivables);

    expect($cashAndBank->code)->toBe('A001');
    expect($inventory->code)->toBe('A002');
    expect($productInventory->code)->toBe('A002-01');
    expect($productInventory->parent_id)->toBe($inventory->id);
    expect($customerReceivables->code)->toBe('A003-01');
});

test('chart of accounts seeder creates every system account key', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    foreach (SystemAccountKey::defaultSeededCases() as $key) {
        $account = ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->where('is_system', true)
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->first();

        expect($account)->not->toBeNull();
        expect($account->name)->toBe($key->defaultName());
        expect($account->type)->toBe($key->accountType());
    }
});

test('system account seed does not create duplicate accounts', function () {
    SystemAccountService::seed(null);
    SystemAccountService::seed(null);

    foreach (SystemAccountKey::defaultSeededCases() as $key) {
        $count = ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->where('is_system', true)
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->count();

        expect($count)->toBe(1);
    }

    expect(ChartOfAccount::query()
        ->where('is_system', true)
        ->whereNull('source_type')
        ->whereNull('source_id')
        ->count())
        ->toBe(count(SystemAccountKey::defaultSeededCases()));
});

test('retired system accounts are not seeded by default', function () {
    SystemAccountService::seed(null);

    expect(ChartOfAccount::query()
        ->where('account_number', SystemAccountKey::BankAccount->accountNumber())
        ->whereNull('source_type')
        ->whereNull('source_id')
        ->exists())->toBeFalse();

    expect(ChartOfAccount::query()
        ->where('account_number', SystemAccountKey::BranchInventory->accountNumber())
        ->whereNull('source_type')
        ->whereNull('source_id')
        ->exists())->toBeFalse();
});
