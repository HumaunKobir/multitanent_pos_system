<?php

use App\Enums\SaleType;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\Transaction;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use App\Services\ReportService;
use App\Services\SystemAccountService;

test('sale earn stores money value on customer coin payable without coin discount expense', function () {
    $user = accountingUser();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    CoinSettings::query()->create([
        'branch_id' => $user->branch_id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 100,
    ]);

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
        'discount_type' => 'flat',
        'discount_value' => 0,
        'special_discount_amount' => 0,
        'coin_discount_amount' => 0,
        'coins_redeemed' => 0,
        'coins_earned' => 10,
        'vat' => 0,
        'paid_amount' => 1000,
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
        [['payment_account_id' => $cash->id, 'amount' => 1000.0]],
        $cogs,
    );

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $coinDiscountId = SystemAccountService::id(SystemAccountKey::CoinDiscountApplied, $user->branch_id);
    $coinPayableId = SystemAccountService::id(SystemAccountKey::CustomerCoinPayable, $user->branch_id);
    $salesId = SystemAccountService::id(SystemAccountKey::ProductSales, $user->branch_id);

    // Earn: money value on payable only — Coin Discount Applied stays unchanged.
    expect(round((float) $ledgers->where('account_id', $coinDiscountId)->sum('debit'), 2))->toBe(0.0);
    expect(round((float) $ledgers->where('account_id', $coinPayableId)->sum('credit'), 2))->toBe(10.0);
    expect(round((float) $ledgers->where('account_id', $salesId)->sum('debit'), 2))->toBe(10.0);
    expect(round((float) $ledgers->sum('debit'), 2))->toBe(round((float) $ledgers->sum('credit'), 2));

    $this->actingAs($user);
    $date = now()->format('Y-m-d');
    $pl = app(ReportService::class)->profitAndLoss($date, $date, $user->branch_id);

    expect((float) $pl['sales_discounts'])->toBe(0.0)
        ->and((float) $pl['net_sales'])->toBe(990.0);
});

test('sale redeem posts money value to coin discount applied and reduces payable', function () {
    $user = accountingUser();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    CoinSettings::query()->create([
        'branch_id' => $user->branch_id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 100,
    ]);

    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);
    $batch = Batch::factory()->for($product)->withStock(20)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 40,
    ]);

    // Redeem ৳30 (use), earn ৳10 (have) — amounts are money, not coin counts.
    $sell = Sell::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'discount' => 0,
        'discount_type' => 'flat',
        'discount_value' => 0,
        'special_discount_amount' => 0,
        'coin_discount_amount' => 30,
        'coins_redeemed' => 30,
        'coins_earned' => 10,
        'vat' => 0,
        'paid_amount' => 970,
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
        [['payment_account_id' => $cash->id, 'amount' => 970.0]],
        $cogs,
    );

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $coinDiscountId = SystemAccountService::id(SystemAccountKey::CoinDiscountApplied, $user->branch_id);
    $coinPayableId = SystemAccountService::id(SystemAccountKey::CustomerCoinPayable, $user->branch_id);

    expect(round((float) $ledgers->where('account_id', $coinDiscountId)->sum('debit'), 2))->toBe(30.0);
    expect(round((float) $ledgers->where('account_id', $coinPayableId)->sum('debit'), 2))->toBe(30.0);
    expect(round((float) $ledgers->where('account_id', $coinPayableId)->sum('credit'), 2))->toBe(10.0);
    expect(round((float) $ledgers->sum('debit'), 2))->toBe(round((float) $ledgers->sum('credit'), 2));
});

test('sale return reverses coin use expense and coin earn payable', function () {
    $user = accountingUser();
    $cash = seedAccountingAccounts(branchId: $user->branch_id);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    CoinSettings::query()->create([
        'branch_id' => $user->branch_id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 100,
    ]);

    $sell = Sell::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'discount' => 0,
        'coin_discount_amount' => 20,
        'coins_redeemed' => 20,
        'coins_earned' => 10,
        'vat' => 0,
        'paid_amount' => 980,
        'type' => SaleType::Sale,
    ]);

    $saleReturn = SaleReturn::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'sell_id' => $sell->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'discount_amount' => 0,
        'vat_amount' => 0,
        'paid_amount' => 1000,
    ]);

    app(InventoryAccountingService::class)->postSaleReturn(
        $saleReturn->fresh(['customer', 'sell']),
        [['payment_account_id' => $cash->id, 'amount' => 1000.0]],
        0.0,
    );

    $transaction = Transaction::query()
        ->where('source_type', SaleReturn::class)
        ->where('source_id', $saleReturn->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $coinDiscountId = SystemAccountService::id(SystemAccountKey::CoinDiscountApplied, $user->branch_id);
    $coinPayableId = SystemAccountService::id(SystemAccountKey::CustomerCoinPayable, $user->branch_id);

    expect(round((float) $ledgers->where('account_id', $coinDiscountId)->sum('credit'), 2))->toBe(20.0);
    expect(round((float) $ledgers->where('account_id', $coinPayableId)->sum('credit'), 2))->toBe(20.0);
    expect(round((float) $ledgers->where('account_id', $coinPayableId)->sum('debit'), 2))->toBe(10.0);
    expect(round((float) $ledgers->sum('debit'), 2))->toBe(round((float) $ledgers->sum('credit'), 2));
});
