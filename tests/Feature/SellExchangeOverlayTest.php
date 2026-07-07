<?php

use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductExchangeProduct;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use App\Services\ReportService;
use App\Services\SellExchangeOverlayService;

test('exchange overlay computes effective sale totals without mutating the sale', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $oldProduct = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Old Shirt']);
    $newProduct = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'New Shirt']);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'gross_amount' => 1000,
        'discount' => 50,
        'special_discount_amount' => 100,
        'promotion_discount_total' => 75,
        'coin_discount_amount' => 25,
        'round_off_amount' => 10,
        'paid_amount' => 785,
        'vat' => 0,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $oldProduct->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'discount' => 30,
        'batches' => [],
    ]);

    $exchange = ProductExchange::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 200,
        'net_amount' => 175,
        'paid_amount' => 25,
        'price_difference' => 25,
    ]);

    ProductExchangeProduct::query()->create([
        'branch_id' => $branch->id,
        'product_exchange_id' => $exchange->id,
        'sell_product_id' => $sellProduct->id,
        'old_product_id' => $oldProduct->id,
        'old_quantity' => 1,
        'old_unit_price' => 1000,
        'new_product_id' => $newProduct->id,
        'new_quantity' => 1,
        'new_unit_price' => 200,
        'new_line_discount' => 0,
    ]);

    $sell->load('productExchange.products.newProduct');
    $overlay = app(SellExchangeOverlayService::class);

    expect($overlay->effectiveNetAmount($sell))->toBe(810.0)
        ->and($overlay->effectivePaidAmount($sell))->toBe(810.0)
        ->and($overlay->effectiveProducts($sell))->toHaveCount(1)
        ->and($overlay->effectiveProducts($sell)[0]['product_id'])->toBe($newProduct->id)
        ->and((float) $sell->fresh()->gross_amount)->toBe(1000.0)
        ->and($sellProduct->fresh()->product_id)->toBe($oldProduct->id);
});

test('sales summary shows the post-exchange product and amount', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $oldProduct = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Old Shirt']);
    $newProduct = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'New Shirt']);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => '2026-07-07',
        'gross_amount' => 1000,
        'paid_amount' => 1000,
        'vat' => 0,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $oldProduct->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'discount' => 0,
        'batches' => [],
    ]);

    $exchange = ProductExchange::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => '2026-07-07',
        'gross_amount' => 200,
        'net_amount' => 200,
        'paid_amount' => 0,
        'price_difference' => -800,
    ]);

    ProductExchangeProduct::query()->create([
        'branch_id' => $branch->id,
        'product_exchange_id' => $exchange->id,
        'sell_product_id' => $sellProduct->id,
        'old_product_id' => $oldProduct->id,
        'old_quantity' => 1,
        'old_unit_price' => 1000,
        'new_product_id' => $newProduct->id,
        'new_quantity' => 1,
        'new_unit_price' => 200,
        'new_line_discount' => 0,
    ]);

    $result = app(ReportService::class)->salesSummary('2026-07-07', '2026-07-07', null, $branch->id);
    $rows = collect($result['rows']);
    $newRow = $rows->firstWhere('product', 'New Shirt');

    expect($newRow)->not->toBeNull()
        ->and($newRow['unit_price'])->toBe(200.0)
        ->and($newRow['line_total'])->toBe(200.0)
        ->and($rows->firstWhere('product', 'Old Shirt'))->toBeNull();
});

test('sell index exposes effective totals for exchanged sales', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('inventory.sell.view');

    $product = Product::factory()->create(['branch_id' => $branch->id]);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'gross_amount' => 1000,
        'discount' => 50,
        'special_discount_amount' => 100,
        'promotion_discount_total' => 75,
        'coin_discount_amount' => 25,
        'round_off_amount' => 10,
        'paid_amount' => 785,
        'vat' => 0,
    ]);

    $sellProduct = SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000,
        'discount' => 30,
        'batches' => [],
    ]);

    ProductExchange::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'sell_id' => $sell->id,
        'date' => now()->format('Y-m-d'),
        'gross_amount' => 200,
        'net_amount' => 175,
        'paid_amount' => 25,
        'price_difference' => 25,
    ]);

    ProductExchangeProduct::query()->create([
        'branch_id' => $branch->id,
        'product_exchange_id' => ProductExchange::query()->where('sell_id', $sell->id)->value('id'),
        'sell_product_id' => $sellProduct->id,
        'old_product_id' => $product->id,
        'old_quantity' => 1,
        'old_unit_price' => 1000,
        'new_product_id' => $product->id,
        'new_quantity' => 1,
        'new_unit_price' => 200,
        'new_line_discount' => 0,
    ]);

    $this->actingAs($user)
        ->get('/inventory/sell')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sells.data', 1)
            ->where('sells.data.0.has_exchange', true)
            ->where('sells.data.0.gross_amount', '1000.00')
            ->whereNot('sells.data.0.exchange_invoice_number', null));
});
