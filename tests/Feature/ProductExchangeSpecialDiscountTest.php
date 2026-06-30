<?php

use App\Enums\DiscountType;
use App\Enums\PromotionScope;
use App\Enums\ReceivedPaymentMethod;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryAccountingService;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function productExchangeUser(array $permissions = ['inventory.product-exchange.create', 'inventory.sell.create']): User
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

/**
 * @return array{cash_debit: float, cash_credit: float, ar_debit: float, ar_credit: float}
 */
function exchangePaymentLedgerTotals(ProductExchange $exchange, ChartOfAccount $cash): array
{
    $transaction = Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->firstOrFail();

    $ledgers = Ledger::query()
        ->where('transaction_id', $transaction->id)
        ->get();

    $receivablesId = SystemAccountService::id(SystemAccountKey::CustomerReceivables, $exchange->branch_id);

    return [
        'cash_debit' => round($ledgers->where('account_id', $cash->id)->sum(fn (Ledger $line) => (float) $line->debit), 2),
        'cash_credit' => round($ledgers->where('account_id', $cash->id)->sum(fn (Ledger $line) => (float) $line->credit), 2),
        'ar_debit' => round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->debit), 2),
        'ar_credit' => round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->credit), 2),
    ];
}

function createSaleWithSpecialDiscount(User $user, Product $product, SpecialDiscount $specialDiscount, ChartOfAccount $cash): Sell
{
    $grossAmount = 2500.0;
    $specialAmount = min(
        (float) $specialDiscount->discount_value,
        $grossAmount,
    );
    $netAmount = $grossAmount - $specialAmount;

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    test()->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
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

function createSaleWithVatAndDiscount(User $user, Product $product, ChartOfAccount $cash, float $vatPercent = 10, float $invoiceDiscount = 100): Sell
{
    $grossAmount = 2500.0;
    $vatAmount = $grossAmount * ($vatPercent / 100);
    $netAmount = $grossAmount + $vatAmount - $invoiceDiscount;

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    test()->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => (string) $invoiceDiscount,
            'special_discount_id' => null,
            'vat' => (string) $vatPercent,
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
            'paid_amount' => '500',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'special_discount_id' => $specialDiscount->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
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
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
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

test('sale with manual invoice discount can be exchanged', function () {
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
            'customer_id' => Customer::factory()->create(['branch_id' => $user->branch_id])->id,
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
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
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
        ->assertRedirect();

    expect(ProductExchange::query()->where('sell_id', $sell->id)->exists())->toBeTrue();
});

test('sales lookup exposes manual discount flag separately from special discount', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.sell.create']);
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

test('product exchange mirrors sale vat and invoice discount on new gross', function () {
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

    // Sale has 10% VAT and 100 invoice discount on 2500 gross.
    $sell = createSaleWithVatAndDiscount($user, $oldProduct, $cash, vatPercent: 10, invoiceDiscount: 100);
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
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
        ->assertRedirect();

    $exchange = ProductExchange::query()->latest('id')->first();

    // New gross = 5 × 600 = 3000. Sale VAT rate = 250/2500 = 10%.
    // Exchange VAT = 3000 × 10% = 300.
    expect((float) $exchange->vat)->toBe(300.0);
    // Invoice discount = 100 (flat, carried from sale).
    expect((float) $exchange->discount)->toBe(100.0);
    // net = 3000 + 300 − 100 = 3200
    expect((float) $exchange->net_amount)->toBe(3200.0);
});

test('product exchange show and edit expose sale source discounts payload', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.view', 'inventory.product-exchange.update', 'inventory.sell.create']);
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
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'special_discount_id' => $specialDiscount->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
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
        ->assertRedirect();

    $exchange = ProductExchange::query()->latest('id')->first();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.show', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sell_discounts')
            ->has('totals')
            ->where('sell_discounts.special_discount_amount', 150)
            ->where('sell_discounts.invoice_discount', 0)
        );

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sell_discounts')
            ->where('sell_discounts.special_discount_amount', 150)
        );
});

test('sales lookup returns sell_discounts for exchange pre-fill', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.sell.create']);
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
        ->assertJsonStructure([
            'sell_discounts' => [
                'gross_amount',
                'vat',
                'invoice_discount',
                'invoice_discount_type',
                'invoice_discount_value',
                'round_off_amount',
                'special_discount_amount',
                'promotion_discount_total',
                'coin_discount_amount',
            ],
        ]);
});

test('same product exchange keeps proportional line discount', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2250',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                    'discount' => '250',
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
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $line = ProductExchange::query()->latest('id')->first()->products()->first();

    expect((float) $line->new_line_discount)->toBe(250.0);
});

test('different product exchange drops line discount', function () {
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

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2250',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $oldProduct->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                    'discount' => '250',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
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
        ->assertSessionDoesntHaveErrors();

    $line = ProductExchange::query()->latest('id')->first()->products()->first();

    expect((float) $line->new_line_discount)->toBe(0.0);
});

test('invoice discount override is stored on exchange', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '50',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2450',
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
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '200',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->first();

    expect((float) $exchange->discount)->toBe(200.0);
    expect((float) $exchange->discount_value)->toBe(200.0);
});

test('unpaid cash exchange remains editable', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2500',
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
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->first();

    expect($exchange->isEditable())->toBeTrue();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk();
});

test('fully paid exchange cannot be edited', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2500',
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
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '100',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->first();
    $exchange->update([
        'price_difference' => 100,
        'paid_amount' => 100,
        'due_amount' => 0,
    ]);

    expect($exchange->fresh()->isEditable())->toBeFalse();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertForbidden();
});

test('partially paid exchange opens payment-only edit', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
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
            'paid_amount' => '40',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'special_discount_id' => $specialDiscount->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
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
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();

    expect((float) $exchange->price_difference)->toBe(350.0);
    expect((float) $exchange->paid_amount)->toBe(40.0);
    expect((float) $exchange->due_amount)->toBe(310.0);
    expect($exchange->fresh()->isEditable())->toBeFalse();
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeTrue();
    expect($exchange->fresh()->canAccessEdit())->toBeTrue();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('paymentOnlyEdit', true)
            ->has('totals')
        );

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'comment' => 'Partial payment updated',
            'paid_amount' => '350',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.show', $exchange));

    $exchange->refresh();

    expect((float) $exchange->paid_amount)->toBe(350.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect($exchange->comment)->toBe('Partial payment updated');
});

test('product exchange refund uses gross difference plus new invoice and round off discounts', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 400]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 175]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '3',
            'round_off_amount' => '2',
            'special_discount_id' => null,
            'vat' => '5',
            'paid_amount' => '1630',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $oldProduct->id,
                    'variation_id' => null,
                    'unit_price' => '400',
                    'quantity' => '4',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '3',
            'round_off_amount' => '2',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '175',
                    'quantity' => '4',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();

    expect((float) $exchange->discount)->toBe(21.0);
    expect((float) $exchange->round_off_amount)->toBe(2.0);
    expect((float) $exchange->net_amount)->toBe(712.0);
    expect((float) $exchange->price_difference)->toBe(-923.0);
    expect($exchange->settlementAmount())->toBe(923.0);
});

test('product exchange applies percent invoice discount and round off on same product exchange', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 200]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '3',
            'round_off_amount' => '2',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '580',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '3',
            'round_off_amount' => '2',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $exchange = ProductExchange::query()->latest('id')->first();

    expect((float) $exchange->discount)->toBe(18.0);
    expect((float) $exchange->round_off_amount)->toBe(2.0);
    expect((float) $exchange->net_amount)->toBe(580.0);
    expect((float) $exchange->price_difference)->toBe(0.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
});

test('customer account exchange refund does not post to cash or bank accounts', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 200]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '3',
            'round_off_amount' => '2',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '580',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '3',
            'round_off_amount' => '2',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();
    $totals = exchangePaymentLedgerTotals($exchange, $cash);

    expect($totals['cash_debit'])->toBe(0.0);
    expect($totals['cash_credit'])->toBe(0.0);
    expect($totals['ar_credit'])->toBe(0.0);
});

test('partial cash upgrade posts only paid amount to selected account and due to receivables', function () {
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
            'paid_amount' => '40',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'special_discount_id' => $specialDiscount->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
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
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();
    $totals = exchangePaymentLedgerTotals($exchange, $cash);

    expect($totals['cash_debit'])->toBe(40.0);
    expect($totals['cash_credit'])->toBe(0.0);
    expect($totals['ar_debit'])->toBe(310.0);
    expect($totals['ar_credit'])->toBe(0.0);
});

test('zero settlement exchange does not post to cash or bank accounts', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '2500',
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
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();
    $totals = exchangePaymentLedgerTotals($exchange, $cash);

    expect((float) $exchange->price_difference)->toBe(0.0);
    expect($totals['cash_debit'])->toBe(0.0);
    expect($totals['cash_credit'])->toBe(0.0);
    expect($totals['ar_debit'])->toBe(0.0);
    expect($totals['ar_credit'])->toBe(0.0);
});

test('product exchange carries original promotion discount for same product after promotion expires', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $promotion = Promotion::factory()->percent(10)->forProduct()->create([
        'branch_id' => $user->branch_id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    PromotionTarget::create([
        'promotion_id' => $promotion->id,
        'target_type' => PromotionScope::Product->value,
        'target_id' => $product->id,
    ]);

    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);
    $saleDate = now()->subDays(2)->format('Y-m-d');

    $this->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => $saleDate,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
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
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProduct = $sell->products()->firstOrFail();

    expect((float) $sellProduct->promotion_discount)->toBe(100.0);

    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProduct->id,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '2',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();
    $line = $exchange->products()->firstOrFail();

    expect((float) $exchange->promotion_discount_total)->toBe(100.0);
    expect((float) $line->new_promotion_discount)->toBe(100.0);
    expect((float) $line->new_unit_price)->toBe(450.0);
    expect((float) $exchange->price_difference)->toBe(0.0);
});
