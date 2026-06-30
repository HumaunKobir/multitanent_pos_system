<?php

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

function barcodePrintAdmin(): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('barcode.view', 'web');
    $user->givePermissionTo('barcode.view');

    return $user;
}

function barcodePrintBranchUser(int $branchId): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => $branchId]);
    Permission::findOrCreate('barcode.view', 'web');
    $user->givePermissionTo('barcode.view');

    return $user;
}

function barcodePrintMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

test('barcode index returns paginated barcodes', function () {
    barcodePrintMainBranch();
    $admin = barcodePrintAdmin();

    $this->actingAs($admin)
        ->get(route('barcode.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/index')
            ->has('barcodes.data')
            ->has('barcodes.links')
            ->has('branches')
            ->where('barcodes.per_page', 10)
            ->where('filters.branch_id', (string) Branch::resolveMainBranchId()));

    $admin->delete();
});

test('barcode print page includes variation price for variant products', function () {
    barcodePrintMainBranch();
    $admin = barcodePrintAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
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
        'branch_id' => $mainBranchId,
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
            ->where('barcodes.0.variation.sku', 'BLACK-S-30')
            ->where('barcodes.0.product.sale_price', '0.00'));
});

test('barcode print page includes product sale price for simple products', function () {
    barcodePrintMainBranch();
    $admin = barcodePrintAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $product = Product::factory()->create([
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => 'SIMPLE-'.fake()->unique()->numerify('######'),
        'sale_price' => 275,
        'discount_price' => 0,
    ]);

    $barcode = Barcode::query()->create([
        'branch_id' => $mainBranchId,
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
    barcodePrintMainBranch();
    $admin = barcodePrintAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
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
            'branch_id' => $mainBranchId,
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
                ->where('branch_id', $mainBranchId)
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

test('admin barcode index defaults to main branch barcodes only', function () {
    barcodePrintMainBranch();
    $admin = barcodePrintAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $otherBranch = Branch::factory()->create();
    $prefix = 'BCF-'.uniqid();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix.'-MAIN',
        'sale_price' => 100,
    ]);

    $otherProduct = Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix.'-OTHER',
        'sale_price' => 100,
    ]);

    $mainBarcode = Barcode::query()->create([
        'branch_id' => $mainBranchId,
        'product_id' => $mainProduct->id,
        'product_variation_id' => null,
        'code' => $prefix.'-MAIN',
        'name' => $prefix.' Main',
    ]);

    Barcode::query()->create([
        'branch_id' => $otherBranch->id,
        'product_id' => $otherProduct->id,
        'product_variation_id' => null,
        'code' => $prefix.'-OTHER',
        'name' => $prefix.' Other',
    ]);

    $this->actingAs($admin)
        ->get(route('barcode.index', ['search' => $prefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/index')
            ->where('filters.branch_id', (string) $mainBranchId)
            ->has('barcodes.data', 1)
            ->where('barcodes.data.0.id', $mainBarcode->id));

    $mainBarcode->delete();
    $mainProduct->delete();
    $otherProduct->delete();
    $admin->delete();
});

test('admin barcode index can filter by all branches', function () {
    barcodePrintMainBranch();
    $admin = barcodePrintAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $otherBranch = Branch::factory()->create();
    $prefix = 'BCA-'.uniqid();

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix.'-MAIN',
        'sale_price' => 100,
    ]);

    $otherProduct = Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix.'-OTHER',
        'sale_price' => 100,
    ]);

    Barcode::query()->create([
        'branch_id' => $mainBranchId,
        'product_id' => $mainProduct->id,
        'product_variation_id' => null,
        'code' => $prefix.'-MAIN',
        'name' => $prefix.' Main',
    ]);

    Barcode::query()->create([
        'branch_id' => $otherBranch->id,
        'product_id' => $otherProduct->id,
        'product_variation_id' => null,
        'code' => $prefix.'-OTHER',
        'name' => $prefix.' Other',
    ]);

    $this->actingAs($admin)
        ->get(route('barcode.index', ['branch_id' => 'all', 'search' => $prefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/index')
            ->where('filters.branch_id', 'all')
            ->has('barcodes.data', 2));

    $mainProduct->delete();
    $otherProduct->delete();
    $admin->delete();
});

test('branch user barcode index only shows own branch barcodes', function () {
    barcodePrintMainBranch();
    $branch = Branch::factory()->create();
    $user = barcodePrintBranchUser($branch->id);
    $prefix = 'BCB-'.uniqid();

    $branchProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix.'-BRANCH',
        'sale_price' => 100,
    ]);

    $mainProduct = Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => $prefix.'-MAIN',
        'sale_price' => 100,
    ]);

    $branchBarcode = Barcode::query()->create([
        'branch_id' => $branch->id,
        'product_id' => $branchProduct->id,
        'product_variation_id' => null,
        'code' => $prefix.'-BRANCH',
        'name' => $prefix.' Branch',
    ]);

    Barcode::query()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'product_id' => $mainProduct->id,
        'product_variation_id' => null,
        'code' => $prefix.'-MAIN',
        'name' => $prefix.' Main',
    ]);

    $this->actingAs($user)
        ->get(route('barcode.index', ['search' => $prefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/index')
            ->has('barcodes.data', 1)
            ->where('barcodes.data.0.id', $branchBarcode->id)
            ->missing('filters.branch_id'));

    $branchBarcode->delete();
    $branchProduct->delete();
    $mainProduct->delete();
    $user->delete();
});

test('main branch admin sees branch filter on barcode index', function () {
    barcodePrintMainBranch();
    $mainBranchId = Branch::resolveMainBranchId();
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('barcode.view', 'web');
    $user->givePermissionTo('barcode.view');

    $this->actingAs($user)
        ->get(route('barcode.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/barcode/index')
            ->has('branches')
            ->where('filters.branch_id', (string) $mainBranchId));

    $user->delete();
});
