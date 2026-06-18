<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\ProductVariation;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\Size;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

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
        'branch_id' => $product->branch_id,
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
            ->has('ecommerceBranchId')
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
        'code' => 'edit-group-'.fake()->unique()->numerify('######'),
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

test('product update syncs non-variant initial stock with accounting', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $product = Product::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'INIT-'.fake()->unique()->numerify('######'),
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

test('product update applies global initial stock to new variation without stock', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'NEWVAR-'.fake()->unique()->numerify('######'),
        'purchase_price' => 0,
        'sale_price' => 0,
    ]);

    $payload = productUpdatePayload($product, [
        'purchase_price' => '0',
        'sale_price' => '0',
        'initial_stock' => '15',
        'combinations' => [
            [
                'variant' => 'Black-XL',
                'variation_data' => ['label' => 'Black-XL', 'Color' => 'Black', 'Size' => 'XL'],
                'sale_price' => '250',
                'purchase_price' => '140',
                'sku' => $product->code.'-BLACK-XL',
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

test('updating single branch product to all branches keeps source branch copy and stock', function () {
    $admin = productUpdateAdmin();
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $operatingBranch = Branch::factory()->create();
    $productName = 'Expand Branch Product '.fake()->unique()->numerify('######');
    $manualCode = 'EXP-'.fake()->unique()->numerify('######');

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
            'branch_id' => (string) $operatingBranch->id,
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

    $branchProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', $operatingBranch->id)
        ->firstOrFail();

    $branchBatch = Batch::query()->where('product_id', $branchProduct->id)->first();
    expect($branchBatch)->not->toBeNull()
        ->and((float) $branchBatch->available)->toBe(30.0);

    $payload = productUpdatePayload($branchProduct, [
        'branch_id' => '',
        'initial_stock' => '30',
        'purchase_price' => '300',
        'sale_price' => '500',
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $branchProduct), $payload)
        ->assertRedirect(route('product.index'));

    $copies = Product::query()->where('name', $productName)->get();
    $activeBranchCount = Branch::query()->active()->count();

    expect($copies)->toHaveCount($activeBranchCount);

    $branchProduct->refresh();
    $mainCopy = $copies->firstWhere('branch_id', $mainBranchId);

    expect($branchProduct->branch_id)->toBe($operatingBranch->id)
        ->and($branchProduct->product_group_id)->not->toBeNull()
        ->and($mainCopy)->not->toBeNull();

    $branchBatch->refresh();
    expect((float) $branchBatch->available)->toBe(30.0);

    $mainBatch = Batch::query()->where('product_id', $mainCopy->id)->first();
    expect($mainBatch)->not->toBeNull()
        ->and((float) $mainBatch->available)->toBe(30.0);

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
        'code' => 'GRP-A-'.fake()->unique()->numerify('######'),
        'sale_price' => 300,
    ]);

    Product::factory()->create([
        'branch_id' => $branchB->id,
        'product_group_id' => $groupId,
        'category_id' => $productA->category_id,
        'brand_id' => $productA->brand_id,
        'unit_id' => $productA->unit_id,
        'name' => $productName,
        'code' => 'GRP-B-'.fake()->unique()->numerify('######'),
        'sale_price' => 300,
    ]);

    $payload = productUpdatePayload($productA, [
        'branch_id' => '',
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
        'code' => 'OUT-A-'.fake()->unique()->numerify('######'),
    ]);

    Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'name' => $sharedName,
        'code' => 'OUT-B-'.fake()->unique()->numerify('######'),
    ]);

    $payload = productUpdatePayload($productA, [
        'name' => $sharedName,
    ]);

    $this->actingAs($admin)
        ->patch(route('product.update', $productA), $payload)
        ->assertSessionHasErrors('name');
});
