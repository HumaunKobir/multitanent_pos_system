<?php

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function sellUser(): User
{
    return User::factory()->create();
}

function sellProduct(float $available = 20, ?int $branchId = null): array
{
    $product = Product::factory()->create(['branch_id' => $branchId]);
    $batch = Batch::factory()->for($product)->withStock($available)->create(['branch_id' => $branchId]);

    return compact('product', 'batch');
}

// ── Index ─────────────────────────────────────────────────────────────────────

test('guests are redirected from sell index', function () {
    $this->get('/inventory/sell')->assertRedirect(route('login'));
});

test('authenticated user can view sell index', function () {
    $user = sellUser();

    $this->actingAs($user)
        ->get('/inventory/sell')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/index')->has('sells'));
});

// ── Create ────────────────────────────────────────────────────────────────────

test('authenticated user can view sell create form', function () {
    $user = sellUser();

    $this->actingAs($user)
        ->get('/inventory/sell/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/create')->has('today'));
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('authenticated user can create a sale and stock is deducted', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
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
        ]);

    $sell = Sell::query()->latest('id')->first();
    expect($sell)->not->toBeNull();

    $response->assertRedirect(route('inventory.sell.show', $sell).'?pos_print=1');

    expect(SellProduct::where('sell_id', $sell->id)->count())->toBe(1);

    $sell->refresh();
    expect((float) $sell->gross_amount)->toBe(2500.0);
    expect((float) $sell->paid_amount)->toBe(500.0);

    $batch->refresh();
    expect((float) $batch->available)->toBe(15.0);
});

test('store fails when stock is insufficient', function () {
    $user = sellUser();
    ['product' => $product] = sellProduct(2, $user->branch_id);

    $sellProductCount = SellProduct::query()->where('product_id', $product->id)->count();

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '0',
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '10',
                ],
            ],
        ])
        ->assertServerError();

    expect(SellProduct::query()->where('product_id', $product->id)->count())->toBe($sellProductCount);
});

test('store requires at least one item', function () {
    $user = sellUser();

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '0',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('authenticated user can view a sale', function () {
    $user = sellUser();
    $sell = Sell::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/show')->has('sell'));
});

// ── Edit ──────────────────────────────────────────────────────────────────────

test('authenticated user can view the edit form', function () {
    $user = sellUser();
    $sell = Sell::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/edit')->has('sell'));
});

// ── Update ────────────────────────────────────────────────────────────────────

test('authenticated user can update a sale and stock is adjusted', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', [
        'customer_id' => null,
        'date' => now()->format('Y-m-d'),
        'discount' => '0',
        'vat' => '0',
        'paid_amount' => '1000',
        'payment_account_id' => $cash->id,
        'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '2']],
    ])->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(18.0);

    $sell = Sell::query()->latest('id')->first();
    expect($sell)->not->toBeNull();

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '3']],
        ])
        ->assertRedirect('/inventory/sell');

    $batch->refresh();
    // Old qty (2) rolled back → 20; new qty (3) deducted → 17
    expect((float) $batch->available)->toBe(17.0);

    $sell->refresh();
    expect((float) $sell->gross_amount)->toBe(1500.0);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('authenticated user can delete a sale and stock is restored', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', [
        'customer_id' => null,
        'date' => now()->format('Y-m-d'),
        'discount' => '0',
        'vat' => '0',
        'paid_amount' => '500',
        'payment_account_id' => $cash->id,
        'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '4']],
    ])->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(16.0);

    $sell = Sell::query()->latest('id')->first();
    expect($sell)->not->toBeNull();

    $this->actingAs($user)
        ->delete("/inventory/sell/{$sell->id}")
        ->assertRedirect('/inventory/sell');

    expect(Sell::find($sell->id))->toBeNull();

    $batch->refresh();
    expect((float) $batch->available)->toBe(20.0);
});
