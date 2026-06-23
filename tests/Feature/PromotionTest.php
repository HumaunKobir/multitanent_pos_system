<?php

use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Enums\SaleType;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Sell;
use App\Models\User;
use App\Services\PromotionService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function promotionUser(array $permissions = []): User
{
    $user = User::factory()->create();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
        $user->givePermissionTo($permission);
    }

    return $user;
}

function promotionSellUser(): User
{
    return promotionUser(['inventory.sell.create']);
}

test('guests are redirected from promotion index', function () {
    $this->get('/setting/promotion')->assertRedirect(route('login'));
});

test('authorized user can view promotion index', function () {
    $this->artisan('permissions:sync');

    $user = promotionUser(['setting.promotion.view']);

    $this->actingAs($user)
        ->get('/setting/promotion')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/promotion/index')
            ->has('promotions')
            ->has('promotionScopes')
            ->has('promotionTypes'));
});

test('authorized user can create a product promotion', function () {
    $this->artisan('permissions:sync');

    $user = promotionUser(['setting.promotion.create']);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $promotionName = 'Summer Sale '.uniqid();

    $this->actingAs($user)
        ->post('/setting/promotion', [
            'name' => $promotionName,
            'description' => '10% off selected product',
            'scope' => PromotionScope::Product->value,
            'type' => PromotionType::Percent->value,
            'discount_value' => '10',
            'target_ids' => [$product->id],
            'min_qty' => '1',
            'status' => true,
            'priority' => 1,
            'stack_with_product_discount' => true,
            'stack_with_manual_line_discount' => true,
            'stack_with_invoice_discount' => true,
            'stack_with_special_discount' => true,
            'exclusive' => false,
        ])
        ->assertRedirect(route('setting.promotion.index'));

    $promotion = Promotion::query()->where('name', $promotionName)->first();

    expect($promotion)->not->toBeNull();
    expect($promotion->scope)->toBe(PromotionScope::Product);
    expect($promotion->targets)->toHaveCount(1);
    expect((int) $promotion->targets->first()->target_id)->toBe($product->id);
});

test('percent promotion accepts empty bundle product ids from client payload', function () {
    $this->artisan('permissions:sync');

    $user = promotionUser(['setting.promotion.create']);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $promotionName = 'Percent With Empty Bundle '.uniqid();

    $this->actingAs($user)
        ->post('/setting/promotion', [
            'name' => $promotionName,
            'scope' => PromotionScope::Product->value,
            'type' => PromotionType::Percent->value,
            'discount_value' => '5',
            'target_ids' => [$product->id],
            'bundle_product_ids' => [],
            'min_qty' => '1',
            'status' => true,
            'priority' => 0,
            'stack_with_product_discount' => true,
            'stack_with_manual_line_discount' => true,
            'stack_with_invoice_discount' => true,
            'stack_with_special_discount' => true,
            'exclusive' => false,
        ])
        ->assertRedirect(route('setting.promotion.index'));

    expect(Promotion::query()->where('name', $promotionName)->exists())->toBeTrue();
});

test('inertia promotion create validation returns 422 when targets are missing', function () {
    $this->artisan('permissions:sync');

    $user = promotionUser(['setting.promotion.create']);

    $this->actingAs($user)
        ->from('/setting/promotion')
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post('/setting/promotion', [
            'name' => 'Invalid Promotion',
            'scope' => PromotionScope::Product->value,
            'type' => PromotionType::Percent->value,
            'discount_value' => '10',
            'target_ids' => [],
            'min_qty' => '1',
            'status' => true,
            'priority' => 0,
            'stack_with_product_discount' => true,
            'stack_with_manual_line_discount' => true,
            'stack_with_invoice_discount' => true,
            'stack_with_special_discount' => true,
            'exclusive' => false,
        ])
        ->assertRedirect('/setting/promotion')
        ->assertSessionHasErrors(['target_ids']);
});

test('promotion service applies percent discount to matching product', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'category_id' => $category->id,
        'sale_price' => 1000,
        'discount_price' => 0,
    ]);

    $promotion = Promotion::factory()->percent(10)->forCategory()->create([
        'branch_id' => $user->branch_id,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Category->value,
        'target_id' => $category->id,
    ]);

    $service = app(PromotionService::class);
    $result = $service->applyToCart([
        [
            'product_id' => $product->id,
            'category_id' => $category->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'discount' => 0,
        ],
    ], $user->branch_id);

    expect((float) $result['items'][0]['unit_price'])->toBe(900.0);
    expect((float) $result['promotion_discount_total'])->toBe(200.0);
    expect($result['items'][0]['promotion_id'])->toBe($promotion->id);
});

test('promotion without min qty applies from the first unit', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 1000,
        'discount_price' => 0,
    ]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->create([
        'branch_id' => $user->branch_id,
        'min_qty' => null,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $service = app(PromotionService::class);
    $result = $service->applyToCart([
        [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'discount' => 0,
        ],
    ], $user->branch_id);

    expect((float) $result['items'][0]['unit_price'])->toBe(900.0);
    expect($result['items'][0]['promotion_id'])->toBe($promotion->id);
});

test('promotion min qty blocks discount below threshold', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 1000,
        'discount_price' => 0,
    ]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->withMinQty(5)->create([
        'branch_id' => $user->branch_id,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $service = app(PromotionService::class);
    $result = $service->applyToCart([
        [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'discount' => 0,
        ],
    ], $user->branch_id);

    expect($result['items'][0]['promotion_id'])->toBeNull();
    expect((float) $result['items'][0]['unit_price'])->toBe(1000.0);
});

test('buy x get y grants stacked free units until the next buy tier', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 100,
        'discount_price' => 0,
    ]);

    $promotion = Promotion::factory()->forProduct()->create([
        'branch_id' => $user->branch_id,
        'type' => PromotionType::BuyXGetY,
        'buy_qty' => 5,
        'get_qty' => 1,
        'get_discount_percent' => 100,
        'min_qty' => null,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $service = app(PromotionService::class);

    foreach ([5, 6, 7, 8, 9] as $paidQty) {
        $result = $service->applyToCart([
            [
                'product_id' => $product->id,
                'quantity' => $paidQty,
                'unit_price' => 100,
                'discount' => 0,
            ],
        ], $user->branch_id);

        expect($result['items'][0]['promotion_id'])->toBe($promotion->id);
        expect((float) $result['items'][0]['quantity'])->toBe((float) $paidQty);
        expect((float) $result['items'][0]['free_quantity'])->toBe(1.0);
    }

    $tenQty = $service->applyToCart([
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'discount' => 0,
        ],
    ], $user->branch_id);

    expect($tenQty['items'][0]['promotion_id'])->toBe($promotion->id);
    expect((float) $tenQty['items'][0]['free_quantity'])->toBe(2.0);
});

test('higher priority buy tier promotion replaces lower buy x get y offer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 100,
        'discount_price' => 0,
    ]);

    $buyFive = Promotion::factory()->forProduct()->create([
        'branch_id' => $user->branch_id,
        'type' => PromotionType::BuyXGetY,
        'buy_qty' => 5,
        'get_qty' => 1,
        'priority' => 0,
    ]);

    $buyTen = Promotion::factory()->forProduct()->create([
        'branch_id' => $user->branch_id,
        'type' => PromotionType::BuyXGetY,
        'buy_qty' => 10,
        'get_qty' => 2,
        'priority' => 5,
    ]);

    foreach ([$buyFive, $buyTen] as $promotion) {
        PromotionTarget::create([
            'promotion_id' => $promotion->id,
            'target_type' => PromotionScope::Product->value,
            'target_id' => $product->id,
        ]);
    }

    $service = app(PromotionService::class);
    $result = $service->applyToCart([
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'discount' => 0,
        ],
    ], $user->branch_id);

    expect($result['items'][0]['promotion_id'])->toBe($buyTen->id);
    expect((float) $result['items'][0]['free_quantity'])->toBe(2.0);
});

test('line promotion with higher priority blocks buy x get y free units', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 100,
        'discount_price' => 0,
    ]);

    $bogo = Promotion::factory()->forProduct()->create([
        'branch_id' => $user->branch_id,
        'type' => PromotionType::BuyXGetY,
        'buy_qty' => 5,
        'get_qty' => 1,
        'priority' => 0,
    ]);

    $percent = Promotion::factory()->percent(10)->forProduct()->withMinQty(10)->create([
        'branch_id' => $user->branch_id,
        'priority' => 5,
    ]);

    foreach ([$bogo, $percent] as $promotion) {
        PromotionTarget::create([
            'promotion_id' => $promotion->id,
            'target_type' => PromotionScope::Product->value,
            'target_id' => $product->id,
        ]);
    }

    $service = app(PromotionService::class);
    $result = $service->applyToCart([
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
            'discount' => 0,
        ],
    ], $user->branch_id);

    expect($result['items'][0]['promotion_id'])->toBe($percent->id);
    expect((float) $result['items'][0]['free_quantity'])->toBe(0.0);
});

test('pos sale applies branch product promotion server side', function () {
    $this->artisan('permissions:sync');

    $user = promotionSellUser();
    $cash = seedAccountingAccounts(user: $user);
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 500,
        'discount_price' => 0,
    ]);
    $batch = Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $user->branch_id]);

    $promotion = Promotion::factory()->percent(20)->forProduct()->create([
        'branch_id' => $user->branch_id,
        'name' => 'POS Promo '.uniqid(),
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '800',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '2',
                ],
            ],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();

    expect((float) $sell->gross_amount)->toBe(800.0);
    expect((float) $sell->promotion_discount_total)->toBe(200.0);
    expect((float) $sell->net_amount)->toBe(800.0);
    expect($sell->products)->toHaveCount(1);
    expect((float) $sell->products->first()->unit_price)->toBe(400.0);
    expect($sell->products->first()->promotion_id)->toBe($promotion->id);

    $batch->refresh();
    expect((float) $batch->available)->toBe(18.0);
});

test('sale blocks invoice discount when promotion stacking disallows it', function () {
    $this->artisan('permissions:sync');

    $user = promotionSellUser();
    seedAccountingAccounts(user: $user);
    $product = Product::factory()->create([
        'branch_id' => $user->branch_id,
        'sale_price' => 500,
    ]);
    Batch::factory()->for($product)->withStock(20)->create(['branch_id' => $user->branch_id]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->create([
        'branch_id' => $user->branch_id,
        'stack_with_invoice_discount' => false,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $sellCountBefore = Sell::query()->where('type', SaleType::Sale)->count();

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '50',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '400',
            'payment_account_id' => seedAccountingAccounts(user: $user)->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasErrors('discount_value');

    expect(Sell::query()->where('type', SaleType::Sale)->count())->toBe($sellCountBefore);
});

test('promotion with recorded usage cannot be deleted', function () {
    $this->artisan('permissions:sync');

    $user = promotionUser(['setting.promotion.delete', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 100]);
    Batch::factory()->for($product)->withStock(5)->create(['branch_id' => $user->branch_id]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->create([
        'branch_id' => $user->branch_id,
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'vat' => '0',
            'paid_amount' => '90',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->delete('/setting/promotion/'.$promotion->id)
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Promotion::query()->whereKey($promotion->id)->exists())->toBeTrue();
});
