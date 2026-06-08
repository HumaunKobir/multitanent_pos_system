<?php

use App\Enums\DiscountType;
use App\Enums\SaleType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Models\User;
use App\Services\InventoryAccountingService;
use App\Services\InventoryCostService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function specialDiscountUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('guests are redirected from special discount index', function () {
    $this->get('/setting/special-discount')->assertRedirect(route('login'));
});

test('authorized user can view special discount index', function () {
    $this->artisan('permissions:sync');

    $user = specialDiscountUser(['setting.special-discount.view']);

    $this->actingAs($user)
        ->get('/setting/special-discount')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/special-discount/index')
            ->has('specialDiscounts')
            ->has('discountTypes'));
});

test('authorized user can create a special discount', function () {
    $this->artisan('permissions:sync');

    $user = specialDiscountUser(['setting.special-discount.create']);

    $this->actingAs($user)
        ->post('/setting/special-discount', [
            'name' => 'Weekend Offer',
            'min_amount' => '2000',
            'max_amount' => '5000',
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '10',
            'status' => true,
        ])
        ->assertRedirect(route('setting.special-discount.index'));

    $discount = SpecialDiscount::query()->where('name', 'Weekend Offer')->first();

    expect($discount)->not->toBeNull();
    expect((float) $discount->min_amount)->toBe(2000.0);
    expect((float) $discount->max_amount)->toBe(5000.0);
    expect($discount->discount_type)->toBe(DiscountType::Percent);
});

test('sale automatically applies matching flat special discount', function () {
    $user = User::factory()->create();
    $cash = seedAccountingAccounts(user: $user);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $batch = Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $user->branch_id]);

    $specialDiscount = SpecialDiscount::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Spend 1000 Test '.uniqid(),
        'min_amount' => 1000,
        'discount_value' => 150,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => $specialDiscount->id,
            'vat' => '0',
            'paid_amount' => '2350',
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
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();

    expect((float) $sell->gross_amount)->toBe(2500.0);
    expect($sell->special_discount_id)->toBe($specialDiscount->id);
    expect((float) $sell->special_discount_amount)->toBe(150.0);
    expect((float) $sell->net_amount)->toBe(2350.0);

    $batch->refresh();
    expect((float) $batch->available)->toBe(15.0);
});

test('postSale journal balances when invoice discount is applied', function () {
    $user = User::factory()->create();
    $cash = seedAccountingAccounts(user: $user);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $batch = Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $user->branch_id]);

    $sell = Sell::query()->create([
        'branch_id' => $user->branch_id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 1000,
        'discount' => 100,
        'discount_type' => DiscountType::Flat,
        'discount_value' => 100,
        'special_discount_amount' => 0,
        'vat' => 0,
        'paid_amount' => 900,
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
        $cash->id,
        $cogs,
    );

    expect((float) $sell->net_amount)->toBe(900.0);
});

test('sale applies flat invoice discount', function () {
    $user = User::factory()->create();
    $cash = seedAccountingAccounts(user: $user);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '50',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '450',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $sell = Sell::query()->where('gross_amount', 500)->where('discount_value', 50)->latest('id')->first();

    expect($sell)->not->toBeNull();
    expect((float) $sell->discount)->toBe(50.0);
    expect((float) $sell->special_discount_amount)->toBe(0.0);
    expect((float) $sell->net_amount)->toBe(450.0);
});

test('sale applies percent invoice discount', function () {
    $user = User::factory()->create();
    $cash = seedAccountingAccounts(user: $user);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $user->branch_id]);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '10',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '450',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $sell = Sell::query()->where('gross_amount', 500)->where('discount_value', 10)->latest('id')->first();

    expect($sell)->not->toBeNull();
    expect((float) $sell->discount)->toBe(50.0);
    expect($sell->discount_type)->toBe(DiscountType::Percent);
    expect((float) $sell->discount_value)->toBe(10.0);
    expect((float) $sell->special_discount_amount)->toBe(0.0);
    expect((float) $sell->net_amount)->toBe(450.0);
});
