<?php

use App\Enums\CoinExpiryUnit;
use App\Enums\CoinTransactionType;
use App\Models\Branch;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\CustomerCoinLot;
use App\Models\CustomerCoinTransaction;
use App\Models\ProductExchange;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\User;
use App\Services\CoinService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->coinService = app(CoinService::class);
});

function coinSettings(array $overrides = []): CoinSettings
{
    return new CoinSettings(array_merge([
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 50,
    ], $overrides));
}

function coinCustomer(float $balance = 50, bool $isDefault = false): Customer
{
    return new Customer([
        'is_default' => $isDefault,
        'point' => $balance,
    ]);
}

test('redeem discount converts coins to taka and caps at net', function () {
    $settings = coinSettings(['coin_value' => 2]);

    expect($this->coinService->redeemDiscount(10, $settings, 500))->toBe(20.0);
    expect($this->coinService->redeemDiscount(100, $settings, 15))->toBe(15.0);
});

test('earn coins uses earn base and spend threshold', function () {
    $settings = coinSettings(['earn_spend_amount' => 100, 'earn_coins' => 2]);

    expect($this->coinService->earnCoins(480, $settings))->toBe(8.0);
    expect($this->coinService->earnCoins(99, $settings))->toBe(0.0);
});

test('max redeemable coins respects balance bill and percent caps', function () {
    $settings = coinSettings(['coin_value' => 1, 'max_redeem_percent' => 50]);
    $customer = coinCustomer(200);

    expect($this->coinService->maxRedeemableCoins($customer, $settings, 500))->toBe(200.0);

    $customer = coinCustomer(400);

    expect($this->coinService->maxRedeemableCoins($customer, $settings, 500))->toBe(250.0);
});

test('walk-in customer cannot redeem coins', function () {
    $settings = coinSettings();
    $customer = coinCustomer(50, isDefault: true);

    try {
        $this->coinService->resolveForSale($customer, $settings, 500, 10, 490);
        expect(false)->toBeTrue('Expected validation exception');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('coins_redeemed');
    }
});

test('resolve for sale earns on net before coin even when coins are redeemed', function () {
    $settings = coinSettings();
    $customer = coinCustomer(49);

    $result = $this->coinService->resolveForSale($customer, $settings, 200, 49, 200);

    expect($result['coins_redeemed'])->toBe(49.0);
    expect($result['coin_discount_amount'])->toBe(49.0);
    expect($result['coins_earned'])->toBe(2.0);
});

test('resolve for sale returns earned coins on net payable amount', function () {
    $settings = coinSettings();
    $customer = coinCustomer(50);

    $result = $this->coinService->resolveForSale($customer, $settings, 500, 20, 500);

    expect($result['coins_redeemed'])->toBe(20.0);
    expect($result['coin_discount_amount'])->toBe(20.0);
    expect($result['coins_earned'])->toBe(5.0);
});

test('resolve for sale earns coins on full net amount even when payment is partial', function () {
    $settings = coinSettings();
    $customer = coinCustomer(50);

    $result = $this->coinService->resolveForSale($customer, $settings, 1000, 0, 1000);

    expect($result['coins_earned'])->toBe(10.0);
});

test('coin settings resolve expires at using configured unit', function () {
    $settings = coinSettings([
        'expiry_value' => 6,
        'expiry_unit' => CoinExpiryUnit::Month,
    ]);

    $from = now()->startOfDay();

    expect($settings->resolveExpiresAt($from)?->toDateString())->toBe($from->copy()->addMonths(6)->toDateString());
    expect(
        coinSettings([
            'expiry_value' => 1,
            'expiry_unit' => CoinExpiryUnit::Year,
        ])->resolveExpiresAt($from)?->toDateString()
    )->toBe($from->copy()->addYears(1)->toDateString());
    expect(coinSettings(['expiry_value' => null])->resolveExpiresAt())->toBeNull();
});

test('apply to sale creates earn lot with expiry and redeem consumes fefo', function () {
    $branch = Branch::factory()->create();
    CoinSettings::query()->create([
        'branch_id' => $branch->id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 100,
        'expiry_value' => 1,
        'expiry_unit' => 'week',
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 0,
        'is_default' => false,
    ]);
    $sellA = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'created_at' => now()->subHours(3),
    ]);
    $sellB = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
    ]);

    $this->coinService->applyToSale($sellA, $customer->fresh(), [
        'coins_redeemed' => 0,
        'coin_discount_amount' => 0,
        'coins_earned' => 10,
    ]);

    $lotA = CustomerCoinLot::query()->where('sell_id', $sellA->id)->first();
    expect($lotA)->not->toBeNull();
    expect((float) $lotA->remaining_coins)->toBe(10.0);
    expect($lotA->expires_at?->toDateTimeString())->toBe(
        $sellA->created_at->copy()->addWeek()->toDateTimeString()
    );

    $lotA->update(['expires_at' => now()->addDay()]);

    $this->coinService->applyToSale($sellB, $customer->fresh(), [
        'coins_redeemed' => 0,
        'coin_discount_amount' => 0,
        'coins_earned' => 10,
    ]);

    $lotB = CustomerCoinLot::query()->where('sell_id', $sellB->id)->first();
    $lotB->update(['expires_at' => now()->addDays(10)]);

    $customer->refresh();
    expect((float) $customer->point)->toBe(20.0);

    $redeemSell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
    ]);

    $this->coinService->applyToSale($redeemSell, $customer->fresh(), [
        'coins_redeemed' => 6,
        'coin_discount_amount' => 6,
        'coins_earned' => 0,
    ]);

    $lotA->refresh();
    $lotB->refresh();
    $customer->refresh();

    expect((float) $lotA->remaining_coins)->toBe(4.0);
    expect((float) $lotB->remaining_coins)->toBe(10.0);
    expect((float) $customer->point)->toBe(14.0);

    $this->coinService->reverseForSell($redeemSell);
    $lotA->refresh();
    $customer->refresh();

    expect((float) $lotA->remaining_coins)->toBe(10.0);
    expect((float) $customer->point)->toBe(20.0);
});

test('expire lots removes remaining coins and updates customer balance', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 15,
        'is_default' => false,
    ]);
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
    ]);

    $earn = CustomerCoinTransaction::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'type' => CoinTransactionType::Earn,
        'coins' => 10,
        'balance_after' => 15,
        'meta' => [],
    ]);

    $expiredLot = CustomerCoinLot::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'earn_transaction_id' => $earn->id,
        'sell_id' => $sell->id,
        'original_coins' => 10,
        'remaining_coins' => 7,
        'expires_at' => now()->subDay(),
    ]);

    $futureEarn = CustomerCoinTransaction::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'type' => CoinTransactionType::Earn,
        'coins' => 5,
        'balance_after' => 15,
        'meta' => [],
    ]);

    CustomerCoinLot::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'earn_transaction_id' => $futureEarn->id,
        'original_coins' => 5,
        'remaining_coins' => 5,
        'expires_at' => now()->addWeek(),
    ]);

    $result = $this->coinService->expireLotsForCustomer($customer->id);

    $expiredLot->refresh();
    $customer->refresh();

    expect($result['lots'])->toBe(1);
    expect($result['coins'])->toBe(7.0);
    expect((float) $expiredLot->remaining_coins)->toBe(0.0);
    expect((float) $customer->point)->toBe(8.0);
    expect(
        CustomerCoinTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', CoinTransactionType::Expire)
            ->exists()
    )->toBeTrue();
});

test('resolve for sale accepts coin balance offset when editing an existing sale', function () {
    $settings = coinSettings();
    $customer = coinCustomer(5);

    $result = $this->coinService->resolveForSale($customer, $settings, 200, 49, 200, 44);

    expect($result['coins_redeemed'])->toBe(49.0);
    expect($result['coin_discount_amount'])->toBe(49.0);
});

test('insufficient balance throws validation error', function () {
    $settings = coinSettings();
    $customer = coinCustomer(5);

    try {
        $this->coinService->resolveForSale($customer, $settings, 500, 10, 490);
        expect(false)->toBeTrue('Expected validation exception');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('coins_redeemed');
    }
});

test('reverse for sell restores balance from sell snapshot when ledger rows are missing', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 19,
        'is_default' => false,
    ]);
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'coins_redeemed' => 50,
        'coins_earned' => 5,
        'coin_discount_amount' => 50,
    ]);

    $this->coinService->reverseForSell($sell);

    $customer->refresh();

    expect((float) $customer->point)->toBe(64.0);
    expect(CustomerCoinTransaction::query()->where('sell_id', $sell->id)->count())->toBe(2);
});

test('reverse proportional for sale return adjusts customer balance', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 35,
        'is_default' => false,
    ]);
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'coins_redeemed' => 20,
        'coins_earned' => 5,
        'coin_discount_amount' => 20,
    ]);
    $saleReturn = SaleReturn::query()->create([
        'branch_id' => $branch->id,
        'user_id' => User::factory()->create(['branch_id' => $branch->id])->id,
        'sell_id' => $sell->id,
        'customer_id' => $customer->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 500,
        'payment_type' => 5,
    ]);

    $this->coinService->reverseProportionalForSaleReturn($sell, $saleReturn, 0.5);

    $customer->refresh();

    expect((float) $customer->point)->toBe(42.5);
});

test('restore for sale return rolls back proportional coin reversal', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 35,
        'is_default' => false,
    ]);
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'coins_redeemed' => 20,
        'coins_earned' => 5,
        'coin_discount_amount' => 20,
    ]);
    $saleReturn = SaleReturn::query()->create([
        'branch_id' => $branch->id,
        'user_id' => User::factory()->create(['branch_id' => $branch->id])->id,
        'sell_id' => $sell->id,
        'customer_id' => $customer->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 500,
        'payment_type' => 5,
    ]);

    $this->coinService->reverseProportionalForSaleReturn($sell, $saleReturn, 1.0);
    $this->coinService->restoreForSaleReturn($sell, $saleReturn);

    $customer->refresh();

    expect((float) $customer->point)->toBe(35.0);
});

test('reverse for exchange only reverses unreversed redeem and earn rows', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 100,
        'is_default' => false,
    ]);
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);
    $exchange = ProductExchange::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'customer_id' => $customer->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'net_amount' => 980,
        'coins_redeemed' => 20,
        'coin_discount_amount' => 20,
        'coins_earned' => 5,
        'paid_amount' => 0,
        'due_amount' => 0,
        'price_difference' => 0,
    ]);

    $this->coinService->applyToExchange($exchange, $customer->fresh(), [
        'coins_redeemed' => 20,
        'coin_discount_amount' => 20,
        'coins_earned' => 5,
        'effective_paid' => 0,
    ]);

    $customer->refresh();
    expect((float) $customer->point)->toBe(85.0);

    $this->coinService->reverseForExchange($exchange);
    $customer->refresh();
    expect((float) $customer->point)->toBe(100.0);

    // Second reverse must be a no-op (previously double-reversed and inflated balance).
    $this->coinService->reverseForExchange($exchange);
    $customer->refresh();
    expect((float) $customer->point)->toBe(100.0);

    $this->coinService->applyToExchange($exchange, $customer->fresh(), [
        'coins_redeemed' => 10,
        'coin_discount_amount' => 10,
        'coins_earned' => 2,
        'effective_paid' => 0,
    ]);
    $customer->refresh();
    expect((float) $customer->point)->toBe(92.0);

    $this->coinService->reverseForExchange($exchange);
    $this->coinService->reverseForExchange($exchange);
    $customer->refresh();
    expect((float) $customer->point)->toBe(100.0);

    expect(
        CustomerCoinTransaction::query()
            ->where('product_exchange_id', $exchange->id)
            ->count()
    )->toBe(8);
});
