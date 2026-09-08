<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Services\SystemAccountService;
use Database\Seeders\ChartOfAccountsSeeder;

test('superadmin chart of accounts seeder creates only platform billing and SaaS accounts', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $parentsByType = [
        AccountType::Asset->value => [
            SystemAccountKey::CashAndBank,
            SystemAccountKey::AccountsReceivable,
        ],
        AccountType::Liability->value => [
            SystemAccountKey::LoansPayable,
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
            $account = SystemAccountService::resolve($parentKey, null);

            expect($account->type->value)->toBe($type);
            expect($account->parent_id)->toBeNull();
            expect($account->is_system)->toBeTrue();
            expect($account->name)->toBe($parentKey->defaultName());
        }
    }

    // SuperAdmin child accounts
    $childrenByParent = [
        [SystemAccountKey::CashAndBank, [
            SystemAccountKey::CashInHand,
            SystemAccountKey::SslCommerz,
            SystemAccountKey::Bkash,
            SystemAccountKey::Nagad,
        ]],
        [SystemAccountKey::AccountsReceivable, [
            SystemAccountKey::SubscriptionReceivable,
        ]],
        [SystemAccountKey::TaxesPayable, [
            SystemAccountKey::OutputVat,
            SystemAccountKey::TaxesPaid,
        ]],
        [SystemAccountKey::OpeningBalanceEquity, [SystemAccountKey::OpeningBalanceClearing]],
        [SystemAccountKey::SalesRevenue, [
            SystemAccountKey::SubscriptionIncome,
        ]],
        [SystemAccountKey::Expenses, [
            SystemAccountKey::RentExpense,
            SystemAccountKey::SalaryExpense,
            SystemAccountKey::UtilitiesExpense,
        ]],
    ];

    foreach ($childrenByParent as [$parentKey, $childKeys]) {
        $parentId = SystemAccountService::id($parentKey, null);

        foreach ($childKeys as $childKey) {
            $child = SystemAccountService::resolve($childKey, null);

            expect($child->parent_id)->toBe($parentId);
            expect($child->type)->toBe($childKey->accountType());
            expect($child->is_system)->toBeTrue();
        }
    }
});

test('branch chart of accounts seeder creates complete retail store and subscription accounts', function () {
    $branch = Branch::factory()->create();
    SystemAccountService::seed($branch->id);

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
            SystemAccountKey::SubscriptionPayable,
        ]],
        [SystemAccountKey::TaxesPayable, [
            SystemAccountKey::OutputVat,
            SystemAccountKey::TaxesPaid,
        ]],
        [SystemAccountKey::OpeningBalanceEquity, [SystemAccountKey::OpeningBalanceClearing]],
        [SystemAccountKey::SalesRevenue, [
            SystemAccountKey::ProductSales,
            SystemAccountKey::SalesReturns,
            SystemAccountKey::DiscountApplied,
            SystemAccountKey::CoinDiscountApplied,
        ]],
        [SystemAccountKey::OtherIncome, [
            SystemAccountKey::StockAdjustmentGain,
        ]],
        [SystemAccountKey::Expenses, [
            SystemAccountKey::CostOfGoodsSold,
            SystemAccountKey::InventoryDamage,
            SystemAccountKey::StockAdjustmentLoss,
            SystemAccountKey::RentExpense,
            SystemAccountKey::SalaryExpense,
            SystemAccountKey::UtilitiesExpense,
            SystemAccountKey::SubscriptionExpense,
        ]],
    ];

    foreach ($childrenByParent as [$parentKey, $childKeys]) {
        $parentId = SystemAccountService::id($parentKey, $branch->id);

        foreach ($childKeys as $childKey) {
            $child = SystemAccountService::resolve($childKey, $branch->id);

            expect($child->parent_id)->toBe($parentId);
            expect($child->type)->toBe($childKey->accountType());
            expect($child->is_system)->toBeTrue();
        }
    }
});

test('superadmin chart of accounts does not contain retail inventory or retail store accounts', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $retailAccountNumbers = [
        SystemAccountKey::Inventory->accountNumber(),
        SystemAccountKey::ProductInventory->accountNumber(),
        SystemAccountKey::CustomerReceivables->accountNumber(),
        SystemAccountKey::AccountsPayable->accountNumber(),
        SystemAccountKey::SupplierPayables->accountNumber(),
        SystemAccountKey::CostOfGoodsSold->accountNumber(),
        SystemAccountKey::ProductSales->accountNumber(),
        SystemAccountKey::InventoryDamage->accountNumber(),
    ];

    foreach ($retailAccountNumbers as $accountNumber) {
        expect(ChartOfAccount::query()
            ->where('account_number', $accountNumber)
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->exists())->toBeFalse("Account {$accountNumber} should not exist on SuperAdmin global panel");
    }
});

test('system account seed does not create duplicate accounts', function () {
    SystemAccountService::seed(null);
    SystemAccountService::seed(null);

    foreach (SystemAccountKey::defaultSeededCases(null) as $key) {
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
        ->toBe(count(SystemAccountKey::defaultSeededCases(null)));
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

