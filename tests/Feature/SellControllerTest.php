<?php

use App\Enums\SaleType;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellPayment;
use App\Models\SellProduct;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemAccountService;
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
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '5000',
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
    expect((float) $sell->paid_amount)->toBe((float) $sell->net_amount);

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
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
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

    $cash = seedAccountingAccounts();

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2000',
            'payment_account_id' => $cash->id,
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

test('authenticated user can pause a sale without deducting stock', function () {
    $user = sellUser();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell/pause', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'comment' => 'Customer will return',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.sell.create'))
        ->assertSessionHas('success');

    $paused = Sell::query()->latest('id')->first();

    expect($paused)->not->toBeNull();
    expect($paused->type)->toBe(SaleType::Paused);
    expect((float) $paused->gross_amount)->toBe(1500.0);
    expect(SellProduct::where('sell_id', $paused->id)->count())->toBe(1);

    $batch->refresh();
    expect((float) $batch->available)->toBe(20.0);
});

test('paused sale can be resumed and completed with stock deduction', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell/pause', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
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

    $paused = Sell::query()->latest('id')->first();

    $this->actingAs($user)
        ->get('/inventory/sell/create?paused='.$paused->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/sell/create')
            ->where('resumedSell.id', $paused->id)
            ->has('resumedSell.items', 1));

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '1500',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'paused_sell_id' => $paused->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertRedirect();

    $paused->refresh();
    expect($paused->type)->toBe(SaleType::Sale);
    expect((float) $paused->gross_amount)->toBe(1500.0);
    expect(SellProduct::where('sell_id', $paused->id)->count())->toBe(1);

    $batch->refresh();
    expect((float) $batch->available)->toBe(17.0);
});

test('paused sale can be deleted without restoring stock', function () {
    $user = sellUser();
    ['product' => $product, 'batch' => $batch] = sellProduct(10, $user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell/pause', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '100',
                    'quantity' => '2',
                ],
            ],
        ]);

    $paused = Sell::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete('/inventory/sell/'.$paused->id)
        ->assertRedirect(route('inventory.sell.create'));

    expect(Sell::query()->find($paused->id))->toBeNull();
    $batch->refresh();
    expect((float) $batch->available)->toBe(10.0);
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
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2000',
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

test('purchase stores stock in main warehouse until manually distributed', function () {
    $this->artisan('permissions:sync');

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $mainUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('inventory.purchase.create', 'web');
    $mainUser->givePermissionTo('inventory.purchase.create');
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $mainUser->givePermissionTo('inventory.stock-distribution.create');

    $targetBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $targetBranch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $branchUser->givePermissionTo('inventory.sell.create');

    $cash = seedAccountingAccounts();
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $product = Product::factory()->create(['branch_id' => $targetBranch->id]);

    $this->actingAs($mainUser)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
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
    expect($batch->branch_id)->toBe(Branch::MAIN_BRANCH_ID);
    expect((float) $batch->available)->toBe(10.0);

    $sellResponse = $this->actingAs($branchUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $sellResponse->assertOk();
    $match = collect($sellResponse->json())->firstWhere('id', $product->id);
    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(0.0);

    $this->actingAs($mainUser)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity' => '10',
                ],
            ],
        ])
        ->assertRedirect();

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
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
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

    $destinationBatch = Batch::query()
        ->where('product_id', $product->id)
        ->where('branch_id', $targetBranch->id)
        ->first();

    expect($destinationBatch)->not->toBeNull();
    expect((float) $destinationBatch->available)->toBe(8.0);
});

test('due sale records customer balance and receivable accounting', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    $balanceBefore = (float) $customer->balance;

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '200',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();
    $dueAmount = round((float) $sell->net_amount - (float) $sell->paid_amount, 2);

    expect((float) $sell->paid_amount)->toBe(200.0);
    expect($dueAmount)->toBeGreaterThan(0);

    $customer->refresh();
    expect(round((float) $customer->balance - $balanceBefore, 2))->toBe($dueAmount);

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $receivableAccountId = SystemAccountService::resolve(SystemAccountKey::CustomerReceivables, $user->branch_id)->id;

    expect(round($ledgers->where('account_id', $cash->id)->sum('debit'), 2))->toBe(200.0);
    expect(round($ledgers->where('account_id', $receivableAccountId)->sum('debit'), 2))->toBe($dueAmount);
    expect(round($ledgers->sum('debit'), 2))->toBe(round($ledgers->sum('credit'), 2));
});

test('due sale requires a registered customer', function () {
    $user = sellUser();
    seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $walkIn = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => true,
        'balance' => 0,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $walkIn->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '0',
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertSessionHasErrors('customer_id');
});

test('sale can be paid across multiple accounts with balanced accounting', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    $sslCommerz = SystemAccountService::resolve(SystemAccountKey::SslCommerz, $user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '100',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 60],
                ['payment_account_id' => $sslCommerz->id, 'amount' => 40],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '100',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->paid_amount)->toBe(100.0);
    expect(SellPayment::query()->where('sell_id', $sell->id)->count())->toBe(2);

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();

    expect(round($ledgers->where('account_id', $cash->id)->sum('debit'), 2))->toBe(60.0);
    expect(round($ledgers->where('account_id', $sslCommerz->id)->sum('debit'), 2))->toBe(40.0);
    expect(round($ledgers->sum('debit'), 2))->toBe(round($ledgers->sum('credit'), 2));
});

test('overpayment stores effective paid amount for accounting', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '1000',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();
    $netAmount = round((float) $sell->net_amount, 2);

    expect((float) $sell->paid_amount)->toBe($netAmount);
    $response->assertSessionHas('pos_change', 1000 - $netAmount);

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    expect(round($ledgers->where('account_id', $cash->id)->sum('debit'), 2))->toBe($netAmount);
});

test('store requires at least one item', function () {
    $user = sellUser();

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
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

test('branch user can open actions for their own branch sale', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.view', 'web');
    Permission::findOrCreate('inventory.sell.update', 'web');
    $user->givePermissionTo(['inventory.sell.view', 'inventory.sell.update']);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
    ]);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/show'));

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/edit'));
});

test('branch user does not see sales without a branch on index', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.view', 'web');
    $user->givePermissionTo('inventory.sell.view');
    $ownSell = Sell::factory()->create(['branch_id' => $branch->id, 'type' => SaleType::Sale]);
    $globalSell = Sell::factory()->create(['branch_id' => null, 'type' => SaleType::Sale]);

    $this->actingAs($user)
        ->get('/inventory/sell')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sells.data', 1)
            ->where('sells.data.0.id', $ownSell->id));

    $this->actingAs($user)
        ->get("/inventory/sell/{$globalSell->id}")
        ->assertNotFound();
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
        'discount_type' => 'flat',
        'discount_value' => '0',
        'special_discount_id' => null,
        'vat' => '0',
        'paid_amount' => '200',
        'payment_account_id' => $cash->id,
        'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '100', 'quantity' => '2']],
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(18.0);

    $sell = Sell::query()->latest('id')->first();
    expect($sell)->not->toBeNull();

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '300',
            'payment_account_id' => $cash->id,
            'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '100', 'quantity' => '3']],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect('/inventory/sell');

    $batch->refresh();
    // Old qty (2) rolled back → 20; new qty (3) deducted → 17
    expect((float) $batch->available)->toBe(17.0);

    $sell->refresh();
    expect((float) $sell->gross_amount)->toBe(300.0);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('authenticated user can delete a sale and stock is restored', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', [
        'customer_id' => null,
        'date' => now()->format('Y-m-d'),
        'discount_type' => 'flat',
        'discount_value' => '0',
        'special_discount_id' => null,
        'vat' => '0',
        'paid_amount' => '5000',
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
