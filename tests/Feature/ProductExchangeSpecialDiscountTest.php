<?php

use App\Enums\DiscountType;
use App\Enums\PromotionScope;
use App\Enums\ReceivedPaymentMethod;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductExchangeProduct;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\Sell;
use App\Models\SpecialDiscount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\InventoryAccountingService;
use App\Services\SellExchangeOverlayService;
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
            ->has('sellDiscounts')
            ->where('sellDiscounts.special_discount_amount', 150)
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

test('fully paid exchange opens full edit', function () {
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

    expect($exchange->fresh()->isEditable())->toBeTrue();
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeFalse();
    expect($exchange->fresh()->canAccessEdit())->toBeTrue();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('paymentOnlyEdit', false));
});

test('partially paid exchange opens full edit', function () {
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
    expect($exchange->fresh()->isEditable())->toBeTrue();
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeFalse();
    expect($exchange->fresh()->canAccessEdit())->toBeTrue();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('paymentOnlyEdit', false)
            ->has('totals')
        );

    $line = $exchange->products()->firstOrFail();

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Partial payment updated',
            'paid_amount' => '350',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => $specialDiscount->id,
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $line->new_product_id,
                    'variation_id' => $line->new_variation_id,
                    'unit_price' => (string) $line->new_unit_price,
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

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

test('product exchange with partial return refunds the returned quantity and reduces the sale', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser();
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $product = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    $batch = Batch::factory()->for($product)->withStock(10)->create([
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
            'paid_amount' => '1500',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '3',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    // Keep/exchange 1 (same product, no upcharge) and return 2 to the customer account.
    $this->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'vat' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => '500',
                    'quantity' => '1',
                    'return_quantity' => '2',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();

    expect((float) $exchange->gross_amount)->toBe(500.0)
        ->and((float) $exchange->return_refund_amount)->toBe(1000.0)
        ->and((float) $exchange->price_difference)->toBe(-1000.0)
        ->and($exchange->settlementAmount())->toBe(1000.0);

    // Line records both the swap and the return.
    $line = $exchange->products()->firstOrFail();
    expect((float) $line->old_quantity)->toBe(1.0)
        ->and((float) $line->return_quantity)->toBe(2.0)
        ->and((float) $line->return_refund_amount)->toBe(1000.0);

    // Effective sale gross/net drops from 1500 to 500 (only the 1 kept unit remains).
    $sell->refresh()->load('productExchange.products.newProduct');
    $overlay = app(SellExchangeOverlayService::class);
    expect($overlay->effectiveGrossAmount($sell))->toBe(500.0)
        ->and($overlay->effectiveNetAmount($sell))->toBe(500.0)
        ->and($overlay->effectiveProducts($sell))->toHaveCount(1);

    // Stock: sold 3 (10 → 7), swap-out restore +1 then new deduct −1, return restore +2 → 9.
    expect((float) $batch->refresh()->available)->toBe(9.0);

    // The customer is owed the 1000 refund on their account.
    expect((float) $customer->refresh()->balance)->toBe(1000.0);
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

/**
 * @return array{sell: Sell, exchange: ProductExchange}
 */
function createCustomerAccountExchange(User $user, ChartOfAccount $cash, Customer $customer, float $newUnitPrice): array
{
    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => $newUnitPrice]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
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
                ['product_id' => $oldProduct->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '5'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    test()->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                ['sell_product_id' => $sellProductId, 'product_id' => $newProduct->id, 'variation_id' => null, 'unit_price' => (string) $newUnitPrice, 'quantity' => '5'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    return ['sell' => $sell, 'exchange' => ProductExchange::query()->latest('id')->firstOrFail()];
}

/**
 * Fingerprint the current (single, post-reversal) accounting transaction of an exchange
 * as an account_id => {debit, credit} map, so two exchanges can be compared ledger-for-ledger.
 *
 * @return array<int, array{debit: float, credit: float}>
 */
function exchangeLedgerFingerprint(ProductExchange $exchange): array
{
    $transaction = Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->latest('id')
        ->firstOrFail();

    return Ledger::query()
        ->where('transaction_id', $transaction->id)
        ->get()
        ->groupBy('account_id')
        ->map(fn ($rows) => [
            'debit' => round($rows->sum(fn (Ledger $line) => (float) $line->debit), 2),
            'credit' => round($rows->sum(fn (Ledger $line) => (float) $line->credit), 2),
        ])
        ->toArray();
}

function exchangeLatestLedgerIsBalanced(ProductExchange $exchange): bool
{
    $fingerprint = exchangeLedgerFingerprint($exchange);
    $debit = round(array_sum(array_map(fn (array $line) => $line['debit'], $fingerprint)), 2);
    $credit = round(array_sum(array_map(fn (array $line) => $line['credit'], $fingerprint)), 2);

    return abs($debit - $credit) < 0.01;
}

/**
 * @return array{sell: Sell, exchange: ProductExchange, exchanged_line: ProductExchangeProduct, untouched_sell_product_id: int}
 */
function createPartialSaleExchange(User $user, ChartOfAccount $cash, Customer $customer): array
{
    $firstOld = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 300]);
    $secondOld = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 200]);
    Batch::factory()->for($firstOld)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);
    Batch::factory()->for($secondOld)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 200]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
        ->post('/inventory/sell', [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'special_discount_id' => null,
            'vat' => '0',
            'paid_amount' => '3500',
            'payment_account_id' => $cash->id,
            'comment' => null,
            'items' => [
                ['product_id' => $firstOld->id, 'variation_id' => null, 'unit_price' => '300', 'quantity' => '5'],
                ['product_id' => $secondOld->id, 'variation_id' => null, 'unit_price' => '200', 'quantity' => '10'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $exchangedSellProductId = $sell->products()->orderBy('id')->first()->id;
    $untouchedSellProductId = $sell->products()->orderBy('id')->skip(1)->first()->id;

    test()->actingAs($user)
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
                    'sell_product_id' => $exchangedSellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '200',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();

    return [
        'sell' => $sell,
        'exchange' => $exchange,
        'exchanged_line' => $exchange->products()->firstOrFail(),
        'untouched_sell_product_id' => $untouchedSellProductId,
    ];
}

/**
 * @return array{exchange: ProductExchange}
 */
function createDirectCashExchange(User $user, ChartOfAccount $cash, Customer $customer, float $newUnitPrice, string $paidAmount): array
{
    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => $newUnitPrice]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
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
                ['product_id' => $oldProduct->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '5'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    test()->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => $paidAmount,
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                ['sell_product_id' => $sellProductId, 'product_id' => $newProduct->id, 'variation_id' => null, 'unit_price' => (string) $newUnitPrice, 'quantity' => '5'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    return ['exchange' => ProductExchange::query()->latest('id')->firstOrFail()];
}

test('list cash settlement posts the same ledgers as a direct cash exchange', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    // Path A — exchange created directly on cash, customer pays 500.
    $direct = createDirectCashExchange($user, $cash, $customer, 600, '500')['exchange'];

    // Path B — exchange created on customer account, then settled to cash from the list.
    $settled = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.payment', $settled), [
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'paid_amount' => '500',
        ])
        ->assertSessionDoesntHaveErrors();

    $settled->refresh();

    // The resulting accounting transaction must be identical account-for-account.
    expect(exchangeLedgerFingerprint($settled))->toEqual(exchangeLedgerFingerprint($direct));
});

test('customer-pays exchange settlement can be recorded as cash from the list', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    // New 600 × 5 = 3000 vs old 500 × 5 = 2500 → customer pays 500.
    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];

    expect((float) $exchange->price_difference)->toBe(500.0);
    expect((float) $exchange->paid_amount)->toBe(0.0);
    expect((float) $exchange->due_amount)->toBe(500.0);

    $balanceBefore = (float) $customer->fresh()->balance;

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.payment', $exchange), [
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'paid_amount' => '500',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect((float) $exchange->paid_amount)->toBe(500.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect($exchange->payment_type)->toBe(ReceivedPaymentMethod::Cash);
    expect($exchange->payment_account_id)->toBe($cash->id);

    // The receivable that sat on the customer account is cleared by the cash receipt.
    expect((float) $customer->fresh()->balance)->toBe(round($balanceBefore + 500.0, 2));

    $totals = exchangePaymentLedgerTotals($exchange, $cash);
    expect($totals['cash_debit'])->toBe(500.0);
    expect($totals['cash_credit'])->toBe(0.0);
});

test('refund exchange settlement can be recorded as cash from the list', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    // New 300 × 5 = 1500 vs old 500 × 5 = 2500 → refund 1000 to customer.
    $exchange = createCustomerAccountExchange($user, $cash, $customer, 300)['exchange'];

    expect((float) $exchange->price_difference)->toBe(-1000.0);
    expect($exchange->settlementAmount())->toBe(1000.0);

    $balanceBefore = (float) $customer->fresh()->balance;

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.payment', $exchange), [
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'paid_amount' => '1000',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect((float) $exchange->paid_amount)->toBe(1000.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect($exchange->payment_type)->toBe(ReceivedPaymentMethod::Cash);

    // Refund payable that sat on the customer account is cleared by the cash payout.
    expect((float) $customer->fresh()->balance)->toBe(round($balanceBefore - 1000.0, 2));

    $totals = exchangePaymentLedgerTotals($exchange, $cash);
    expect($totals['cash_credit'])->toBe(1000.0);
    expect($totals['cash_debit'])->toBe(0.0);
});

test('list settlement requires a payment account for cash', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.payment', $exchange), [
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => null,
            'paid_amount' => '500',
        ])
        ->assertSessionHasErrors('payment_account_id');

    $exchange->refresh();

    expect((float) $exchange->paid_amount)->toBe(0.0);
    expect((float) $exchange->due_amount)->toBe(500.0);
});

test('exchange list settlement adds to paid amount incrementally', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];

    expect((float) $exchange->due_amount)->toBe(500.0);

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.payment', $exchange), [
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'paid_amount' => '200',
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange->refresh();

    expect((float) $exchange->paid_amount)->toBe(200.0);
    expect((float) $exchange->due_amount)->toBe(300.0);
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeFalse();

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.payment', $exchange), [
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'paid_amount' => '300',
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange->refresh();

    expect((float) $exchange->paid_amount)->toBe(500.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect($exchange->fresh()->isFullyPaid())->toBeTrue();
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeFalse();
    expect($exchange->fresh()->canAccessEdit())->toBeTrue();

    $totals = exchangePaymentLedgerTotals($exchange, $cash);
    expect($totals['cash_debit'])->toBe(500.0);
});

test('fully paid cash exchange remains editable', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);

    $exchange = createDirectCashExchange($user, $cash, Customer::factory()->create(['branch_id' => $user->branch_id]), 600, '500')['exchange'];

    expect($exchange->fresh()->isFullyPaid())->toBeTrue();
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeFalse();
    expect($exchange->fresh()->canAccessEdit())->toBeTrue();
});

test('unpaid customer account exchange is editable from list', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];

    expect((float) $exchange->due_amount)->toBe(500.0);
    expect($exchange->fresh()->isEditable())->toBeTrue();
    expect($exchange->fresh()->isPaymentOnlyEditable())->toBeFalse();
    expect($exchange->fresh()->canAccessEdit())->toBeTrue();

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('paymentOnlyEdit', false));
});

test('exchange edit exposes sell discounts for live settlement calculation', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $fixture = createPartialSaleExchange($user, $cash, $customer);

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $fixture['exchange']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sellDiscounts')
            ->where('sellDiscounts.gross_amount', 3500)
        );
});

test('exchange edit lists every sale line including products not yet exchanged', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $fixture = createPartialSaleExchange($user, $cash, $customer);

    $this->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $fixture['exchange']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('exchange.items', 2)
            ->where('exchange.items.0.sell_product_id', $fixture['exchanged_line']->sell_product_id)
            ->where('exchange.items.0.quantity', '5')
            ->where('exchange.items.0.new_product_id', $fixture['exchanged_line']->new_product_id)
            ->where('exchange.items.1.sell_product_id', $fixture['untouched_sell_product_id'])
            ->where('exchange.items.1.quantity', '0')
            ->where('exchange.items.1.return_quantity', '0')
            ->where('exchange.items.1.new_product_id', '')
        );
});

test('exchange update without changes reverses and reposts matching ledger accounts', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];
    $line = $exchange->products()->firstOrFail();
    $beforeFingerprint = exchangeLedgerFingerprint($exchange);
    $transactionIdBefore = Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->latest('id')
        ->value('id');

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => $exchange->comment,
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $line->new_product_id,
                    'variation_id' => $line->new_variation_id,
                    'unit_price' => (string) $line->new_unit_price,
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => (string) (int) $line->return_quantity,
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect(exchangeLedgerFingerprint($exchange))->toBe($beforeFingerprint);
    expect(exchangeLatestLedgerIsBalanced($exchange))->toBeTrue();
    expect(Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->latest('id')
        ->value('id'))->not->toBe($transactionIdBefore);
});

test('exchange update with changed replacement price reposts balanced ledgers', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];
    $line = $exchange->products()->firstOrFail();
    $beforeFingerprint = exchangeLedgerFingerprint($exchange);
    $receivablesId = SystemAccountService::resolve(SystemAccountKey::CustomerReceivables, $user->branch_id)->id;

    $this->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Lower replacement price',
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $line->new_product_id,
                    'variation_id' => $line->new_variation_id,
                    'unit_price' => '200',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => (string) (int) $line->return_quantity,
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();
    $afterFingerprint = exchangeLedgerFingerprint($exchange);

    expect($afterFingerprint)->not->toBe($beforeFingerprint);
    expect(exchangeLatestLedgerIsBalanced($exchange))->toBeTrue();
    expect($afterFingerprint[$receivablesId]['credit'] ?? 0)->toBeGreaterThan($beforeFingerprint[$receivablesId]['credit'] ?? 0);
    expect((float) $exchange->products()->first()->new_unit_price)->toBe(200.0);
});

test('exchange edit exposes refund settlement labels for refund exchanges', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
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

    test()->actingAs($user)
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
                ['product_id' => $oldProduct->id, 'variation_id' => null, 'unit_price' => '400', 'quantity' => '4'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    test()->actingAs($user)
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
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();

    expect((float) $exchange->price_difference)->toBe(-923.0);

    test()->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('paymentOnlyEdit', false)
            ->where('totals.is_refund', true)
            ->where('totals.settlement', 923)
            ->where('exchange.is_refund', true)
            ->where('exchange.settlement_amount', 923)
            ->where('exchange.discount_type', DiscountType::Percent->value)
            ->where('exchange.discount_value', 3)
            ->where('sellDiscounts.round_off_amount', 2)
            ->where('sellDiscounts.invoice_discount_type', DiscountType::Percent->value)
            ->where('sellDiscounts.invoice_discount_value', 3)
        );
});

test('exchange edit exposes customer pays settlement for charge exchanges', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createCustomerAccountExchange($user, $cash, $customer, 600)['exchange'];

    expect((float) $exchange->price_difference)->toBe(500.0);

    test()->actingAs($user)
        ->get(route('inventory.product-exchange.edit', $exchange))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('paymentOnlyEdit', false)
            ->where('totals.is_refund', false)
            ->where('totals.settlement', 500)
            ->where('exchange.is_refund', false)
            ->where('exchange.settlement_amount', 500)
        );
});

test('exchange edit update can add another sale line and recalculates settlement', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $fixture = createPartialSaleExchange($user, $cash, $customer);
    $exchange = $fixture['exchange'];
    $firstLine = $fixture['exchanged_line'];

    $secondReplacement = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 250]);
    Batch::factory()->for($secondReplacement)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    expect($exchange->products)->toHaveCount(1);
    expect((float) $exchange->price_difference)->toBe(-500.0);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Added second line',
            'paid_amount' => '0',
            'payment_type' => ReceivedPaymentMethod::Customer_Account->value,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $firstLine->sell_product_id,
                    'product_id' => $firstLine->new_product_id,
                    'variation_id' => $firstLine->new_variation_id,
                    'unit_price' => (string) $firstLine->new_unit_price,
                    'quantity' => (string) (int) $firstLine->old_quantity,
                    'return_quantity' => '0',
                ],
                [
                    'sell_product_id' => $fixture['untouched_sell_product_id'],
                    'product_id' => $secondReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '250',
                    'quantity' => '5',
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect($exchange->products)->toHaveCount(2);
    // Line 1: 300×5 → 200×5 = -500 refund; line 2: 200×5 → 250×5 = +250 charge; net -250 refund.
    expect((float) $exchange->price_difference)->toBe(-250.0);
    expect((float) $exchange->due_amount)->toBe(250.0);
    expect(exchangeLatestLedgerIsBalanced($exchange))->toBeTrue();
});

test('exchange edit update preserves partial refund payment when settlement unchanged', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 300]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
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
                ['product_id' => $oldProduct->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '5'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    test()->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '200',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '300',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();
    $line = $exchange->products()->firstOrFail();

    expect((float) $exchange->price_difference)->toBe(-1000.0);
    expect((float) $exchange->paid_amount)->toBe(200.0);
    expect((float) $exchange->due_amount)->toBe(800.0);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Adjusted comment only',
            'paid_amount' => '200',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $line->new_product_id,
                    'variation_id' => $line->new_variation_id,
                    'unit_price' => (string) $line->new_unit_price,
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect((float) $exchange->price_difference)->toBe(-1000.0);
    expect((float) $exchange->paid_amount)->toBe(200.0);
    expect((float) $exchange->due_amount)->toBe(800.0);
    expect(exchangeLatestLedgerIsBalanced($exchange))->toBeTrue();
});

test('exchange edit update preserves partial customer pays amount when settlement unchanged', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $exchange = createDirectCashExchange($user, $cash, $customer, 600, '150')['exchange'];
    $line = $exchange->products()->firstOrFail();

    expect((float) $exchange->price_difference)->toBe(500.0);
    expect((float) $exchange->paid_amount)->toBe(150.0);
    expect((float) $exchange->due_amount)->toBe(350.0);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Customer pays edit',
            'paid_amount' => '150',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $line->new_product_id,
                    'variation_id' => $line->new_variation_id,
                    'unit_price' => (string) $line->new_unit_price,
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect((float) $exchange->price_difference)->toBe(500.0);
    expect((float) $exchange->paid_amount)->toBe(150.0);
    expect((float) $exchange->due_amount)->toBe(350.0);
    expect(exchangeLatestLedgerIsBalanced($exchange))->toBeTrue();
});

test('exchange edit update adjusts fully paid refund down when settlement decreases', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $oldProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 500]);
    Batch::factory()->for($oldProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $newProduct = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 300]);
    Batch::factory()->for($newProduct)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
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
                ['product_id' => $oldProduct->id, 'variation_id' => null, 'unit_price' => '500', 'quantity' => '5'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $sell = Sell::query()->latest('id')->firstOrFail();
    $sellProductId = $sell->products()->first()->id;

    test()->actingAs($user)
        ->post('/inventory/product-exchange', [
            'sell_id' => $sell->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'paid_amount' => '1000',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $sellProductId,
                    'product_id' => $newProduct->id,
                    'variation_id' => null,
                    'unit_price' => '300',
                    'quantity' => '5',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $exchange = ProductExchange::query()->latest('id')->firstOrFail();
    $line = $exchange->products()->firstOrFail();

    expect((float) $exchange->paid_amount)->toBe(1000.0);
    expect((float) $exchange->due_amount)->toBe(0.0);

    $higherReplacement = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 350]);
    Batch::factory()->for($higherReplacement)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Higher replacement',
            'paid_amount' => '1000',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $higherReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '350',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();

    expect((float) $exchange->price_difference)->toBe(-750.0);
    expect((float) $exchange->paid_amount)->toBe(750.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect(exchangeLatestLedgerIsBalanced($exchange))->toBeTrue();
});

test('exchange edit update tracks overpayment as a customer receivable when a shrinking refund falls below what was already paid in cash', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    // Sale: 5 x 500 = 2500. Exchange to 5 x 300 = 1500, refund 1000, paid in full.
    $exchange = createDirectCashExchange($user, $cash, $customer, 300, '1000')['exchange'];
    $line = $exchange->products()->firstOrFail();

    expect((float) $exchange->paid_amount)->toBe(1000.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect((float) $exchange->overpaid_amount)->toBe(0.0);

    // Edit to a pricier replacement: 5 x 350 = 1750, refund shrinks to 750 —
    // 250 less than the 1000 already handed to the customer.
    $higherReplacement = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 350]);
    Batch::factory()->for($higherReplacement)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Reduced refund after prior full cash payout',
            'paid_amount' => '1000',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $higherReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '350',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();
    $customer->refresh();

    expect((float) $exchange->price_difference)->toBe(-750.0);
    expect((float) $exchange->paid_amount)->toBe(750.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect((float) $exchange->overpaid_amount)->toBe(250.0);
    expect((float) $customer->balance)->toBe(250.0);

    $transactions = Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->get();
    $ledgers = Ledger::query()->whereIn('transaction_id', $transactions->pluck('id'))->get();
    $totalDebit = round($ledgers->sum(fn (Ledger $line) => (float) $line->debit), 2);
    $totalCredit = round($ledgers->sum(fn (Ledger $line) => (float) $line->credit), 2);

    expect($totalDebit)->toBe($totalCredit);

    $receivablesId = SystemAccountService::id(SystemAccountKey::CustomerReceivables, $exchange->branch_id);
    $receivableDebit = round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->debit), 2);

    expect($receivableDebit)->toBe(250.0);

    // Editing again back to the original replacement (refund back up to 1000,
    // matching what's already been paid) must fully reverse the receivable —
    // no leftover balance, no leftover overpaid_amount.
    $originalReplacement = Product::query()
        ->where('id', '!=', $higherReplacement->id)
        ->where('sale_price', 300)
        ->firstOrFail();

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Reverted to original replacement',
            'paid_amount' => '1000',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $originalReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '300',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();
    $customer->refresh();

    expect((float) $exchange->price_difference)->toBe(-1000.0);
    expect((float) $exchange->paid_amount)->toBe(1000.0);
    expect((float) $exchange->overpaid_amount)->toBe(0.0);
    expect((float) $customer->balance)->toBe(0.0);
});

test('exchange edit accepts customer payment that reduces the overpaid receivable', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    // Sale: 5 x 500 = 2500. Exchange to 5 x 300 = 1500, refund 1000, paid in full.
    $exchange = createDirectCashExchange($user, $cash, $customer, 300, '1000')['exchange'];
    $line = $exchange->products()->firstOrFail();

    // Edit to a pricier replacement: refund shrinks to 750 — 250 overpaid.
    // Customer pays 150 back now; only 100 should remain as receivable/due.
    $higherReplacement = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 350]);
    Batch::factory()->for($higherReplacement)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Customer paid part of the overpaid refund back',
            'paid_amount' => '1000',
            'customer_payment_amount' => '150',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $higherReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '350',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();
    $customer->refresh();

    expect((float) $exchange->price_difference)->toBe(-750.0);
    expect((float) $exchange->paid_amount)->toBe(750.0);
    expect((float) $exchange->due_amount)->toBe(0.0);
    expect((float) $exchange->overpaid_amount)->toBe(100.0);
    expect((float) $customer->balance)->toBe(100.0);

    $transactions = Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->get();
    $ledgers = Ledger::query()->whereIn('transaction_id', $transactions->pluck('id'))->get();
    $totalDebit = round($ledgers->sum(fn (Ledger $line) => (float) $line->debit), 2);
    $totalCredit = round($ledgers->sum(fn (Ledger $line) => (float) $line->credit), 2);

    expect($totalDebit)->toBe($totalCredit);

    $receivablesId = SystemAccountService::id(SystemAccountKey::CustomerReceivables, $exchange->branch_id);
    $receivableDebit = round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->debit), 2);

    expect($receivableDebit)->toBe(100.0);
});

test('exchange edit customer payment can clear the full overpaid amount', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser(['inventory.product-exchange.create', 'inventory.product-exchange.update', 'inventory.sell.create']);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    $exchange = createDirectCashExchange($user, $cash, $customer, 300, '1000')['exchange'];
    $line = $exchange->products()->firstOrFail();

    $higherReplacement = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 350]);
    Batch::factory()->for($higherReplacement)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Customer repaid the full overpaid refund',
            'paid_amount' => '1000',
            'customer_payment_amount' => '250',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $higherReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '350',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('inventory.product-exchange.index'));

    $exchange->refresh();
    $customer->refresh();

    expect((float) $exchange->overpaid_amount)->toBe(0.0);
    expect((float) $customer->balance)->toBe(0.0);

    $transactions = Transaction::query()
        ->where('source_type', ProductExchange::class)
        ->where('source_id', $exchange->id)
        ->get();
    $ledgers = Ledger::query()->whereIn('transaction_id', $transactions->pluck('id'))->get();
    $receivablesId = SystemAccountService::id(SystemAccountKey::CustomerReceivables, $exchange->branch_id);
    $receivableDebit = round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $line) => (float) $line->debit), 2);

    expect($receivableDebit)->toBe(0.0);
});

/**
 * Collecting a receivable credits (decreases) the Customer Receivables account, which
 * TransactionService guards with an insufficient-balance check. Give it headroom so the
 * guard reflects this test's own postings rather than whatever the shared DB carries.
 */
function seedCustomerReceivableBalance(User $user, float $amount = 100000): void
{
    $receivables = SystemAccountService::resolve(SystemAccountKey::CustomerReceivables, $user->branch_id);

    app(InventoryAccountingService::class)->postAccountOpeningBalance(
        $receivables,
        $amount,
        now()->format('Y-m-d'),
    );
}

/**
 * Overpay an exchange by 250: refund 1000 paid in cash, then edit the replacement
 * up so only 750 was actually owed. The customer owes the 250 back.
 *
 * @return array{exchange: ProductExchange, customer: Customer}
 */
function createOverpaidExchange(User $user, ChartOfAccount $cash, Customer $customer): array
{
    $exchange = createDirectCashExchange($user, $cash, $customer, 300, '1000')['exchange'];
    $line = $exchange->products()->firstOrFail();

    $higherReplacement = Product::factory()->create(['branch_id' => $user->branch_id, 'sale_price' => 350]);
    Batch::factory()->for($higherReplacement)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    test()->actingAs($user)
        ->put(route('inventory.product-exchange.update', $exchange), [
            'date' => now()->format('Y-m-d'),
            'comment' => 'Refund shrank below what was already paid out',
            'paid_amount' => '1000',
            'payment_type' => ReceivedPaymentMethod::Cash->value,
            'payment_account_id' => $cash->id,
            'discount_type' => DiscountType::Flat->value,
            'discount_value' => '0',
            'items' => [
                [
                    'sell_product_id' => $line->sell_product_id,
                    'product_id' => $higherReplacement->id,
                    'variation_id' => null,
                    'unit_price' => '350',
                    'quantity' => (string) (int) $line->old_quantity,
                    'return_quantity' => '0',
                ],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    return ['exchange' => $exchange->refresh(), 'customer' => $customer->refresh()];
}

test('due collection lists an exchange overpayment alongside due sale invoices', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser([
        'inventory.product-exchange.create',
        'inventory.product-exchange.update',
        'inventory.sell.create',
        'party.customer-due-collection.view',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    $exchange = createOverpaidExchange($user, $cash, $customer)['exchange'];

    expect((float) $exchange->overpaid_amount)->toBe(250.0);

    $response = $this->actingAs($user)
        ->getJson(route('api.customers.due-sales', $customer))
        ->assertOk();

    $exchangeRow = collect($response->json('sales'))
        ->firstWhere('key', 'exchange:'.$exchange->id);

    expect($exchangeRow)->not->toBeNull();
    expect($exchangeRow['type'])->toBe('exchange');
    expect($exchangeRow['id'])->toBe($exchange->id);
    expect((float) $exchangeRow['due_amount'])->toBe(250.0);
    expect($exchangeRow['invoice_number'])->toBe($exchange->invoice_number);
});

test('collecting an exchange overpayment clears the customer balance and posts cash against the receivable', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser([
        'inventory.product-exchange.create',
        'inventory.product-exchange.update',
        'inventory.sell.create',
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    seedCustomerReceivableBalance($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    $exchange = createOverpaidExchange($user, $cash, $customer)['exchange'];
    $customer->refresh();

    expect((float) $customer->balance)->toBe(250.0);

    $this->actingAs($user)
        ->post(route('party.customer-due-collection.store'), [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'comment' => 'Customer paid back the overpaid refund',
            'allocations' => [
                ['product_exchange_id' => $exchange->id, 'amount' => '250'],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertSessionMissing('warning');

    $exchange->refresh();
    $customer->refresh();

    expect((float) $exchange->overpaid_collected_amount)->toBe(250.0);
    expect($exchange->overpaidDueAmount())->toBe(0.0);
    expect((float) $customer->balance)->toBe(0.0);

    // Settled — it should drop off the collectible list.
    $remaining = collect(
        $this->actingAs($user)
            ->getJson(route('api.customers.due-sales', $customer))
            ->json('sales'),
    )->firstWhere('key', 'exchange:'.$exchange->id);

    expect($remaining)->toBeNull();

    $payment = CustomerPayment::query()->latest('id')->firstOrFail();
    $ledgers = Ledger::query()
        ->whereIn(
            'transaction_id',
            Transaction::query()
                ->where('source_type', CustomerPayment::class)
                ->where('source_id', $payment->id)
                ->pluck('id'),
        )
        ->get();

    $receivablesId = SystemAccountService::id(SystemAccountKey::CustomerReceivables, $exchange->branch_id);

    expect(round($ledgers->where('account_id', $cash->id)->sum(fn (Ledger $l) => (float) $l->debit), 2))->toBe(250.0);
    expect(round($ledgers->where('account_id', $receivablesId)->sum(fn (Ledger $l) => (float) $l->credit), 2))->toBe(250.0);
});

test('deleting an exchange overpayment collection restores the customer balance and the collectible row', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser([
        'inventory.product-exchange.create',
        'inventory.product-exchange.update',
        'inventory.sell.create',
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
        'party.customer-due-collection.delete',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    seedCustomerReceivableBalance($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    $exchange = createOverpaidExchange($user, $cash, $customer)['exchange'];

    $this->actingAs($user)
        ->post(route('party.customer-due-collection.store'), [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'comment' => null,
            'allocations' => [
                ['product_exchange_id' => $exchange->id, 'amount' => '250'],
            ],
        ])
        ->assertSessionDoesntHaveErrors();

    $payment = CustomerPayment::query()->latest('id')->firstOrFail();

    $this->actingAs($user)
        ->delete(route('party.customer-due-collection.destroy', $payment))
        ->assertSessionDoesntHaveErrors();

    $exchange->refresh();
    $customer->refresh();

    expect((float) $exchange->overpaid_collected_amount)->toBe(0.0);
    expect($exchange->overpaidDueAmount())->toBe(250.0);
    expect((float) $customer->balance)->toBe(250.0);
});

test('an exchange overpayment collection cannot exceed the overpaid amount', function () {
    $this->artisan('permissions:sync');

    $user = productExchangeUser([
        'inventory.product-exchange.create',
        'inventory.product-exchange.update',
        'inventory.sell.create',
        'party.customer-due-collection.view',
        'party.customer-due-collection.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    seedExchangeAccountingBalances($user);
    seedCustomerReceivableBalance($user);
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

    $exchange = createOverpaidExchange($user, $cash, $customer)['exchange'];

    $this->actingAs($user)
        ->post(route('party.customer-due-collection.store'), [
            'customer_id' => $customer->id,
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'comment' => null,
            'allocations' => [
                ['product_exchange_id' => $exchange->id, 'amount' => '400'],
            ],
        ])
        ->assertSessionHasErrors('allocations.0.amount');

    $exchange->refresh();

    expect((float) $exchange->overpaid_collected_amount)->toBe(0.0);
});
