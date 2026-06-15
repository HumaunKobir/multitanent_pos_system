<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\Size;
use App\Models\Unit;
use App\Models\User;

function productUpdateAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function productUpdatePayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'category_id' => (string) $product->category_id,
        'brand_id' => (string) $product->brand_id,
        'unit_id' => (string) $product->unit_id,
        'name' => $product->name,
        'code' => $product->code,
        'purchase_price' => '100',
        'sale_price' => '150',
        'visible' => 'yes',
        'status' => '1',
    ], $overrides);
}

test('product edit page includes variant data and lock flag', function () {
    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'EDIT-'.fake()->unique()->numerify('######'),
    ]);

    ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => $product->code.'-RED-S',
        'price' => 200,
        'purchase_price' => 120,
        'stock' => 5,
        'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
    ]);

    $this->actingAs($admin)
        ->get(route('product.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/edit')
            ->where('variantsLocked', false)
            ->has('colorOptions')
            ->has('sizeOptions')
            ->has('product.variations', 1)
            ->where('product.variations.0.variation_data.Color', 'Red')
            ->where('product.variations.0.variation_data.Size', 'S'));
});

test('product edit page locks variants when product has sales history', function () {
    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'LOCK-'.fake()->unique()->numerify('######'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => $product->code.'-BLUE-M',
        'price' => 180,
        'purchase_price' => 110,
        'stock' => 2,
        'variation_data' => ['label' => 'Blue-M', 'Color' => 'Blue', 'Size' => 'M'],
    ]);

    $sell = Sell::query()->create([
        'date' => now()->toDateString(),
        'gross_amount' => 180,
        'paid_amount' => 180,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 180,
    ]);

    $this->actingAs($admin)
        ->get(route('product.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/edit')
            ->where('variantsLocked', true));
});

test('product update syncs variations when not locked', function () {
    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'SYNC-'.fake()->unique()->numerify('######'),
        'purchase_price' => 0,
        'sale_price' => 0,
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => $product->code.'-RED-S',
        'price' => 200,
        'purchase_price' => 120,
        'stock' => 5,
        'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
    ]);

    $payload = productUpdatePayload($product, [
        'purchase_price' => '0',
        'sale_price' => '0',
        'combinations' => [
            [
                'id' => $variation->id,
                'variant' => 'Red-S',
                'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
                'sale_price' => '220',
                'purchase_price' => '130',
                'sku' => $product->code.'-RED-S',
                'stock' => '7',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'));

    $variation->refresh();

    expect((float) $variation->price)->toBe(220.0)
        ->and((float) $variation->purchase_price)->toBe(130.0)
        ->and($variation->stock)->toBe(7);
});

test('product update saves multiple colors and sizes even when variants are locked', function () {
    $admin = productUpdateAdmin();
    $colorOne = Color::query()->create(['name' => 'Green '.fake()->unique()->numerify('####'), 'status' => 1]);
    $colorTwo = Color::query()->create(['name' => 'Yellow '.fake()->unique()->numerify('####'), 'status' => 1]);
    $sizeOne = Size::query()->create(['name' => 'XL '.fake()->unique()->numerify('####'), 'status' => 1]);
    $sizeTwo = Size::query()->create(['name' => 'XXL '.fake()->unique()->numerify('####'), 'status' => 1]);

    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'COLOR-'.fake()->unique()->numerify('######'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => $product->code.'-GREEN-L',
        'price' => 190,
        'purchase_price' => 115,
        'stock' => 4,
        'variation_data' => ['label' => 'Green-L', 'Color' => 'Green', 'Size' => 'L'],
    ]);

    $sell = Sell::query()->create([
        'date' => now()->toDateString(),
        'gross_amount' => 190,
        'paid_amount' => 190,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 190,
    ]);

    $payload = productUpdatePayload($product, [
        'color_ids' => [(string) $colorOne->id, (string) $colorTwo->id],
        'size_ids' => [(string) $sizeOne->id, (string) $sizeTwo->id],
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'));

    $product->refresh();

    expect($product->colors)->toBe([$colorOne->id, $colorTwo->id])
        ->and($product->sizes)->toBe([$sizeOne->id, $sizeTwo->id]);
});

test('product update ignores variation changes when locked', function () {
    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'IGNORE-'.fake()->unique()->numerify('######'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => $product->code.'-GREEN-L',
        'price' => 190,
        'purchase_price' => 115,
        'stock' => 4,
        'variation_data' => ['label' => 'Green-L', 'Color' => 'Green', 'Size' => 'L'],
    ]);

    $sell = Sell::query()->create([
        'date' => now()->toDateString(),
        'gross_amount' => 190,
        'paid_amount' => 190,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 190,
    ]);

    $payload = productUpdatePayload($product, [
        'combinations' => [
            [
                'id' => $variation->id,
                'variant' => 'Green-L',
                'variation_data' => ['label' => 'Green-L', 'Color' => 'Green', 'Size' => 'L'],
                'sale_price' => '999',
                'purchase_price' => '888',
                'sku' => $product->code.'-GREEN-L',
                'stock' => '99',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'));

    $variation->refresh();

    expect((float) $variation->price)->toBe(190.0)
        ->and((float) $variation->purchase_price)->toBe(115.0)
        ->and($variation->stock)->toBe(4);
});
