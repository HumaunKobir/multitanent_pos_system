<?php

use App\Enums\SystemAccountKey;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\ProductPhoto;
use App\Models\ProductVariation;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\SystemAccountService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function productUpdateAdmin(): User
{
    Permission::findOrCreate('product.update', 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.update');

    return $admin;
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
        'code' => fake()->unique()->numerify('########'),
    ]);

    ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
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
            ->has('ecommerceBranchId')
            ->has('colorOptions')
            ->has('sizeOptions')
            ->has('product.variations', 1)
            ->where('product.variations.0.variation_data.Color', 'Red')
            ->where('product.variations.0.variation_data.Size', 'S'));
});

test('product edit page keeps variants editable even when product has sales history', function () {
    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
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
            ->where('variantsLocked', false));
});

test('all branches product edit page includes catalog relations for form', function () {
    $admin = productUpdateAdmin();

    Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1, 'branch_id' => $mainBranchId]);
    $brand = Brand::factory()->create(['status' => 1, 'branch_id' => $mainBranchId]);
    $unit = Unit::query()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Edit Unit '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);
    $color = Color::query()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Edit Color '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);
    $size = Size::query()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Edit Size '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => (string) Str::uuid(),
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'unit_id' => $unit->id,
        'colors' => [$color->id],
        'sizes' => [$size->id],
        'code' => fake()->unique()->numerify('########'),
    ]);

    $this->actingAs($admin)
        ->get(route('product.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/edit')
            ->where('product.category_id', $category->id)
            ->where('product.brand_id', $brand->id)
            ->where('product.unit_id', $unit->id)
            ->where('product.category.name', $category->name)
            ->where('product.brand.name', $brand->name)
            ->where('product.unit.name', $unit->name)
            ->has('selectedColors', 1)
            ->has('selectedSizes', 1)
            ->where('defaultCatalogBranchId', $mainBranchId));
});

test('product update syncs product barcode when code changes', function () {
    $admin = productUpdateAdmin();
    $oldCode = fake()->unique()->numerify('########');
    $newCode = fake()->unique()->numerify('########');

    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $oldCode,
    ]);

    Barcode::query()->create([
        'branch_id' => $product->branch_id,
        'product_id' => $product->id,
        'product_variation_id' => null,
        'code' => $oldCode,
        'name' => $product->name,
    ]);

    $payload = productUpdatePayload($product, ['code' => $newCode]);

    $this->actingAs($admin)
        ->post(route('product.update', $product), array_merge($payload, ['_method' => 'patch']))
        ->assertRedirect(route('product.index'));

    expect($product->fresh()->code)->toBe($newCode)
        ->and(
            Barcode::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variation_id')
                ->value('code'),
        )->toBe($newCode);
});

test('product update syncs variation barcode when sku changes', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $oldSku = fake()->unique()->numerify('########');
    $newSku = fake()->unique()->numerify('########');

    $product = Product::createCatalogEntry([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'name' => 'Variant Barcode Edit '.fake()->unique()->numerify('######'),
        'purchase_price' => 0,
        'sale_price' => 0,
        'visible' => 'no',
        'status' => 1,
    ], withVariations: true);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'branch_id' => $product->branch_id,
        'sku' => $oldSku,
        'price' => 200,
        'purchase_price' => 120,
        'stock' => 5,
        'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
    ]);

    Barcode::query()->create([
        'branch_id' => $product->branch_id,
        'product_id' => $product->id,
        'product_variation_id' => $variation->id,
        'code' => $oldSku,
        'name' => $product->name.' - Red-S',
    ]);

    $payload = productUpdatePayload($product, [
        'code' => '',
        'purchase_price' => '0',
        'sale_price' => '0',
        'combinations' => [
            [
                'id' => $variation->id,
                'variant' => 'Red-S',
                'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
                'sale_price' => '220',
                'purchase_price' => '130',
                'sku' => $newSku,
                'stock' => '7',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'));

    $variation->refresh();

    expect($product->fresh()->code)->toBeNull()
        ->and($variation->sku)->toBe($newSku)
        ->and(
            Barcode::query()
                ->where('product_variation_id', $variation->id)
                ->value('code'),
        )->toBe($newSku)
        ->and(
            Barcode::query()
                ->where('product_id', $product->id)
                ->whereNull('product_variation_id')
                ->exists(),
        )->toBeFalse();
});

test('product update syncs variations when not locked', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $admin = productUpdateAdmin();
    $sku = fake()->unique()->numerify('########');

    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 0,
        'sale_price' => 0,
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => $sku,
        'price' => 200,
        'purchase_price' => 120,
        'stock' => 5,
        'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
    ]);

    $payload = productUpdatePayload($product, [
        'code' => '',
        'purchase_price' => '0',
        'sale_price' => '0',
        'combinations' => [
            [
                'id' => $variation->id,
                'variant' => 'Red-S',
                'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
                'sale_price' => '220',
                'purchase_price' => '130',
                'sku' => $sku,
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
        'code' => fake()->unique()->numerify('########'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
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

test('product update can update supplier settlement when product has sales history', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $admin = productUpdateAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
        'initial_stock_supplier_id' => $supplier->id,
        'initial_stock_paid_amount' => 200,
        'initial_stock_payment_account_id' => $cash->id,
    ]);

    ProductInitialStock::query()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 100,
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
        'price' => 150,
        'purchase_price' => 100,
        'stock' => 10,
        'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
    ]);

    $sell = Sell::query()->create([
        'date' => now()->toDateString(),
        'gross_amount' => 150,
        'paid_amount' => 150,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'variation_id' => $variation->id,
        'quantity' => 1,
        'unit_price' => 150,
    ]);

    $supplier->update(['balance' => 800]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '100',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '500',
            'initial_stock_payment_account_id' => (string) $cash->id,
            'combinations' => [
                [
                    'id' => $variation->id,
                    'variant' => 'Red-S',
                    'variation_data' => ['label' => 'Red-S', 'Color' => 'Red', 'Size' => 'S'],
                    'sale_price' => '999',
                    'purchase_price' => '888',
                    'sku' => $variation->sku,
                    'stock' => '99',
                ],
            ],
        ]))
        ->assertRedirect(route('product.index'));

    $product->refresh();
    $variation->refresh();

    expect((float) $product->initial_stock_paid_amount)->toBe(500.0)
        ->and((float) $variation->price)->toBe(999.0)
        ->and((float) $variation->purchase_price)->toBe(888.0)
        ->and($variation->stock)->toBe(99)
        ->and((float) $supplier->fresh()->balance)->toBeGreaterThan(0.0);

    $transactions = Transaction::query()
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->get();

    expect($transactions)->toHaveCount(1);
});

test('product update still syncs variation changes after sales history exists', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
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
                'sku' => fake()->unique()->numerify('########'),
                'stock' => '99',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'));

    $variation->refresh();

    expect((float) $variation->price)->toBe(999.0)
        ->and((float) $variation->purchase_price)->toBe(888.0)
        ->and($variation->stock)->toBe(99);
});

test('product update syncs non-variant initial stock with accounting', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    $createPayload = productUpdatePayload($product, [
        'initial_stock' => '10',
        'purchase_price' => '100',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $createPayload)
        ->assertRedirect(route('product.index'));

    $batch = Batch::query()->where('product_id', $product->id)->first();
    expect((float) $batch->available)->toBe(10.0);

    $updatePayload = productUpdatePayload($product, [
        'initial_stock' => '15',
        'purchase_price' => '100',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $updatePayload)
        ->assertRedirect(route('product.index'));

    $batch->refresh();
    expect((float) $batch->available)->toBe(15.0);

    $record = ProductInitialStock::query()
        ->where('product_id', $product->id)
        ->whereNull('product_variation_id')
        ->first();

    expect($record->quantity)->toBe(15);

    $transactions = Transaction::query()
        ->where('source_type', ProductInitialStock::class)
        ->where('source_id', $record->id)
        ->get();

    expect($transactions)->toHaveCount(2);
});

test('product update initial stock matches on hand without double reducing batch stock', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 50,
        'sale_price' => 80,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '50',
        ]))
        ->assertRedirect(route('product.index'));

    $batch = Batch::query()->where('product_id', $product->id)->firstOrFail();
    $batch->decrement('available', 3);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '7',
            'purchase_price' => '50',
        ]))
        ->assertRedirect(route('product.index'));

    $batch->refresh();

    expect((float) $batch->available)->toBe(7.0)
        ->and((int) ProductInitialStock::query()->where('product_id', $product->id)->value('quantity'))->toBe(7);
});

test('product update opening stock without supplier posts to product inventory and owners capital', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, Branch::MAIN_BRANCH_ID);
    $capital = SystemAccountService::resolve(SystemAccountKey::OwnersCapital, Branch::MAIN_BRANCH_ID);

    $initialInventory = (float) $inventory->fresh()->current_balance;
    $initialCapital = (float) $capital->fresh()->current_balance;

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '100',
        ]))
        ->assertRedirect(route('product.index'));

    expect((float) $inventory->fresh()->current_balance)->toBe(round($initialInventory + 1000, 2))
        ->and((float) $capital->fresh()->current_balance)->toBe(round($initialCapital + 1000, 2));

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '12',
            'purchase_price' => '100',
        ]))
        ->assertRedirect(route('product.index'));

    expect((float) $inventory->fresh()->current_balance)->toBe(round($initialInventory + 1200, 2))
        ->and((float) $capital->fresh()->current_balance)->toBe(round($initialCapital + 1200, 2));
});

test('product update can add supplier settlement to existing initial stock', function () {
    $admin = productUpdateAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '100',
        ]))
        ->assertRedirect(route('product.index'));

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '100',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '400',
            'initial_stock_payment_account_id' => (string) $cash->id,
        ]))
        ->assertRedirect(route('product.index'));

    $product->refresh();

    expect($product->initial_stock_supplier_id)->toBe($supplier->id)
        ->and((float) $product->initial_stock_paid_amount)->toBe(400.0)
        ->and((float) $supplier->fresh()->balance)->toBe(600.0);

    $transaction = Transaction::query()
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->first();

    expect($transaction)->not->toBeNull();
});

test('adding supplier settlement reverses all opening balance journals without double counting inventory', function () {
    $admin = productUpdateAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '100',
        ]))
        ->assertRedirect(route('product.index'));

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '15',
            'purchase_price' => '100',
        ]))
        ->assertRedirect(route('product.index'));

    $record = ProductInitialStock::query()
        ->where('product_id', $product->id)
        ->whereNull('product_variation_id')
        ->first();

    expect(Transaction::query()
        ->where('source_type', ProductInitialStock::class)
        ->where('source_id', $record->id)
        ->count())->toBe(2);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '15',
            'purchase_price' => '100',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '500',
            'initial_stock_payment_account_id' => (string) $cash->id,
        ]))
        ->assertRedirect(route('product.index'));

    expect(Transaction::query()
        ->where('source_type', ProductInitialStock::class)
        ->where('source_id', $record->id)
        ->count())->toBe(0);

    $transaction = Transaction::query()
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->sole();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();

    expect(round($ledgers->sum('debit'), 2))->toBe(1500.0)
        ->and(round($ledgers->sum('credit'), 2))->toBe(1500.0)
        ->and((float) $supplier->fresh()->balance)->toBe(1000.0);
});

test('supplier settlement edit replaces prior journal instead of duplicating inventory', function () {
    $admin = productUpdateAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '10',
            'purchase_price' => '100',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '1000',
            'initial_stock_payment_account_id' => (string) $cash->id,
        ]))
        ->assertRedirect(route('product.index'));

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'initial_stock' => '20',
            'purchase_price' => '100',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '1000',
            'initial_stock_payment_account_id' => (string) $cash->id,
        ]))
        ->assertRedirect(route('product.index'));

    $transactions = Transaction::query()
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->get();

    expect($transactions)->toHaveCount(1);

    $ledgers = Ledger::query()->where('transaction_id', $transactions->first()->id)->get();

    expect(round($ledgers->sum('debit'), 2))->toBe(2000.0)
        ->and(round($ledgers->sum('credit'), 2))->toBe(2000.0)
        ->and((float) $supplier->fresh()->balance)->toBe(1000.0);
});

test('product update applies global initial stock to new variation without stock', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $sku = fake()->unique()->numerify('########');

    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 0,
        'sale_price' => 0,
    ]);

    $payload = productUpdatePayload($product, [
        'code' => '',
        'purchase_price' => '0',
        'sale_price' => '0',
        'initial_stock' => '15',
        'combinations' => [
            [
                'variant' => 'Black-XL',
                'variation_data' => ['label' => 'Black-XL', 'Color' => 'Black', 'Size' => 'XL'],
                'sale_price' => '250',
                'purchase_price' => '140',
                'sku' => $sku,
                'stock' => '',
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'));

    $variation = ProductVariation::query()->where('product_id', $product->id)->first();

    expect($variation)->not->toBeNull()
        ->and($variation->stock)->toBe(15);
});

test('updating main branch product to specific branch creates copy with same stock', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'Copy Branch Product '.fake()->unique()->numerify('######');
    $manualCode = fake()->unique()->numerify('########');

    $category = Category::factory()->create(['status' => 1, 'branch_id' => Branch::MAIN_BRANCH_ID]);
    $brand = Brand::factory()->create(['status' => 1, 'branch_id' => Branch::MAIN_BRANCH_ID]);
    $unit = Unit::query()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'name' => 'Unit '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $mainBranchId = Branch::resolveMainBranchId();

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => (string) $mainBranchId,
            'category_id' => (string) $category->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'code' => $manualCode,
            'initial_stock' => '30',
            'purchase_price' => '300',
            'sale_price' => '500',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $mainProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', $mainBranchId)
        ->firstOrFail();

    $mainBatch = Batch::query()->where('product_id', $mainProduct->id)->first();
    expect($mainBatch)->not->toBeNull()
        ->and((float) $mainBatch->available)->toBe(30.0);

    $payload = productUpdatePayload($mainProduct, [
        'branch_id' => (string) $operatingBranch->id,
        'initial_stock' => '30',
        'purchase_price' => '300',
        'sale_price' => '500',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $mainProduct), $payload)
        ->assertRedirect(route('product.index'));

    $copies = Product::query()->where('name', $productName)->get();

    expect($copies)->toHaveCount(2);

    $mainProduct->refresh();
    $branchCopy = $copies->firstWhere('branch_id', $operatingBranch->id);

    expect($mainProduct->branch_id)->toBe($mainBranchId)
        ->and($mainProduct->selected_branch_id)->toBe($operatingBranch->id)
        ->and($branchCopy)->not->toBeNull()
        ->and($branchCopy->name)->toBe($productName)
        ->and((float) $branchCopy->purchase_price)->toBe(300.0)
        ->and((float) $branchCopy->sale_price)->toBe(500.0)
        ->and($branchCopy->selected_branch_id)->toBeNull();

    $branchBatch = Batch::query()->where('product_id', $branchCopy->id)->first();
    expect($branchBatch)->not->toBeNull()
        ->and((float) $branchBatch->available)->toBe(30.0);

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => (string) $operatingBranch->id, 'search' => $productName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.batches_sum_available', '30.00'));

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => (string) $mainBranchId, 'search' => $productName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.batches_sum_available', '30.00'));
});

test('product update allows unchanged name for all branch group siblings', function () {
    $admin = productUpdateAdmin();
    $groupId = (string) Str::uuid();
    $productName = 'Grouped Name Product '.fake()->unique()->numerify('######');

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $productA = Product::factory()->create([
        'branch_id' => $branchA->id,
        'product_group_id' => $groupId,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'name' => $productName,
        'code' => fake()->unique()->numerify('########'),
        'sale_price' => 300,
    ]);

    Product::factory()->create([
        'branch_id' => $branchB->id,
        'product_group_id' => $groupId,
        'category_id' => $productA->category_id,
        'brand_id' => $productA->brand_id,
        'unit_id' => $productA->unit_id,
        'name' => $productName,
        'code' => fake()->unique()->numerify('########'),
        'sale_price' => 300,
    ]);

    $payload = productUpdatePayload($productA, [
        'branch_id' => (string) $productA->branch_id,
        'name' => $productName,
        'sale_price' => '350',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $productA), $payload)
        ->assertRedirect(route('product.index'))
        ->assertSessionHasNoErrors();

    expect((float) $productA->fresh()->sale_price)->toBe(350.0);
});

test('product update still rejects duplicate name outside product group', function () {
    $admin = productUpdateAdmin();
    $groupId = (string) Str::uuid();
    $sharedName = 'Taken Outside Group '.fake()->unique()->numerify('######');

    $productA = Product::factory()->create([
        'branch_id' => Branch::factory()->create()->id,
        'product_group_id' => $groupId,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'name' => 'Grouped Product '.fake()->unique()->numerify('######'),
        'code' => fake()->unique()->numerify('########'),
    ]);

    Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'name' => $sharedName,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $payload = productUpdatePayload($productA, [
        'name' => $sharedName,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $productA), $payload)
        ->assertSessionHasErrors('name');
});

test('product edit page exposes selected branch for form', function () {
    $admin = productUpdateAdmin();
    $operatingBranch = Branch::factory()->create();

    $product = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'selected_branch_id' => $operatingBranch->id,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $this->actingAs($admin)
        ->get(route('product.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('formBranchId', (string) $operatingBranch->id));
});

test('updating all branches product to specific branch removes other branch copies', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'Narrow Branch Product '.fake()->unique()->numerify('######');
    $manualCode = fake()->unique()->numerify('########');

    $category = Category::factory()->create(['status' => 1, 'branch_id' => Branch::MAIN_BRANCH_ID]);
    $brand = Brand::factory()->create(['status' => 1, 'branch_id' => Branch::MAIN_BRANCH_ID]);
    $unit = Unit::query()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'name' => 'Unit '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => 'all',
            'category_id' => (string) $category->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'code' => $manualCode,
            'initial_stock' => '10',
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $mainProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', Branch::resolveMainBranchId())
        ->firstOrFail();

    expect(Product::query()->where('name', $productName)->count())->toBeGreaterThan(2);

    $payload = productUpdatePayload($mainProduct, [
        'branch_id' => (string) $operatingBranch->id,
        'initial_stock' => '10',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $mainProduct), $payload)
        ->assertRedirect(route('product.index'));

    $copies = Product::query()->where('name', $productName)->get();

    expect($copies)->toHaveCount(2)
        ->and($copies->pluck('branch_id')->sort()->values()->all())
        ->toBe(collect([Branch::resolveMainBranchId(), $operatingBranch->id])->sort()->values()->all())
        ->and($mainProduct->fresh()->selected_branch_id)->toBe($operatingBranch->id)
        ->and($copies->firstWhere('branch_id', $operatingBranch->id)?->selected_branch_id)->toBeNull();
});

test('narrowing branch selection is blocked when another branch has sales history', function () {
    $admin = productUpdateAdmin();

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $branchWithSale = Branch::factory()->create();
    $targetBranch = Branch::factory()->create();
    $groupId = (string) Str::uuid();
    $productName = 'Blocked Narrow Product '.fake()->unique()->numerify('######');

    $categoryId = Category::factory()->create(['status' => 1])->id;
    $brandId = Brand::factory()->create(['status' => 1])->id;
    $unitId = Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id;

    $mainProduct = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'product_group_id' => $groupId,
        'selected_branch_id' => null,
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'unit_id' => $unitId,
        'name' => $productName,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $soldBranchProduct = Product::factory()->create([
        'branch_id' => $branchWithSale->id,
        'product_group_id' => $groupId,
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'unit_id' => $unitId,
        'name' => $productName,
        'code' => fake()->unique()->numerify('########'),
    ]);

    Product::factory()->create([
        'branch_id' => $targetBranch->id,
        'product_group_id' => $groupId,
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'unit_id' => $unitId,
        'name' => $productName,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $sell = Sell::query()->create([
        'date' => now()->toDateString(),
        'gross_amount' => 150,
        'paid_amount' => 150,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'product_id' => $soldBranchProduct->id,
        'quantity' => 1,
        'unit_price' => 150,
        'total_price' => 150,
    ]);

    $payload = productUpdatePayload($mainProduct, [
        'branch_id' => (string) $targetBranch->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $mainProduct), $payload)
        ->assertSessionHasErrors('branch_id');

    expect(Product::query()->where('name', $productName)->count())->toBe(3);
});

test('updating specific branch product to all branches creates missing branch copies', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'Expand All Branch Product '.fake()->unique()->numerify('######');
    $manualCode = fake()->unique()->numerify('########');

    $category = Category::factory()->create(['status' => 1, 'branch_id' => Branch::MAIN_BRANCH_ID]);
    $brand = Brand::factory()->create(['status' => 1, 'branch_id' => Branch::MAIN_BRANCH_ID]);
    $unit = Unit::query()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'name' => 'Unit '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $mainBranchId = Branch::resolveMainBranchId();

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => (string) $mainBranchId,
            'category_id' => (string) $category->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'code' => $manualCode,
            'initial_stock' => '20',
            'purchase_price' => '200',
            'sale_price' => '300',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $mainProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', $mainBranchId)
        ->firstOrFail();

    $payload = productUpdatePayload($mainProduct, [
        'branch_id' => (string) $operatingBranch->id,
        'initial_stock' => '20',
        'purchase_price' => '200',
        'sale_price' => '300',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $mainProduct), $payload)
        ->assertRedirect(route('product.index'));

    expect(Product::query()->where('name', $productName)->count())->toBe(2);

    $expandPayload = productUpdatePayload($mainProduct->fresh(), [
        'branch_id' => 'all',
        'initial_stock' => '20',
        'purchase_price' => '200',
        'sale_price' => '300',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $mainProduct->fresh()), $expandPayload)
        ->assertRedirect(route('product.index'));

    $activeBranchCount = Branch::query()->active()->count();
    $products = Product::query()->where('name', $productName)->get();

    expect($products)->toHaveCount($activeBranchCount)
        ->and($mainProduct->fresh()->selected_branch_id)->toBeNull()
        ->and($products->pluck('product_group_id')->unique())->toHaveCount(1)
        ->and($products->pluck('branch_id')->sort()->values()->all())
        ->toBe(Branch::query()->active()->orderBy('id')->pluck('id')->sort()->values()->all());
});

test('product update with image via method spoofing accepts required fields', function () {
    Storage::fake('public');

    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'image' => null,
    ]);

    $payload = productUpdatePayload($product, [
        '_method' => 'patch',
        'image' => UploadedFile::fake()->image('product.jpg', 400, 400),
    ]);

    $this->actingAs($admin)
        ->post(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'))
        ->assertSessionDoesntHaveErrors(['category_id', 'brand_id', 'unit_id', 'name']);

    $product->refresh();

    expect($product->image)->not->toBeNull()
        ->and($product->image)->toStartWith('products/');

    Storage::disk('public')->assertExists($product->image);
});

test('product update appends gallery photos via method spoofing', function () {
    Storage::fake('public');

    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    ProductPhoto::query()->create([
        'product_id' => $product->id,
        'image' => 'products/photos/existing.jpg',
    ]);

    $payload = productUpdatePayload($product, [
        '_method' => 'patch',
        'photos' => [
            UploadedFile::fake()->image('new-gallery.jpg', 400, 400),
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('product.update', $product), $payload)
        ->assertRedirect(route('product.index'))
        ->assertSessionHasNoErrors();

    $product->load('photos');

    expect($product->photos)->toHaveCount(2)
        ->and($product->photos->pluck('image')->contains(fn ($path) => str_starts_with($path, 'products/photos/')))->toBeTrue();
});

test('product gallery photo can be deleted', function () {
    Storage::fake('public');

    $admin = productUpdateAdmin();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    Storage::disk('public')->put('products/photos/to-delete.jpg', 'fake-image');

    $photo = ProductPhoto::query()->create([
        'product_id' => $product->id,
        'image' => 'products/photos/to-delete.jpg',
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('product.photo.destroy', $photo))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(ProductPhoto::query()->whereKey($photo->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('products/photos/to-delete.jpg');
});

test('grouped product edit page defaults branch field to product branch', function () {
    $admin = productUpdateAdmin();
    $operatingBranch = Branch::factory()->create();
    $groupId = (string) Str::uuid();

    $product = Product::factory()->create([
        'branch_id' => $operatingBranch->id,
        'product_group_id' => $groupId,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $this->actingAs($admin)
        ->get(route('product.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('formBranchId', (string) $operatingBranch->id));
});

test('updating grouped product without selecting all branches does not create extra branch copies', function () {
    $admin = productUpdateAdmin();

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $dressShop = Branch::factory()->create(['name' => 'Dress Shop Test '.fake()->unique()->numerify('###')]);
    $coolnessPoint = Branch::factory()->create(['name' => 'Coolness Point Test '.fake()->unique()->numerify('###')]);
    $groupId = (string) Str::uuid();
    $productName = 'Branch Scoped Edit '.fake()->unique()->numerify('######');

    $dressProduct = Product::factory()->create([
        'branch_id' => $dressShop->id,
        'product_group_id' => $groupId,
        'name' => $productName,
        'category_id' => Category::factory()->create(['status' => 1, 'branch_id' => $dressShop->id])->id,
        'brand_id' => Brand::factory()->create(['status' => 1, 'branch_id' => $dressShop->id])->id,
        'unit_id' => Unit::query()->create(['branch_id' => $dressShop->id, 'name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'product_group_id' => $groupId,
        'source_branch_id' => $dressShop->id,
        'name' => $productName,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $dressProduct), productUpdatePayload($dressProduct, [
            'branch_id' => (string) $dressShop->id,
            'name' => $productName.' Updated',
            'sale_price' => '175',
        ]))
        ->assertRedirect(route('product.index'));

    expect(Product::query()->where('name', $productName.' Updated')->count())->toBe(2)
        ->and(Product::query()->where('name', $productName.' Updated')->where('branch_id', $coolnessPoint->id)->exists())->toBeFalse();
});

test('product update purchase price change adjusts batch valuation and supplier payable', function () {
    $admin = productUpdateAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'balance' => 600]);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 100,
        'sale_price' => 150,
        'initial_stock_supplier_id' => $supplier->id,
        'initial_stock_paid_amount' => 400,
        'initial_stock_payment_account_id' => $cash->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'branch_id' => (string) Branch::MAIN_BRANCH_ID,
            'initial_stock' => '10',
            'purchase_price' => '100',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '400',
            'initial_stock_payment_account_id' => (string) $cash->id,
        ]))
        ->assertRedirect(route('product.index'));

    $this->actingAs($admin)
        ->patch(route('product.update', $product), productUpdatePayload($product, [
            'branch_id' => (string) Branch::MAIN_BRANCH_ID,
            'initial_stock' => '10',
            'purchase_price' => '120',
            'initial_stock_supplier_id' => (string) $supplier->id,
            'initial_stock_paid_amount' => '400',
            'initial_stock_payment_account_id' => (string) $cash->id,
        ]))
        ->assertRedirect(route('product.index'));

    $batch = Batch::query()->where('product_id', $product->id)->first();
    $record = ProductInitialStock::query()->where('product_id', $product->id)->first();

    expect((float) $batch->purchase_price)->toBe(120.0)
        ->and((float) $record->unit_cost)->toBe(120.0)
        ->and((float) $supplier->fresh()->balance)->toBe(800.0);

    $transaction = Transaction::query()
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->first();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();

    expect(round($ledgers->sum('debit'), 2))->toBe(1200.0)
        ->and(round($ledgers->sum('credit'), 2))->toBe(1200.0);
});
