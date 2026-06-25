<?php

use App\Enums\PromotionScope;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use App\Services\SaleReturnDiscountService;
use Tests\TestCase;

uses(TestCase::class);

test('promotion clawback removes min qty discount when return drops below threshold', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'sale_price' => 100,
    ]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->withMinQty(3)->create([
        'branch_id' => $branch->id,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 300,
        'discount' => 0,
        'vat' => 0,
        'type' => SaleType::Sale,
    ]);

    $line = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'promotion_id' => $promotion->id,
        'quantity' => 3,
        'unit_price' => 100,
        'original_unit_price' => 100,
        'discount' => 0,
        'promotion_discount' => 30,
    ]);

    $service = app(SaleReturnDiscountService::class);

    expect($service->promotionClawback($line, 2, $sell))->toBe(30.0);
    expect($service->promotionClawback($line, 3, $sell))->toBe(30.0);
});

test('promotion clawback is zero when remaining quantity still meets min qty', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'sale_price' => 250,
    ]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->withMinQty(3)->create([
        'branch_id' => $branch->id,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1250,
        'discount' => 0,
        'vat' => 0,
        'type' => SaleType::Sale,
    ]);

    $line = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'promotion_id' => $promotion->id,
        'quantity' => 5,
        'unit_price' => 250,
        'original_unit_price' => 250,
        'discount' => 0,
        'promotion_discount' => 37.5,
    ]);

    $service = app(SaleReturnDiscountService::class);

    expect($service->promotionClawback($line, 2, $sell))->toBe(0.0);
});

test('coin clawback respects max redeem percent on remaining portion', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    CoinSettings::query()->create([
        'branch_id' => $branch->id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 10,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'point' => 500,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'coin_discount_amount' => 100,
        'coins_redeemed' => 100,
        'type' => SaleType::Sale,
    ]);

    $service = app(SaleReturnDiscountService::class);

    $returnCoin = $service->coinDiscountClawback($sell, 400.0);

    expect($returnCoin)->toBe(40.0);
});
