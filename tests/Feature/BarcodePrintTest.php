<?php

use App\Models\Barcode;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Unit;
use App\Models\User;

function barcodePrintAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('barcode print page includes variation price for variant products', function () {
    $admin = barcodePrintAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'BC-'.fake()->unique()->numerify('######'),
        'sale_price' => 0,
        'discount_price' => 0,
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => 'BLACK-S-30',
        'price' => 350,
        'purchase_price' => 200,
        'stock' => 10,
        'variation_data' => ['label' => 'Black-S / 30'],
    ]);

    $barcode = Barcode::query()->create([
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'code' => 'BLACK-S-30',
        'name' => $product->name.' - Black-S / 30',
    ]);

    $this->actingAs($admin)
        ->get(route('barcode.print', ['ids' => $barcode->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/print')
            ->has('barcodes', 1)
            ->where('barcodes.0.variation.price', '350.00')
            ->where('barcodes.0.product.sale_price', '0.00'));
});

test('barcode print page includes product sale price for simple products', function () {
    $admin = barcodePrintAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'SIMPLE-'.fake()->unique()->numerify('######'),
        'sale_price' => 275,
        'discount_price' => 0,
    ]);

    $barcode = Barcode::query()->create([
        'product_id' => $product->id,
        'product_variation_id' => null,
        'code' => $product->code,
        'name' => $product->name,
    ]);

    $this->actingAs($admin)
        ->get(route('barcode.print', ['ids' => $barcode->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/print')
            ->has('barcodes', 1)
            ->where('barcodes.0.product.sale_price', '275.00')
            ->where('barcodes.0.variation', null));
});
