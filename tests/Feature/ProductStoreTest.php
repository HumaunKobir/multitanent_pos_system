<?php

use App\Models\Barcode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use App\Models\Unit;
use App\Models\User;

function productStoreAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function validProductPayload(array $overrides = []): array
{
    return array_merge([
        'category_id' => (string) Category::factory()->create(['status' => 1])->id,
        'brand_id' => (string) Brand::factory()->create(['status' => 1])->id,
        'unit_id' => (string) Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'name' => 'Product '.fake()->unique()->numerify('######'),
        'purchase_price' => '100',
        'sale_price' => '150',
        'visible' => 'no',
        'status' => '1',
    ], $overrides);
}

test('product without manual code gets auto-generated code and barcode', function () {
    $admin = productStoreAdmin();
    $payload = validProductPayload(['code' => '']);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->code)->toStartWith('PRD-');

    expect(
        Barcode::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->where('code', $product->code)
            ->exists(),
    )->toBeTrue();
});

test('product with manual code uses provided code for barcode', function () {
    $admin = productStoreAdmin();
    $manualCode = 'MAN-'.fake()->unique()->numerify('######');
    $payload = validProductPayload(['code' => $manualCode]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->code)->toBe($manualCode);

    expect(
        Barcode::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variation_id')
            ->where('code', $manualCode)
            ->exists(),
    )->toBeTrue();
});

test('product store saves multiple colors and sizes for non-variant product', function () {
    $admin = productStoreAdmin();
    $colorOne = Color::query()->create(['name' => 'Red '.fake()->unique()->numerify('####'), 'status' => 1]);
    $colorTwo = Color::query()->create(['name' => 'Blue '.fake()->unique()->numerify('####'), 'status' => 1]);
    $sizeOne = Size::query()->create(['name' => 'M '.fake()->unique()->numerify('####'), 'status' => 1]);
    $sizeTwo = Size::query()->create(['name' => 'L '.fake()->unique()->numerify('####'), 'status' => 1]);

    $payload = validProductPayload([
        'color_ids' => [(string) $colorOne->id, (string) $colorTwo->id],
        'size_ids' => [(string) $sizeOne->id, (string) $sizeTwo->id],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->colors)->toBe([$colorOne->id, $colorTwo->id])
        ->and($product->sizes)->toBe([$sizeOne->id, $sizeTwo->id]);
});

test('product store clears colors and sizes when variations are present', function () {
    $admin = productStoreAdmin();
    $color = Color::query()->create(['name' => 'Blue '.fake()->unique()->numerify('####'), 'status' => 1]);
    $size = Size::query()->create(['name' => 'L '.fake()->unique()->numerify('####'), 'status' => 1]);

    $payload = validProductPayload([
        'color_ids' => [(string) $color->id],
        'size_ids' => [(string) $size->id],
        'purchase_price' => '0',
        'sale_price' => '0',
        'combinations' => [
            [
                'variant' => 'Blue-L',
                'variation_data' => ['label' => 'Blue-L', 'Color' => 'Blue', 'Size' => 'L'],
                'sale_price' => '200',
                'purchase_price' => '120',
                'sku' => 'VAR-'.fake()->unique()->numerify('######'),
                'stock' => '5',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->colors)->toBeNull()
        ->and($product->sizes)->toBeNull();
});

test('auto-generated product codes are unique', function () {
    $admin = productStoreAdmin();

    $firstPayload = validProductPayload(['code' => '']);
    $secondPayload = validProductPayload(['code' => '']);

    $this->actingAs($admin)->post(route('product.store'), $firstPayload)->assertRedirect();
    $this->actingAs($admin)->post(route('product.store'), $secondPayload)->assertRedirect();

    $first = Product::query()->where('name', $firstPayload['name'])->first();
    $second = Product::query()->where('name', $secondPayload['name'])->first();

    expect($first->code)->not->toBe($second->code);
});
