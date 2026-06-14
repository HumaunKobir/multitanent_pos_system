<?php

use App\Enums\DiscountType;
use App\Enums\ReceivedPaymentMethod;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Models\User;
use App\Services\InventoryAccountingService;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function productExchangeUser(array $permissions = ['inventory.product-exchange.create']): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
        $user->givePermissionTo($permission);
    }

    return $user;
}

function seedExchangeAccountingBalances(User $user, float $amount = 100000): void
{
    $accounting = app(InventoryAccountingService::class);
    $date = now()->format('Y-m-d');

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $user->branch_id);
    $accounting->postAccountOpeningBalance($inventory, $amount, $date);

    $salesReturns = SystemAccountService::resolve(SystemAccountKey::SalesReturns, $user->branch_id);
    $accounting->postAccountOpeningBalance($salesReturns, $amount, $date);
}

function createSaleWithSpecialDiscount(User $user, Product $product, SpecialDiscount $specialDiscount, ChartOfAccount $cash): Sell
{
    $grossAmount = 2500.0;
    $specialAmount = min(
        (float) $specialDiscount->discount_value,
        $grossAmount,
    );
    $netAmount = $grossAmount - $specialAmount;

    test()->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => $specialDiscount->id,
            'vat' => '0',
            'paid_amount' => (string) $netAmount,
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    return Sell::query()->latest('id')->firstOrFail();
}

test('sale with only special discount can be exchanged', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 600]);
    $newBatch = Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $specialDiscount = SpecialDiscount::factory()->create([
        'branch_id' => $user->branch_id,
        'min_amount' => 1000,
        'discount_value' => 150,
    ]);

    $sell = createSaleWithSpecialDiscount($user, $oldProduct, $specialDiscount, $cash);
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '350',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'special_discount_id' => $specialDiscount->id,
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '600',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange = ProductExchange::query()->latest('id')->first();

    expect($exchange)->not->toBeNull();
    expect((float) $exchange->gross_amount)->toBe(3000.0);
    expect($exchange->special_discount_id)->toBe($specialDiscount->id);
    expect((float) $exchange->special_discount_amount)->toBe(150.0);
    expect((float) $exchange->price_difference)->toBe(350.0);

    $newBatch->refresh();
    expect((float) $newBatch->available)->toBe(5.0);
});

test('product exchange auto applies matching special discount when id is omitted', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 600]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $specialDiscount = SpecialDiscount::factory()->create([
        'branch_id' => $user->branch_id,
        'min_amount' => 2000,
        'discount_value' => 200,
    ]);

    $sell = createSaleWithSpecialDiscount($user, $oldProduct, $specialDiscount, $cash);
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '300',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '600',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange = ProductExchange::query()->latest('id')->first();

    expect($exchange->special_discount_id)->toBe($specialDiscount->id);
    expect((float) $exchange->special_discount_amount)->toBe(200.0);
    expect((float) $exchange->price_difference)->toBe(300.0);
});

test('sale with manual invoice discount cannot be exchanged', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 600]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '100',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2400',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $oldProduct->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '500',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '600',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(ProductExchange::query()->where('sell_id', $sell->id)->exists())->toBeFalse();
});

test('sales lookup exposes manual discount flag separately from special discount', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $specialDiscount = SpecialDiscount::factory()->create(['branch_id' => $user->branch_id]);
    $sell = createSaleWithSpecialDiscount($user, $product, $specialDiscount, $cash);

    $this->actingAs($user)
        ->getJson('/api/sales/lookup?invoice='.$sell->invoice_number)
        ->assertOk()
        ->assertJson([
            'has_discount' => true,
            'has_manual_discount' => false,
        ]);
});

test('sell hasManualDiscount excludes special discount only sales', function () {
    $sell = Sell::factory()->make([
        'discount' => 0,
        'special_discount_amount' => 150,
    ]);

    expect($sell->hasAnyDiscount())->toBeTrue();
    expect($sell->hasManualDiscount())->toBeFalse();
});
