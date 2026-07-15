<?php

use App\Enums\PromotionScope;
use App\Enums\ReceivedPaymentMethod;
use App\Enums\SaleType;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerCoinTransaction;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\SaleReturn;
use App\Models\SaleReturnPayment;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function saleReturnUser(array $permissions = [
    'inventory.sale-return.view',
    'inventory.sale-return.create',
    'inventory.sale-return.update',
    'inventory.sale-return.delete',
]): User
{
    $user = User::factory()->create();
    $branch = Branch::factory()->create();
    $user->update(['branch_id' => $branch->id]);

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

/**
 * @return array{payment_type: string, payment_account_id: int, payments: list<array{payment_account_id: int, amount: string}>, paid_amount: string}
 */
function saleReturnCashPayment(ChartOfAccount $cash, float|string $amount): array
{
    $paid = (string) $amount;

    return [
        'paid_amount' => $paid,
        'payment_type' => (string) ReceivedPaymentMethod::Cash->value,
        'payment_account_id' => $cash->id,
        'payments' => [
            [
                'payment_account_id' => $cash->id,
                'amount' => $paid,
            ],
        ],
    ];
}

test('sale lookup shows remaining returnable quantities after a partial return', function () {
    $user = saleReturnUser();
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 500,
        'vat' => 0,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 1],
    ]);

    $saleReturn = SaleReturn::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 500,
        'due_amount' => 0,
        'payment_type' => 5,
    ]);

    $saleReturn->products()->create([
        'branch_id' => $user->branch_id,
        'sell_product_id' => $sellProduct->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This sale has been fully returned.');
});

test('sale lookup allows another return when quantities remain', function () {
    $user = saleReturnUser();
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 1000,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 2],
    ]);

    SaleReturn::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 500,
        'due_amount' => 0,
        'payment_type' => 5,
    ])->products()->create([
        'branch_id' => $user->branch_id,
        'sell_product_id' => $sellProduct->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number)
        ->assertOk()
        ->assertJsonPath('items.0.sold_quantity', 2)
        ->assertJsonPath('items.0.returned_quantity', 1)
        ->assertJsonPath('items.0.max_return_quantity', 1);
});

test('sale lookup for exchange is blocked when sale has a return', function () {
    $user = saleReturnUser([
        'inventory.sale-return.view',
        'inventory.sale-return.create',
        'inventory.product-exchange.create',
    ]);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 1000,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 2],
    ]);

    SaleReturn::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 0,
        'due_amount' => 500,
        'payment_type' => 5,
    ])->products()->create([
        'branch_id' => $user->branch_id,
        'sell_product_id' => $sellProduct->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number.'&for=exchange')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This sale has a sale return and cannot be exchanged.');

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number)
        ->assertOk()
        ->assertJsonPath('items.0.max_return_quantity', 1);
});

test('product exchange create is blocked when sale has a return', function () {
    $user = saleReturnUser([
        'inventory.sale-return.view',
        'inventory.sale-return.create',
        'inventory.product-exchange.create',
    ]);
    ['product' => $oldProduct, 'batch' => $oldBatch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 500,
        'vat' => 0,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $oldProduct->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [(string) $oldBatch->id => 1],
    ]);

    SaleReturn::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 0,
        'due_amount' => 500,
        'payment_type' => 5,
    ])->products()->create([
        'branch_id' => $user->branch_id,
        'sell_product_id' => $sellProduct->id,
        'product_id' => $oldProduct->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number.'&for=exchange')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This sale has a sale return and cannot be exchanged.');
});

test('can create second return for remaining products on the same sale', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 2],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '500'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '500'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    expect(SaleReturn::where('sell_id', $sell->id)->count())->toBe(2);
});

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
    $cash = seedAccountingAccounts(user: $user);
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
            ...saleReturnCashPayment($cash, '300'),
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

test('paid sale return requires a payment option', function () {
    $user = saleReturnUser();
    seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 500,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
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
        ->assertSessionHasErrors([
            'payments' => 'Select a payment option and enter the refund amount.',
        ]);

    expect(SaleReturn::query()->where('sell_id', $sell->id)->exists())->toBeFalse();
});

test('due sale return from unpaid sale still requires a payment option', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
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
        ->assertSessionHasErrors([
            'payments' => 'Select a payment option and enter the refund amount.',
        ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '500'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->first();

    expect((float) $saleReturn->paid_amount)->toBe(500.0);
    expect((float) $saleReturn->net_amount)->toBe(500.0);
});

test('sale return claws back promotion only when return qty meets the promotion min qty', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
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
            ...saleReturnCashPayment($cash, '200'),
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
            ...saleReturnCashPayment($cash, '170'),
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
    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
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
            ...saleReturnCashPayment($cash, '500'),
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

test('sale return reverses customer coin balance proportionally', function () {
    $branch = Branch::factory()->create();
    $user = saleReturnUser();
    $user->update(['branch_id' => $branch->id]);
    $cash = seedAccountingAccounts(user: $user, branchId: $branch->id);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $branch->id);
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 35,
        'is_default' => false,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 1000,
        'coins_redeemed' => 20,
        'coins_earned' => 5,
        'coin_discount_amount' => 20,
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

    $response = $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '500'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ]);

    $response->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->first();

    $customer->refresh();

    expect((float) $customer->point)->toBe(42.5);
    expect(CustomerCoinTransaction::query()
        ->where('sell_id', $sell->id)
        ->where('meta->sale_return_id', $saleReturn->id)
        ->count())->toBe(2);
});

test('deleting a sale return restores customer coin balance', function () {
    $branch = Branch::factory()->create();
    $user = saleReturnUser();
    $user->update(['branch_id' => $branch->id]);
    $cash = seedAccountingAccounts(user: $user, branchId: $branch->id);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $branch->id);
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 35,
        'is_default' => false,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 500,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 500,
        'coins_redeemed' => 20,
        'coins_earned' => 5,
        'coin_discount_amount' => 20,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'original_unit_price' => 500,
        'discount' => 0,
        'batches' => [(string) $batch->id => 1],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '500'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->first();
    $customer->refresh();

    expect((float) $customer->point)->toBe(50.0);

    $this->actingAs($user)
        ->delete(route('inventory.sale-return.destroy', $saleReturn))
        ->assertRedirect(route('inventory.sale-return.index'));

    $customer->refresh();

    expect((float) $customer->point)->toBe(35.0);
});

/**
 * @return array{cash_debit: float, cash_credit: float, ar_debit: float, ar_credit: float}
 */
function saleReturnLedgerTotals(SaleReturn $saleReturn, ChartOfAccount $cash): array
{
    $transactionIds = Transaction::query()
        ->where(function ($query) use ($saleReturn) {
            $query->where(function ($inner) use ($saleReturn) {
                $inner->where('source_type', SaleReturn::class)
                    ->where('source_id', $saleReturn->id);
            })->orWhere(function ($inner) use ($saleReturn) {
                $inner->where('source_type', SaleReturnPayment::class)
                    ->whereIn('source_id', $saleReturn->payments()->pluck('id'));
            });
        })
        ->pluck('id');

    $ledgers = Ledger::query()->whereIn('transaction_id', $transactionIds)->get();
    $receivablesId = SystemAccountService::id(SystemAccountKey::CustomerReceivables, $saleReturn->branch_id);

    return [
        'cash_debit' => round($ledgers->where('account_id', $cash->id)->sum(fn (Ledger $line) => (float) $line->debit), 2),
        'cash_credit' => round($ledgers->where('account_id', $cash->id)->sum(fn (Ledger $line) => (float) $line->credit), 2),
        'ar_debit' => round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->debit), 2),
        'ar_credit' => round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->credit), 2),
    ];
}

function createCashSaleReturn(User $user, ChartOfAccount $cash, Customer $customer, array $productData, float $refundAmount): SaleReturn
{
    ['product' => $product, 'batch' => $batch] = $productData;

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 2],
    ]);

    test()->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => (string) $refundAmount,
            'payment_type' => (string) ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'payments' => [
                [
                    'payment_account_id' => $cash->id,
                    'amount' => (string) $refundAmount,
                ],
            ],
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.sale-return.index'));

    return SaleReturn::query()->latest('id')->firstOrFail();
}

test('partial cash refund stores due amount and remains fully editable', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    $productData = saleReturnProduct(10, $user->branch_id);

    $saleReturn = createCashSaleReturn($user, $cash, $customer, $productData, 300);

    expect((float) $saleReturn->net_amount)->toBe(1000.0);
    expect((float) $saleReturn->paid_amount)->toBe(300.0);
    expect((float) $saleReturn->due_amount)->toBe(700.0);
    expect($saleReturn->payment_type)->toBe(ReceivedPaymentMethod::Cash);
    expect($saleReturn->fresh()->isEditable())->toBeTrue();
    expect($saleReturn->fresh()->canAccessEdit())->toBeTrue();

    $this->actingAs($user)
        ->get(route('inventory.sale-return.edit', $saleReturn))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('paymentOnlyEdit', false));
});

test('incremental sale return refund updates due and posts ledger entries', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);
    $productData = saleReturnProduct(10, $user->branch_id);

    $saleReturn = createCashSaleReturn($user, $cash, $customer, $productData, 300);
    $balanceAfterReturn = (float) $customer->fresh()->balance;

    $this->actingAs($user)
        ->put(route('inventory.sale-return.refund', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            'amount' => '400',
            'payment_account_id' => $cash->id,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $saleReturn->refresh();

    expect((float) $saleReturn->paid_amount)->toBe(700.0);
    expect((float) $saleReturn->due_amount)->toBe(300.0);

    $totals = saleReturnLedgerTotals($saleReturn, $cash);
    expect($totals['cash_credit'])->toBe(700.0);
    expect($totals['ar_debit'])->toBe(400.0);
    expect($totals['ar_credit'])->toBe(700.0);
    expect((float) $customer->fresh()->balance)->toBe(round($balanceAfterReturn + 400.0, 2));

    $this->actingAs($user)
        ->put(route('inventory.sale-return.refund', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            'amount' => '300',
            'payment_account_id' => $cash->id,
        ])
        ->assertSessionDoesntHaveErrors();

    $saleReturn->refresh();

    expect((float) $saleReturn->paid_amount)->toBe(1000.0);
    expect((float) $saleReturn->due_amount)->toBe(0.0);
    expect($saleReturn->fresh()->isFullyRefunded())->toBeTrue();

    $totals = saleReturnLedgerTotals($saleReturn->fresh(), $cash);
    expect($totals['cash_credit'])->toBe(1000.0);
    expect($totals['ar_debit'])->toBe(700.0);
});

test('customer account sale return can receive incremental cash refund from list', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 500,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
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
        ->assertSessionHasErrors(['payments']);

    $saleReturn = SaleReturn::query()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'customer_id' => $customer->id,
        'date' => now(),
        'gross_amount' => 500,
        'vat_amount' => 0,
        'discount_amount' => 0,
        'paid_amount' => 0,
        'due_amount' => 500,
        'payment_type' => ReceivedPaymentMethod::Customer_Account,
    ]);

    $saleReturn->products()->create([
        'branch_id' => $user->branch_id,
        'sell_product_id' => $sellProduct->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 1],
    ]);

    expect((float) $saleReturn->due_amount)->toBe(500.0);
    expect($saleReturn->fresh()->isEditable())->toBeTrue();

    $balanceBefore = (float) $customer->fresh()->balance;

    $this->actingAs($user)
        ->put(route('inventory.sale-return.refund', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            'amount' => '500',
            'payment_account_id' => $cash->id,
        ])
        ->assertSessionDoesntHaveErrors();

    $saleReturn->refresh();

    expect((float) $saleReturn->paid_amount)->toBe(500.0);
    expect((float) $saleReturn->due_amount)->toBe(0.0);
    expect($saleReturn->payment_type)->toBe(ReceivedPaymentMethod::Cash);
    expect((float) $customer->fresh()->balance)->toBe(round($balanceBefore + 500.0, 2));

    $totals = saleReturnLedgerTotals($saleReturn, $cash);
    expect($totals['cash_credit'])->toBe(500.0);
    expect($totals['ar_debit'])->toBe(500.0);
});

test('sale return edit includes returned elsewhere and available quantities per line', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 2000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 2000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 1000,
        'batches' => [(string) $batch->id => 2],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '1000'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $firstReturn = SaleReturn::query()->latest('id')->firstOrFail();

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '1000'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $secondReturn = SaleReturn::query()->latest('id')->firstOrFail();

    $this->actingAs($user)
        ->get(route('inventory.sale-return.edit', $firstReturn))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('saleReturn.items.0.sold_quantity', 2)
            ->where('saleReturn.items.0.returned_elsewhere', 1)
            ->where('saleReturn.items.0.returned_quantity', 2)
            ->where('saleReturn.items.0.available_quantity', 0)
            ->where('saleReturn.items.0.quantity', '1')
        );

    $this->actingAs($user)
        ->get(route('inventory.sale-return.edit', $secondReturn))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('saleReturn.items.0.sold_quantity', 2)
            ->where('saleReturn.items.0.returned_elsewhere', 1)
            ->where('saleReturn.items.0.returned_quantity', 2)
            ->where('saleReturn.items.0.available_quantity', 0)
            ->where('saleReturn.items.0.quantity', '1')
        );
});

test('sale return update with unchanged variation qty does not require stock rollback', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'branch_id' => $user->branch_id,
        'sku' => 'SR-VAR-'.fake()->unique()->numerify('####'),
        'variation_data' => ['Color' => 'Blue', 'Size' => 'L', 'label' => 'Blue-L'],
        'price' => 950,
        'purchase_price' => 500,
        'stock' => 0,
        'status' => 1,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 950,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 950,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 950,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '950'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->firstOrFail();
    $variation->refresh();

    expect((float) $variation->stock)->toBe(1.0);

    // Simulate the returned unit being sold again so variation stock is back to zero.
    $variation->update(['stock' => 0]);

    $this->actingAs($user)
        ->get(route('inventory.sale-return.edit', $saleReturn))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('saleReturn.items.0.variation_label', 'Blue-L')
        );

    $this->actingAs($user)
        ->put(route('inventory.sale-return.update', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '900'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn->refresh();

    expect((float) $saleReturn->paid_amount)->toBe(900.0);
    expect((float) $variation->fresh()->stock)->toBe(0.0);
});

test('sale return edit requires payment option and rejects clearing refund to zero', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 1000,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 2],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '1000'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->firstOrFail();

    expect($saleReturn->products)->toHaveCount(1);
    expect((float) $saleReturn->paid_amount)->toBe(1000.0);
    expect((float) $saleReturn->due_amount)->toBe(0.0);

    $this->actingAs($user)
        ->put(route('inventory.sale-return.update', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertSessionHasErrors(['payments']);

    $saleReturn->refresh();

    expect($saleReturn->products)->toHaveCount(1);
    expect((float) $saleReturn->products->first()->quantity)->toBe(2.0);
    expect((float) $saleReturn->paid_amount)->toBe(1000.0);
    expect((float) $saleReturn->due_amount)->toBe(0.0);
});

test('sale return create and edit reject missing payment option on paid sales', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'branch_id' => $user->branch_id,
        'sku' => 'SR-VAR-'.fake()->unique()->numerify('####'),
        'variation_data' => ['Color' => 'Red', 'Size' => 'M', 'label' => 'Red-M'],
        'price' => 775,
        'purchase_price' => 400,
        'stock' => 0,
        'status' => 1,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 1550,
        'discount' => 110,
        'vat' => 0,
        'paid_amount' => 1550,
        'type' => SaleType::Sale,
    ]);

    $sellProductA = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 775,
        'batches' => [],
    ]);

    $sellProductB = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 775,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '0'),
            'items' => [
                ['sell_product_id' => $sellProductA->id, 'quantity' => '1'],
                ['sell_product_id' => $sellProductB->id, 'quantity' => '1'],
            ],
        ])
        ->assertSessionHasErrors(['payments']);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '1440'),
            'items' => [
                ['sell_product_id' => $sellProductA->id, 'quantity' => '1'],
                ['sell_product_id' => $sellProductB->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->firstOrFail();

    expect($saleReturn->products)->toHaveCount(2);
    expect((float) $saleReturn->paid_amount)->toBe(1440.0);
    expect((float) $saleReturn->due_amount)->toBe(0.0);

    $this->actingAs($user)
        ->put(route('inventory.sale-return.update', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => '5',
            'items' => [
                ['sell_product_id' => $sellProductA->id, 'quantity' => '1'],
                ['sell_product_id' => $sellProductB->id, 'quantity' => '1'],
            ],
        ])
        ->assertSessionHasErrors(['payments']);

    $saleReturn->refresh();

    expect($saleReturn->products)->toHaveCount(2);
    expect((float) $saleReturn->paid_amount)->toBe(1440.0);
    expect((float) $saleReturn->due_amount)->toBe(0.0);
});

test('sale return payment-only update preserves product lines and stock', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    ['product' => $product, 'batch' => $batch] = saleReturnProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 500,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 500,
        'batches' => [(string) $batch->id => 1],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '500'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->firstOrFail();
    $stockBefore = (float) $batch->fresh()->available;

    $this->actingAs($user)
        ->put(route('inventory.sale-return.update', $saleReturn), [
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '0'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertSessionHasErrors(['payments']);

    $saleReturn->refresh();

    expect($saleReturn->products)->toHaveCount(1);
    expect((float) $saleReturn->products->first()->quantity)->toBe(1.0);
    expect((float) $batch->fresh()->available)->toBe($stockBefore);
});

test('sale return delete is blocked when variation stock is insufficient', function () {
    $user = saleReturnUser();
    $cash = seedAccountingAccounts(user: $user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'branch_id' => $user->branch_id,
        'sku' => 'SR-DEL-'.fake()->unique()->numerify('####'),
        'variation_data' => ['Color' => 'Green', 'Size' => 'S', 'label' => 'Green-S'],
        'price' => 950,
        'purchase_price' => 500,
        'stock' => 0,
        'status' => 1,
    ]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'gross_amount' => 950,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 950,
        'type' => SaleType::Sale,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 950,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->post('/inventory/sale-return', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            ...saleReturnCashPayment($cash, '950'),
            'items' => [
                ['sell_product_id' => $sellProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.sale-return.index'));

    $saleReturn = SaleReturn::query()->latest('id')->firstOrFail();

    expect((float) $variation->fresh()->stock)->toBe(1.0);

    $variation->decrement('stock', 1);

    expect((float) $variation->fresh()->stock)->toBe(0.0);

    $saleReturn->load('products');
    expect($saleReturn->products)->toHaveCount(1);
    expect($saleReturn->products()->count())->toBe(1);
    expect((float) $saleReturn->gross_amount)->toBeGreaterThan(0);
    expect($saleReturn->products->first()->variation_id)->toBe($variation->id);

    $this->actingAs($user)
        ->from(route('inventory.sale-return.index'))
        ->delete(route('inventory.sale-return.destroy', $saleReturn))
        ->assertRedirect(route('inventory.sale-return.index'))
        ->assertSessionHas('error', 'Insufficient stock for variation.');

    expect(SaleReturn::query()->find($saleReturn->id))->not->toBeNull();
    expect((float) $variation->fresh()->stock)->toBe(0.0);
});
