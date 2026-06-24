<?php

use App\Models\Branch;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\CustomerCoinTransaction;
use App\Models\Sell;
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
