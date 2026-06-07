<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

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

test('authenticated user can create a sale with per-line product discount', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(10, $user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '900',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '2',
                    'discount' => '100',
                ],
            ],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();
    $line = SellProduct::query()->where('sell_id', $sell->id)->first();

    expect((float) $line->discount)->toBe(100.0);
    expect((float) $sell->gross_amount)->toBe(1000.0);
    expect((float) $sell->net_amount)->toBe(900.0);

    $batch->refresh();
    expect((float) $batch->available)->toBe(8.0);
});

test('sale fails when quantity exceeds available stock', function () {
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
        ->assertSessionHasErrors('items');

    expect(SellProduct::query()->where('product_id', $product->id)->count())->toBe($sellProductCount);
});

test('main branch user can sell products with stock at main branch', function () {
    $this->artisan('permissions:sync');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');

    ['product' => $product, 'batch' => $batch] = sellProduct(15, Branch::MAIN_BRANCH_ID);

    $response = $this->actingAs($user)
        ->getJson('/api/products/for-sell?search='.$product->name);

    $response->assertOk();
    $match = collect($response->json())->firstWhere('id', $product->id);
    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(15.0);

    $cash = seedAccountingAccounts();

    $this->actingAs($user)
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
                    'quantity' => '2',
                ],
            ],
        ])
        ->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(13.0);
});

test('purchase stores stock on product branch and branch user can sell it', function () {
    $this->artisan('permissions:sync');

    $mainBranch = Branch::factory()->create();
    $targetBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $mainBranch->id]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $admin->givePermissionTo('inventory.purchase.create');

    $branchUser = User::factory()->create(['branch_id' => $targetBranch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $branchUser->givePermissionTo('inventory.sell.create');

    $cash = seedAccountingAccounts();
    $supplier = Supplier::factory()->create(['branch_id' => $mainBranch->id]);
    $product = Product::factory()->create(['branch_id' => $targetBranch->id]);

    $this->actingAs($admin)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '1000',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '1000',
                    'quantity' => '10',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $batch = Batch::query()->where('product_id', $product->id)->first();
    expect($batch)->not->toBeNull();
    expect($batch->branch_id)->toBe($targetBranch->id);
    expect((float) $batch->available)->toBe(10.0);

    $sellResponse = $this->actingAs($branchUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $sellResponse->assertOk();
    $match = collect($sellResponse->json())->firstWhere('id', $product->id);
    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(10.0);

    $this->actingAs($branchUser)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '2000',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '1000',
                    'quantity' => '2',
                ],
            ],
        ])
        ->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(8.0);
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
