<?php

use App\Enums\CustomerDueAlertStatus;
use App\Enums\SaleType;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\CustomerCoinTransaction;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellPayment;
use App\Models\SellProduct;
use App\Models\StockDistribution;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemAccountService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function sellUser(array $permissions = ['inventory.sell.view', 'inventory.sell.create', 'inventory.sell.update', 'inventory.sell.delete']): User
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

function sellCustomer(?int $branchId = null): Customer
{
    return Customer::factory()->create([
        'branch_id' => $branchId,
        'is_default' => false,
    ]);
}

function sellProduct(float $available = 20, ?int $branchId = null, ?float $salePrice = null): array
{
    $product = Product::factory()->create([
        'branch_id' => $branchId,
        ...($salePrice !== null ? ['sale_price' => $salePrice] : []),
    ]);
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

test('sell index includes all discount fields for table total', function () {
    $user = sellUser();
    ['product' => $product] = sellProduct(20, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'discount' => 50,
        'special_discount_amount' => 100,
        'promotion_discount_total' => 75,
        'coin_discount_amount' => 25,
        'round_off_amount' => 10,
        'gross_amount' => 1000,
        'vat' => 0,
        'paid_amount' => 710,
        'type' => SaleType::Sale,
    ]);

    SellProduct::query()->create([
        'branch_id' => $user->branch_id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'discount' => 30,
        'batches' => [],
    ]);

    expect($sell->fresh()->indexDiscountTotal())->toBe(290.0);

    $this->actingAs($user)
        ->get('/inventory/sell')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/index')
            ->has('sells.data', 1)
            ->where('sells.data.0.id', $sell->id)
            ->where('sells.data.0.discount', '50.00')
            ->where('sells.data.0.special_discount_amount', '100.00')
            ->where('sells.data.0.promotion_discount_total', '75.00')
            ->where('sells.data.0.coin_discount_amount', '25.00')
            ->where('sells.data.0.round_off_amount', '10.00')
            ->where('sells.data.0.line_discount_total', '30.00')
        );
});

test('sell net amount includes special discount and round off', function () {
    $user = sellUser();

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'special_discount_amount' => 100,
        'round_off_amount' => 50,
        'vat' => 0,
        'paid_amount' => 850,
        'type' => SaleType::Sale,
    ]);

    expect((float) $sell->net_amount)->toBe(850.0);
    expect(max(0, (float) $sell->net_amount - (float) $sell->paid_amount))->toEqual(0.0);
});

// ── Create ────────────────────────────────────────────────────────────────────

test('authenticated user can view sell create form', function () {
    $user = sellUser();

    $this->actingAs($user)
        ->get('/inventory/sell/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/sell/create')->has('today'));
});

test('sell create preselects default customer when user cannot view customers', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $walkIn = Customer::query()
        ->where('branch_id', $branch->id)
        ->where('is_default', true)
        ->firstOrFail();
    $user = sellUser(['inventory.sell.create']);
    $user->update(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->get('/inventory/sell/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preselectDefaultCustomer', true)
            ->where('initialCustomer.id', $walkIn->id)
            ->where('initialCustomer.is_default', true)
            ->where('defaultCustomer.id', $walkIn->id));
});

test('sell create preselects default customer when user can view customers', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $walkIn = Customer::query()
        ->where('branch_id', $branch->id)
        ->where('is_default', true)
        ->firstOrFail();
    $user = sellUser(['inventory.sell.create', 'party.customer.view']);
    $user->update(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->get('/inventory/sell/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('preselectDefaultCustomer', true)
            ->where('initialCustomer.id', $walkIn->id)
            ->where('initialCustomer.is_default', true)
            ->has('defaultCustomer'));
});

test('authenticated user can create a sale and stock is deducted', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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

    $superAdmin = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('inventory.stock-distribution.create', 'web');
    $superAdmin->givePermissionTo('inventory.stock-distribution.create');

    $targetBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $targetBranch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $branchUser->givePermissionTo('inventory.sell.create');

    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $branchCash = seedAccountingAccounts(branchId: $targetBranch->id);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $productGroupId = (string) Str::uuid();
    $mainProduct = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'product_group_id' => $productGroupId,
    ]);
    $product = Product::factory()->create([
        'branch_id' => $targetBranch->id,
        'product_group_id' => $productGroupId,
        'name' => $mainProduct->name,
    ]);

    $this->actingAs($mainUser)
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
                    'product_id' => $mainProduct->id,
                    'variation_id' => null,
                    'unit_price' => '1000',
                    'quantity' => '10',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $batch = Batch::query()->where('product_id', $mainProduct->id)->first();
    expect($batch)->not->toBeNull();
    expect($batch->branch_id)->toBe(Branch::MAIN_BRANCH_ID);
    expect((float) $batch->available)->toBe(10.0);

    $sellResponse = $this->actingAs($branchUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $sellResponse->assertOk();
    $match = collect($sellResponse->json())->firstWhere('id', $product->id);
    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(0.0);

    $this->actingAs($superAdmin)
        ->post('/inventory/stock-distribution', [
            'to_branch_id' => $targetBranch->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'items' => [
                [
                    'product_id' => $mainProduct->id,
                    'variation_id' => null,
                    'quantity' => '10',
                ],
            ],
        ])
        ->assertRedirect();

    $distribution = StockDistribution::query()->latest('id')->first();
    Permission::findOrCreate('inventory.stock-distribution.receive', 'web');
    $branchUser->givePermissionTo('inventory.stock-distribution.receive');

    $this->actingAs($branchUser)
        ->post("/inventory/stock-distribution/{$distribution->id}/receive")
        ->assertRedirect(route('inventory.stock-distribution.received'));

    $sellResponse = $this->actingAs($branchUser)
        ->getJson('/api/products/for-sell?search='.urlencode($product->name));

    $sellResponse->assertOk();
    $match = collect($sellResponse->json())->firstWhere('id', $product->id);
    expect($match)->not->toBeNull();
    expect((float) $match['stock'])->toBe(10.0);

    $this->actingAs($branchUser)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($branchUser->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2000',
            'payment_account_id' => $branchCash->id,
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

function dueSalePayload(User $user, Product $product, Customer $customer, $cash, array $overrides = []): array
{
    return array_merge([
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
    ], $overrides);
}

test('due sale with due given date creates a customer due alert', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'due_given_date' => '2026-08-15',
        ]))
        ->assertRedirect();

    $alert = CustomerDueAlert::query()->where('customer_id', $customer->id)->first();

    expect($alert)->not->toBeNull();
    expect($alert->branch_id)->toBe($user->branch_id);
    expect($alert->due_given_date->format('Y-m-d'))->toBe('2026-08-15');
    expect($alert->status)->toBe(CustomerDueAlertStatus::Unpaid);
});

test('full due sale with zero payment lines is allowed', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'paid_amount' => '0',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 0],
            ],
        ]))
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->first();

    expect((float) $sell->paid_amount)->toBe(0.0);
    expect((float) $sell->net_amount)->toBe(500.0);

    $customer->refresh();
    expect((float) $customer->balance)->toBe(500.0);
});

test('due sale without due given date does not create a customer due alert', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash))
        ->assertRedirect();

    expect(CustomerDueAlert::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('due sale merges into an existing active due alert when requested', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    $existingAlert = CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'due_given_date' => '2026-09-01',
            'due_alert_action' => 'merge',
        ]))
        ->assertRedirect();

    expect(CustomerDueAlert::query()->where('customer_id', $customer->id)->count())->toBe(1);

    $existingAlert->refresh();
    expect($existingAlert->due_given_date->format('Y-m-d'))->toBe('2026-09-01');
    expect($existingAlert->status)->toBe(CustomerDueAlertStatus::DateChanged);
});

test('due sale can create a separate due alert when customer already has an active alert', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'due_given_date' => '2026-10-01',
            'due_alert_action' => 'separate',
        ]))
        ->assertRedirect();

    $alerts = CustomerDueAlert::query()
        ->where('customer_id', $customer->id)
        ->orderBy('id')
        ->get();

    expect($alerts)->toHaveCount(2);
    expect($alerts->last()->due_given_date->format('Y-m-d'))->toBe('2026-10-01');
    expect($alerts->last()->status)->toBe(CustomerDueAlertStatus::Unpaid);
});

test('due sale with existing active alert requires merge or separate action when due date is provided', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 0,
    ]);

    CustomerDueAlert::create([
        'branch_id' => $user->branch_id,
        'customer_id' => $customer->id,
        'due_given_date' => '2026-07-01',
        'status' => CustomerDueAlertStatus::Unpaid,
    ]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'due_given_date' => '2026-10-01',
        ]))
        ->assertSessionHasErrors('due_alert_action');

    expect(CustomerDueAlert::query()->where('customer_id', $customer->id)->count())->toBe(1);
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

test('sale requires a customer', function () {
    $user = sellUser();
    seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => null,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '500',
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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

    $netAmount = round((float) $sell->net_amount, 2);
    expect((float) $sell->paid_amount)->toBe($netAmount);
    expect(SellPayment::query()->where('sell_id', $sell->id)->count())->toBe(2);

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $changeAmount = round(100 - $netAmount, 2);
    $cashLedgers = $ledgers->where('account_id', $cash->id);

    expect(round($cashLedgers->sum('debit'), 2))->toBe(60.0);
    expect(round($ledgers->where('account_id', $sslCommerz->id)->sum('debit'), 2))->toBe(40.0);

    if ($changeAmount > 0) {
        expect(round($cashLedgers->sum('credit'), 2))->toBe($changeAmount);
    }

    expect(round($ledgers->sum('debit'), 2))->toBe(round($ledgers->sum('credit'), 2));
});

test('overpayment stores effective paid amount for accounting', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
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
    $changeAmount = 1000 - $netAmount;
    $cashLedgers = $ledgers->where('account_id', $cash->id);

    expect(round($cashLedgers->sum('debit'), 2))->toBe(1000.0);
    expect(round($cashLedgers->sum('credit'), 2))->toBe($changeAmount);
    expect(round($cashLedgers->sum('debit') - $cashLedgers->sum('credit'), 2))->toBe($netAmount);
});

test('overpayment via payment accounts stores effective paid amount and change', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '1000',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 1000],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->first();
    expect($sell)->not->toBeNull();

    $netAmount = round((float) $sell->net_amount, 2);

    expect((float) $sell->paid_amount)->toBe($netAmount);
    $response->assertSessionHas('pos_change', 1000 - $netAmount);

    $payments = SellPayment::query()->where('sell_id', $sell->id)->get();
    expect($payments)->toHaveCount(1);
    expect((float) $payments->first()->amount)->toBe(1000.0);

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $changeAmount = 1000 - $netAmount;
    $cashLedgers = $ledgers->where('account_id', $cash->id);

    expect(round($cashLedgers->sum('debit'), 2))->toBe(1000.0);
    expect(round($cashLedgers->sum('credit'), 2))->toBe($changeAmount);
    expect(round($cashLedgers->sum('debit') - $cashLedgers->sum('credit'), 2))->toBe($netAmount);
});

test('overpayment across multiple accounts records tendered receipts and change credit on cash', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    $sslCommerz = SystemAccountService::resolve(SystemAccountKey::SslCommerz, $user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '1000',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 600],
                ['payment_account_id' => $sslCommerz->id, 'amount' => 400],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->first();
    expect($sell)->not->toBeNull();

    $netAmount = round((float) $sell->net_amount, 2);

    expect((float) $sell->paid_amount)->toBe($netAmount);
    $response->assertSessionHas('pos_change', 1000 - $netAmount);

    $payments = SellPayment::query()->where('sell_id', $sell->id)->orderBy('id')->get();
    expect($payments)->toHaveCount(2);
    expect(round((float) $payments[0]->amount, 2))->toBe(600.0);
    expect(round((float) $payments[1]->amount, 2))->toBe(400.0);

    $transaction = Transaction::query()
        ->where('source_type', Sell::class)
        ->where('source_id', $sell->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $changeAmount = 1000 - $netAmount;
    $cashLedgers = $ledgers->where('account_id', $cash->id);

    expect(round($cashLedgers->sum('debit'), 2))->toBe(600.0);
    expect(round($cashLedgers->sum('credit'), 2))->toBe($changeAmount);
    expect(round($cashLedgers->sum('debit') - $cashLedgers->sum('credit'), 2))->toBe(round(600 - $changeAmount, 2));
    expect(round($ledgers->where('account_id', $sslCommerz->id)->sum('debit'), 2))->toBe(400.0);
    expect(round($ledgers->where('account_id', $sslCommerz->id)->sum('credit'), 2))->toBe(0.0);
});

test('cash sale can apply manual round off discount', function () {
    $this->artisan('permissions:sync');

    $user = sellUser();
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');

    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product] = sellProduct(10, $user->branch_id, 500);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'round_off_amount' => '25',
            'vat' => '0',
            'paid_amount' => '475',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 475],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->latest('id')->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->round_off_amount)->toBe(25.0);
    expect((float) $sell->net_amount)->toBe(475.0);
});

test('due sale can apply round off when no payment is received', function () {
    $this->artisan('permissions:sync');

    $user = sellUser();
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');

    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product] = sellProduct(10, $user->branch_id, 500);
    $customer = sellCustomer($user->branch_id);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'round_off_amount' => '2',
            'vat' => '0',
            'paid_amount' => '0',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 0],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->latest('id')->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->round_off_amount)->toBe(2.0);
    expect((float) $sell->net_amount)->toBe(498.0);
    expect((float) $sell->paid_amount)->toBe(0.0);

    $customer->refresh();
    expect((float) $customer->balance)->toBe(498.0);
});

test('non-cash sale can apply round off', function () {
    $this->artisan('permissions:sync');

    $user = sellUser();
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');

    $sslCommerz = SystemAccountService::resolve(SystemAccountKey::SslCommerz, $user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id, 500);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'round_off_amount' => '25',
            'vat' => '0',
            'paid_amount' => '475',
            'payments' => [
                ['payment_account_id' => $sslCommerz->id, 'amount' => 475],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->latest('id')->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->round_off_amount)->toBe(25.0);
    expect((float) $sell->net_amount)->toBe(475.0);
});

test('split cash and non-cash sale can apply round off', function () {
    $this->artisan('permissions:sync');

    $user = sellUser();
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');

    $cash = seedAccountingAccounts(user: $user);
    $sslCommerz = SystemAccountService::resolve(SystemAccountKey::SslCommerz, $user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id, 500);
    $sellIdBefore = (int) (Sell::query()->max('id') ?? 0);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'round_off_amount' => '25',
            'vat' => '0',
            'paid_amount' => '475',
            'payments' => [
                ['payment_account_id' => $cash->id, 'amount' => 300],
                ['payment_account_id' => $sslCommerz->id, 'amount' => 175],
            ],
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->where('id', '>', $sellIdBefore)->latest('id')->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->round_off_amount)->toBe(25.0);
    expect((float) $sell->net_amount)->toBe(475.0);
});

test('store requires at least one item', function () {
    $user = sellUser();

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
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

test('sale succeeds when product inventory account balance is lower than cogs', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(10, $user->branch_id);

    $inventoryAccountId = SystemAccountService::id(SystemAccountKey::ProductInventory, $user->branch_id);
    ChartOfAccount::query()->whereKey($inventoryAccountId)->update(['current_balance' => 0]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '500',
                'quantity' => '1',
            ]],
        ])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->first();
    expect($sell)->not->toBeNull();

    $batch->refresh();
    expect((float) $batch->available)->toBe(9.0);
});

test('due sale with registered customer succeeds when inventory account balance is zero', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'is_default' => false,
        'balance' => 50,
    ]);

    $inventoryAccountId = SystemAccountService::id(SystemAccountKey::ProductInventory, $user->branch_id);
    ChartOfAccount::query()->whereKey($inventoryAccountId)->update(['current_balance' => 0]);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'paid_amount' => '100',
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $customer->refresh();
    expect((float) $customer->balance)->toBeGreaterThan(50.0);
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

test('sell show includes payment accounts for pos print', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    $sslCommerz = SystemAccountService::resolve(SystemAccountKey::SslCommerz, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => null,
        'paid_amount' => 100,
    ]);

    SellPayment::query()->create([
        'sell_id' => $sell->id,
        'payment_account_id' => $cash->id,
        'amount' => 60,
    ]);
    SellPayment::query()->create([
        'sell_id' => $sell->id,
        'payment_account_id' => $sslCommerz->id,
        'amount' => 40,
    ]);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/show')
            ->has('sell.payments', 2)
            ->where('sell.payments.0.payment_account_id', $cash->id)
            ->where('sell.payments.0.payment_account.code', $cash->code)
            ->where('sell.payments.0.payment_account.name', $cash->name)
            ->where('sell.payments.1.payment_account_id', $sslCommerz->id)
            ->where('sell.payments.1.payment_account.code', $sslCommerz->code)
            ->where('sell.payments.1.payment_account.name', $sslCommerz->name));
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
        'user_id' => $user->id,
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
    $ownSell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
    ]);
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

test('fully paid sale cannot be edited', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 500,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 500,
        'type' => SaleType::Sale,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => null,
        'unit_price' => 500,
        'quantity' => 1,
        'discount' => 0,
    ]);

    expect(max(0, (float) $sell->fresh()->net_amount - (float) $sell->paid_amount))->toBe(0.0);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertRedirect(route('inventory.sell.show', $sell))
        ->assertSessionHas('error', 'Fully paid sales cannot be edited.');

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '100', 'quantity' => '5']],
        ])
        ->assertRedirect(route('inventory.sell.show', $sell))
        ->assertSessionHas('error', 'Fully paid sales cannot be edited.');

    $sell->refresh();
    expect((float) $sell->gross_amount)->toBe(500.0);
});

test('partially paid sale opens payment-only edit mode', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product] = sellProduct(10, $user->branch_id);
    $customer = sellCustomer($user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash))
        ->assertRedirect();

    $sell = Sell::query()->where('user_id', $user->id)->latest('id')->first();
    expect((float) $sell->paid_amount)->toBe(200.0);
    expect(max(0, (float) $sell->net_amount - (float) $sell->paid_amount))->toBeGreaterThan(0.0);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/edit')
            ->where('paymentOnlyEdit', true));
});

test('partially paid sale update only changes payments not products', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(10, $user->branch_id);
    $customer = sellCustomer($user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash))
        ->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(9.0);

    $sell = Sell::query()->where('user_id', $user->id)->latest('id')->first();
    $originalGross = (float) $sell->gross_amount;
    $netAmount = (float) $sell->net_amount;

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'paid_amount' => (string) $netAmount,
            'payment_account_id' => $cash->id,
            'payments' => [['payment_account_id' => $cash->id, 'amount' => (string) $netAmount]],
            'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '100', 'quantity' => '5']],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.sell.show', $sell));

    $sell->refresh();
    $batch->refresh();

    expect((float) $sell->gross_amount)->toBe($originalGross);
    expect((float) $sell->paid_amount)->toBe($netAmount);
    expect((float) $batch->available)->toBe(9.0);
    expect($sell->products)->toHaveCount(1);
    expect((float) $sell->products->first()->quantity)->toBe(1.0);
});

test('unpaid due sale still allows full edit', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(10, $user->branch_id);
    $customer = sellCustomer($user->branch_id);
    $marker = 'unpaid-full-edit-'.Str::uuid();

    $this->actingAs($user)->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
        'paid_amount' => '0',
        'payment_account_id' => null,
        'payments' => [],
        'comment' => $marker,
    ]))->assertRedirect();

    $sell = Sell::query()->where('comment', $marker)->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->paid_amount)->toBe(0.0);
    expect(max(0, (float) $sell->net_amount - (float) $sell->paid_amount))->toBeGreaterThan(0.0);

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/edit')
            ->where('paymentOnlyEdit', false));

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '0',
            'payment_account_id' => null,
            'payments' => [],
            'comment' => $marker,
            'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '2']],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect('/inventory/sell');

    $batch->refresh();
    $sell->refresh();

    expect((float) $sell->gross_amount)->toBe(1000.0);
    expect((float) $batch->available)->toBe(8.0);
});

test('sell edit shows due collection payment accounts and sale payment lines separately', function () {
    $this->artisan('permissions:sync');

    $user = sellUser([
        'inventory.sell.view',
        'inventory.sell.create',
        'inventory.sell.update',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product] = sellProduct(10, $user->branch_id);
    $customer = sellCustomer($user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash))
        ->assertRedirect();

    $sell = Sell::query()->where('user_id', $user->id)->latest('id')->first();
    $collectionAmount = 150.0;

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['sell_id' => $sell->id, 'amount' => $collectionAmount],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $sell->refresh();

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/edit')
            ->where('paymentOnlyEdit', true)
            ->has('sell.payments', 1)
            ->where('sell.payments.0.payment_account_id', $cash->id)
            ->where('sell.payments.0.amount', 200)
            ->has('sell.collection_payments', 1)
            ->where('sell.collection_payments.0.payment_account_id', $cash->id)
            ->where('sell.collection_payments.0.amount', 150));
});

test('partially paid sale with only due collection can update payment without payment account error', function () {
    $this->artisan('permissions:sync');

    $user = sellUser([
        'inventory.sell.view',
        'inventory.sell.create',
        'inventory.sell.update',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    ['product' => $product] = sellProduct(10, $user->branch_id);
    $customer = sellCustomer($user->branch_id);

    $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'paid_amount' => '0',
            'payment_account_id' => null,
            'payments' => [],
        ]))
        ->assertRedirect();

    $sell = Sell::query()->where('user_id', $user->id)->latest('id')->first();
    $collectionAmount = 300.0;

    $this->actingAs($user)
        ->post('/party/customer-due-collection', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['sell_id' => $sell->id, 'amount' => $collectionAmount],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $sell->refresh();

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'paid_amount' => (string) $collectionAmount,
            'payments' => [],
            'due_given_date' => now()->format('Y-m-d'),
            'due_alert_action' => 'merge',
        ])
        ->assertRedirect(route('inventory.sell.show', $sell))
        ->assertSessionHas('success');

    expect((float) $sell->fresh()->paid_amount)->toBe($collectionAmount);
});

// ── Update ────────────────────────────────────────────────────────────────────

test('authenticated user can update a sale and stock is adjusted', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', [
        'customer_id' => sellCustomer($user->branch_id)->id,
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
            'customer_id' => sellCustomer($user->branch_id)->id,
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

test('sell edit exposes reserved stock for existing line items so zero warehouse stock does not block update', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(1, $user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', [
        'customer_id' => sellCustomer($user->branch_id)->id,
        'date' => now()->format('Y-m-d'),
        'discount_type' => 'flat',
        'discount_value' => '0',
        'special_discount_id' => null,
        'vat' => '0',
        'paid_amount' => '100',
        'payment_account_id' => $cash->id,
        'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '100', 'quantity' => '1']],
    ])->assertSessionDoesntHaveErrors()->assertRedirect();

    $batch->refresh();
    expect((float) $batch->available)->toBe(0.0);

    $sell = Sell::query()->latest('id')->first();
    expect($sell)->not->toBeNull();

    $this->actingAs($user)
        ->get("/inventory/sell/{$sell->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/edit')
            ->where('sell.items.0.available_stock', 1)
            ->where('sell.items.0.quantity', 1));

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'customer_id' => sellCustomer($user->branch_id)->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '100',
            'payment_account_id' => $cash->id,
            'items' => [['product_id' => $product->id, 'variation_id' => null, 'unit_price' => '100', 'quantity' => '1']],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect('/inventory/sell');

    $batch->refresh();
    expect((float) $batch->available)->toBe(0.0);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('authenticated user can delete a sale and stock is restored', function () {
    $user = sellUser();
    $cash = seedAccountingAccounts();
    ['product' => $product, 'batch' => $batch] = sellProduct(20, $user->branch_id);

    $this->actingAs($user)->post('/inventory/sell', [
        'customer_id' => sellCustomer($user->branch_id)->id,
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

function sellCoinSettings(?int $branchId, array $overrides = []): CoinSettings
{
    if ($branchId === null) {
        $branchId = Branch::factory()->create()->id;
    }

    return CoinSettings::query()->create(array_merge([
        'branch_id' => $branchId,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 50,
    ], $overrides));
}

test('sale can redeem coins and earn new coins', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');
    $cash = seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 50]);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '20',
            'paid_amount' => '480',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    preg_match('/\\/inventory\\/sell\\/(\\d+)/', (string) $response->headers->get('Location'), $matches);
    $sell = Sell::query()->findOrFail((int) $matches[1]);

    expect((float) $sell->coins_redeemed)->toBe(20.0);
    expect((float) $sell->coin_discount_amount)->toBe(20.0);
    expect((float) $sell->coins_earned)->toBe(floor((float) $sell->net_amount / 100));
    expect((float) $sell->net_amount)->toBe(round((float) $sell->gross_amount - 20, 2));

    $customer->refresh();
    expect((float) $customer->point)->toBe(round(50 - 20 + (float) $sell->coins_earned, 2));

    expect(CustomerCoinTransaction::query()->where('sell_id', $sell->id)->count())->toBe(2);
});

test('deleting a sale restores customer coin balance', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    Permission::findOrCreate('inventory.sell.delete', 'web');
    $user->givePermissionTo(['inventory.sell.create', 'inventory.sell.delete']);
    $cash = seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 64]);
    $startingBalance = 64.0;

    $response = $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '50',
            'paid_amount' => '450',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    preg_match('/\\/inventory\\/sell\\/(\\d+)/', (string) $response->headers->get('Location'), $matches);
    $sell = Sell::query()->findOrFail((int) $matches[1]);

    $customer->refresh();
    expect((float) $customer->point)->toBe(round($startingBalance - 50 + (float) $sell->coins_earned, 2));
    expect(CustomerCoinTransaction::query()->where('sell_id', $sell->id)->count())->toBe(2);

    $this->actingAs($user)
        ->delete("/inventory/sell/{$sell->id}")
        ->assertRedirect('/inventory/sell');

    expect(Sell::find($sell->id))->toBeNull();

    $customer->refresh();
    expect((float) $customer->point)->toBe($startingBalance);
});

test('sale rejects coin redemption when balance is insufficient', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');
    $cash = seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 5]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '10',
            'paid_amount' => '490',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasErrors('coins_redeemed');
});

test('updating a sale reverses and reapplies coin transactions', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    Permission::findOrCreate('inventory.sell.update', 'web');
    $user->givePermissionTo(['inventory.sell.create', 'inventory.sell.update']);
    $cash = seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 50]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '10',
            'paid_amount' => '490',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sell = Sell::query()->where('customer_id', $customer->id)->latest('id')->first();
    expect($sell)->not->toBeNull();
    expect((float) $sell->coins_redeemed)->toBe(10.0);

    $customer->refresh();
    expect((float) $customer->point)->toBe(round(50 - 10 + (float) $sell->coins_earned, 2));

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '0',
            'paid_amount' => '500',
            'payment_account_id' => $cash->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertRedirect(route('inventory.sell.index'));

    $sell->refresh();
    expect((float) $sell->coins_redeemed)->toBe(0.0);
    expect((float) $sell->coins_earned)->toBe(floor((float) $sell->net_amount / 100));

    $customer->refresh();
    expect((float) $customer->point)->toBe(round(50 + (float) $sell->coins_earned, 2));
});

test('updating a sale keeps existing coin redemption without insufficient balance error', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    Permission::findOrCreate('inventory.sell.update', 'web');
    $user->givePermissionTo(['inventory.sell.create', 'inventory.sell.update']);
    seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 54]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '49',
            'paid_amount' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sell = Sell::query()->where('customer_id', $customer->id)->latest('id')->first();
    expect((float) $sell->coins_redeemed)->toBe(49.0);

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => 'flat',
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'coins_redeemed' => '49',
            'paid_amount' => '0',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '1',
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('inventory.sell.index'));

    $sell->refresh();
    expect((float) $sell->coins_redeemed)->toBe(49.0);
});

test('updating a sale twice does not inflate customer coin balance', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    Permission::findOrCreate('inventory.sell.update', 'web');
    $user->givePermissionTo(['inventory.sell.create', 'inventory.sell.update']);
    seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 54]);

    $updatePayload = fn (Sell $sell) => [
        'customer_id' => $customer->id,
        'date' => now()->format('Y-m-d'),
        'discount_type' => 'flat',
        'discount_value' => '0',
        'special_discount_id' => null,
        'vat' => '0',
        'coins_redeemed' => '49',
        'paid_amount' => '0',
        'items' => [
            [
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '200',
                'quantity' => '1',
            ],
        ],
    ];

    $this->actingAs($user)
        ->post('/inventory/sell', $updatePayload(new Sell))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sell = Sell::query()->where('customer_id', $customer->id)->latest('id')->first();

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", $updatePayload($sell))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('inventory.sell.index'));

    $customer->refresh();
    $balanceAfterFirstEdit = (float) $customer->point;

    $this->actingAs($user)
        ->put("/inventory/sell/{$sell->id}", $updatePayload($sell))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('inventory.sell.index'));

    $customer->refresh();
    expect((float) $customer->point)->toBe($balanceAfterFirstEdit);
});

test('due sale earns coins on full net amount not only paid portion', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.sell.create', 'web');
    $user->givePermissionTo('inventory.sell.create');
    $cash = seedAccountingAccounts(user: $user);
    sellCoinSettings($user->branch_id);
    ['product' => $product] = sellProduct(10, $user->branch_id);

    $customer = sellCustomer($user->branch_id);
    $customer->update(['point' => 0]);

    $response = $this->actingAs($user)
        ->post('/inventory/sell', dueSalePayload($user, $product, $customer, $cash, [
            'paid_amount' => '200',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    preg_match('/\\/inventory\\/sell\\/(\\d+)/', (string) $response->headers->get('Location'), $matches);
    $sell = Sell::query()->findOrFail((int) $matches[1]);

    expect((float) $sell->paid_amount)->toBe(200.0);
    expect((float) $sell->net_amount)->toBeGreaterThan((float) $sell->paid_amount);
    expect((float) $sell->coins_earned)->toBe(floor((float) $sell->net_amount / 100));
    expect((float) $sell->coins_earned)->toBeGreaterThan(floor((float) $sell->paid_amount / 100));

    $customer->refresh();
    expect((float) $customer->point)->toBe((float) $sell->coins_earned);
});

test('sell net amount includes coin discount', function () {
    $user = sellUser();

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 1000,
        'discount' => 0,
        'special_discount_amount' => 0,
        'coin_discount_amount' => 50,
        'round_off_amount' => 0,
        'vat' => 0,
        'paid_amount' => 950,
        'type' => SaleType::Sale,
    ]);

    expect((float) $sell->net_amount)->toBe(950.0);
});
