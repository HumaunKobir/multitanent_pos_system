<?php

use App\Enums\PromotionScope;
use App\Enums\SaleType;
use App\Models\Branch;
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

test('promotion clawback is zero when return qty is below the promotion min qty threshold', function () {
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

    // Returning 2 items (< min_qty 3): the return itself doesn't meet the threshold → no clawback
    expect($service->promotionClawback($line, 2, $sell))->toBe(0.0);
    // Returning all 3 items (= min_qty 3): meets the threshold → full clawback
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
