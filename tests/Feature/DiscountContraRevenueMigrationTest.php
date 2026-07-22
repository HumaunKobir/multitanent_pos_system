<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Services\SystemAccountService;

test('seed flips leftover positive discount balances so sales revenue is net of discount', function () {
    $branch = Branch::factory()->create();
    SystemAccountService::seed($branch->id);

    $salesRevenue = SystemAccountService::resolve(SystemAccountKey::SalesRevenue, $branch->id);
    $productSales = SystemAccountService::resolve(SystemAccountKey::ProductSales, $branch->id);
    $discount = SystemAccountService::resolve(SystemAccountKey::DiscountApplied, $branch->id);

    expect($discount->type)->toBe(AccountType::Income)
        ->and($discount->parent_id)->toBe($salesRevenue->id);

    // Simulate expense-era leftover: Income type already, but debit left a positive balance.
    $productSales->update(['current_balance' => 120]);
    $discount->update(['current_balance' => 1]);

    $transaction = Transaction::query()->create([
        'date' => now()->format('Y-m-d'),
        'description' => 'Legacy discount sign',
        'amount' => 1,
        'source_type' => ChartOfAccount::class,
        'source_id' => $discount->id,
    ]);

    Ledger::query()->create([
        'account_id' => $discount->id,
        'transaction_id' => $transaction->id,
        'date' => now()->format('Y-m-d'),
        'opening_balance' => 0,
        'debit' => 1,
        'credit' => 0,
        'closing_balance' => 1,
        'description' => 'Discount applied',
        'source_type' => ChartOfAccount::class,
        'source_id' => $discount->id,
    ]);

    SystemAccountService::seed($branch->id);

    $discount->refresh();
    $productSales->refresh();

    expect(round((float) $discount->current_balance, 2))->toBe(-1.0)
        ->and(round((float) $productSales->current_balance + (float) $discount->current_balance, 2))->toBe(119.0);

    $ledger = Ledger::query()->where('account_id', $discount->id)->latest('id')->first();
    expect(round((float) $ledger->closing_balance, 2))->toBe(-1.0);
});

test('seed does not flip coin discount credits that correctly increase income contra reverse', function () {
    $branch = Branch::factory()->create();
    SystemAccountService::seed($branch->id);

    $coinDiscount = SystemAccountService::resolve(SystemAccountKey::CoinDiscountApplied, $branch->id);
    $coinDiscount->update(['current_balance' => 20]);

    $transaction = Transaction::query()->create([
        'date' => now()->format('Y-m-d'),
        'description' => 'Coin discount reverse',
        'amount' => 20,
        'source_type' => ChartOfAccount::class,
        'source_id' => $coinDiscount->id,
    ]);

    Ledger::query()->create([
        'account_id' => $coinDiscount->id,
        'transaction_id' => $transaction->id,
        'date' => now()->format('Y-m-d'),
        'opening_balance' => 0,
        'debit' => 0,
        'credit' => 20,
        'closing_balance' => 20,
        'description' => 'Discount reversed',
        'source_type' => ChartOfAccount::class,
        'source_id' => $coinDiscount->id,
    ]);

    SystemAccountService::seed($branch->id);

    expect(round((float) $coinDiscount->fresh()->current_balance, 2))->toBe(20.0);
});
