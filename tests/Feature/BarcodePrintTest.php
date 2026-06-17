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

test('barcode index returns paginated barcodes', function () {
    $admin = barcodePrintAdmin();

    $this->actingAs($admin)
        ->get(route('barcode.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/index')
            ->has('barcodes.data')
            ->has('barcodes.links')
            ->where('barcodes.per_page', 10));

    $admin->delete();
});

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

test('barcode serial range endpoint returns ids for list position range', function () {
    $admin = barcodePrintAdmin();
    $prefix = 'SR-'.uniqid();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix,
        'sale_price' => 100,
    ]);

    $orderedIds = [];

    for ($i = 0; $i < 5; $i++) {
        $barcode = Barcode::query()->create([
            'product_id' => $product->id,
            'product_variation_id' => null,
            'code' => $prefix.'-'.$i,
            'name' => $prefix.' Item '.$i,
            'created_at' => now()->subMinutes(5 - $i),
            'updated_at' => now()->subMinutes(5 - $i),
        ]);

        $orderedIds[] = $barcode->id;
    }

    $this->actingAs($admin)
        ->getJson(route('barcode.serial-range', ['from' => 2, 'to' => 3, 'search' => $prefix]))
        ->assertOk()
        ->assertJson([
            'ids' => Barcode::query()
                ->where(function ($q) use ($prefix) {
                    $q->where('code', 'like', "%{$prefix}%")
                        ->orWhere('name', 'like', "%{$prefix}%");
                })
                ->listed()
                ->skip(1)
                ->take(2)
                ->pluck('id')
                ->all(),
        ]);

    $this->actingAs($admin)
        ->getJson(route('barcode.serial-range', ['from' => 10, 'to' => 20, 'search' => $prefix]))
        ->assertOk()
        ->assertJson(['ids' => []]);

    Barcode::query()->whereIn('id', $orderedIds)->delete();
    $product->delete();
    $admin->delete();
});
