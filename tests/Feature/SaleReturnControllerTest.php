<?php

use App\Enums\PromotionScope;
use App\Enums\SaleType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function saleReturnUser(array $permissions = [
    'inventory.sale-return.view',
    'inventory.sale-return.create',
    'inventory.sale-return.update',
    'inventory.sale-return.delete',
]): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function saleReturnProduct(float $available = 20, ?int $branchId = null): array
{
    $product = Product::factory()->create(['branch_id' => $branchId]);
    $batch = Batch::factory()->for($product)->withStock($available)->create(['branch_id' => $branchId]);

    return compact('product', 'batch');
}

test('sale lookup includes payment and discount fields for returns', function () {
    $user = saleReturnUser();
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 50,
        'special_discount_amount' => 100,
        'coin_discount_amount' => 25,
        'round_off_amount' => 10,
        'vat' => 0,
        'paid_amount' => 300,
        'type' => SaleType::Sale,
    ]);

    SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'discount' => 30,
        'batches' => [(string) $batch->id => 2],
    ]);

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number)
        ->assertSuccessful()
        ->assertJsonPath('sell_discounts.paid_amount', 300)
        ->assertJsonPath('sell_discounts.net_amount', 785)
        ->assertJsonPath('sell_discounts.line_discount_total', 30)
        ->assertJsonPath('items.0.line_discount', 30);
});

test('partial paid sale return stores proportional discount and caps refund', function () {
    $user = saleReturnUser();
    seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 50,
        'round_off_amount' => 10,
        'vat' => 0,
        'paid_amount' => 300,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'discount' => 30,
        'batches' => [(string) $batch->id => 2],
    ]);

    $response = $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '300',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '2'],
            ],
        ]);

    $response->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->first();

    // discount_amount = line(30) + invoice(50) + round_off(10) = 90
    expect($saleReturn)->not->toBeNull();
    expect((float) $saleReturn->gross_amount)->toBe(1000.0);
    expect((float) $saleReturn->discount_amount)->toBe(90.0);
    expect((float) $saleReturn->net_amount)->toBe(910.0);
    expect((float) $saleReturn->paid_amount)->toBe(300.0);
});

test('due sale return stores zero refund for unpaid sale', function () {
    $user = saleReturnUser();
    seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 500,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 0,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'discount' => 0,
        'batches' => [(string) $batch->id => 1],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->first();

    expect((float) $saleReturn->paid_amount)->toBe(0.0);
    expect((float) $saleReturn->net_amount)->toBe(500.0);
});

test('sale return claws back promotion only when return qty meets the promotion min qty', function () {
    $user = saleReturnUser();
    seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->withMinQty(3)->create([
        'branch_id' => $user->branch_id,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 300,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'promotion_id' => $promotion->id,
        'quantity' => 3,
        'unit_price' => 100,
        'original_unit_price' => 100,
        'discount' => 0,
        'promotion_discount' => 30,
        'batches' => [(string) $batch->id => 3],
    ]);

    // Returning 2 items (< min_qty 3): no promotion clawback since return qty < min qty
    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '200',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $partialReturn = SaleReturn::query()->latest('id')->first();

    expect((float) $partialReturn->gross_amount)->toBe(200.0);
    expect((float) $partialReturn->discount_amount)->toBe(0.0);
    expect((float) $partialReturn->net_amount)->toBe(200.0);

    // Returning all 3 items (= min_qty 3): full promotion clawback
    $sell2 = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 300,
        'type' => SaleType::Sale,
    ]);

    $sellProduct2 = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell2->id,
        'product_id' => $product->id,
        'promotion_id' => $promotion->id,
        'quantity' => 3,
        'unit_price' => 100,
        'original_unit_price' => 100,
        'discount' => 0,
        'promotion_discount' => 30,
        'batches' => [(string) $batch->id => 3],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell2->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '170',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProduct2->id, 'quantity' => '3'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $fullReturn = SaleReturn::query()->latest('id')->first();

    expect((float) $fullReturn->gross_amount)->toBe(300.0);
    expect((float) $fullReturn->discount_amount)->toBe(30.0);
    expect((float) $fullReturn->net_amount)->toBe(270.0);
});

test('sale return net amount equals gross when sale had no invoice-level discounts', function () {
    $user = saleReturnUser();
    $branch = Branch::factory()->create();
    seedAccountingAccounts(user: $user, branchId: $branch->id);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $branch->id);

    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'original_unit_price' => 500,
        'discount' => 0,
        'batches' => [(string) $batch->id => 2],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '500',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->first();

    expect((float) $saleReturn->gross_amount)->toBe(500.0);
    expect((float) $saleReturn->discount_amount)->toBe(0.0);
    expect((float) $saleReturn->net_amount)->toBe(500.0);
});
