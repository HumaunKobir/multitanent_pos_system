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

function saleReturnParentSell(array $overrides = []): Sell
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'sale_price' => 100]);

    $sell = Sell::factory()->create(array_merge([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'round_off_amount' => 0,
        'vat' => 0,
        'type' => SaleType::Sale,
    ], $overrides));

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 100,
        'original_unit_price' => 100,
        'discount' => 0,
    ]);

    return $sell->fresh();
}

test('vat is charged on the taxable base before invoice discount and round off', function () {
    $service = app(SaleReturnDiscountService::class);
    $parent = saleReturnParentSell();

    // 10% VAT with a flat 100 invoice discount: VAT must be 10% of the taxable base (1000),
    // not 10% of the post-discount base (900).
    $totals = $service->calculate($parent, 1000, 0, 0, 'flat', 100, 0, 10);

    expect($totals['vat_amount'])->toBe(100.0);
    expect($totals['return_invoice_discount'])->toBe(100.0);
    expect($totals['discount_amount'])->toBe(100.0);
    // net = (gross - discount) + vat = (1000 - 100) + 100 = 1000
    expect($totals['net_return_amount'])->toBe(1000.0);
});

test('percent invoice discount is computed on the taxable base', function () {
    $service = app(SaleReturnDiscountService::class);
    $parent = saleReturnParentSell();

    $totals = $service->calculate($parent, 1000, 0, 0, 'percent', 10, 0, 0);

    expect($totals['return_invoice_discount'])->toBe(100.0);
    expect($totals['net_return_amount'])->toBe(900.0);
});

test('clearing invoice discount and round off yields zero discount', function () {
    $service = app(SaleReturnDiscountService::class);
    $parent = saleReturnParentSell(['discount' => 50, 'round_off_amount' => 10]);

    // Manual zero values override the source-sale amounts (the user deleted them).
    $totals = $service->calculate($parent, 1000, 0, 0, 'flat', 0, 0, 0);

    expect($totals['return_invoice_discount'])->toBe(0.0);
    expect($totals['return_round_off'])->toBe(0.0);
    expect($totals['discount_amount'])->toBe(0.0);
    expect($totals['net_return_amount'])->toBe(1000.0);
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
