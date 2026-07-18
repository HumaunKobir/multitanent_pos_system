<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function inventoryStockAdmin(array $permissions = ['report.inventory-stock.view']): User
{
    Artisan::call('permissions:sync');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo($permissions);

    return $admin;
}

function inventoryStockBranchUser(int $branchId): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => $branchId]);
    $user->givePermissionTo('report.inventory-stock.view');

    return $user;
}

function inventoryStockMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

test('inventory stock report requires permission', function () {
    inventoryStockMainBranch();
    $user = User::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get(route('report.inventory-stock'))
        ->assertForbidden();
});

test('inventory stock report renders with filters', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();

    $this->actingAs($admin)
        ->get(route('report.inventory-stock'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->has('products.data')
            ->has('summary')
            ->where('summary.total_stock', 0)
            ->where('summary.product_count', 0)
            ->has('categories')
            ->has('brands')
            ->has('branches')
            ->where('products.per_page', 20));
});

test('inventory stock report can filter by category', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();
    $category1 = Category::factory()->create(['status' => 1]);
    $category2 = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);
    $mainBranchId = Branch::resolveMainBranchId();

    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category1->id,
        'brand_id' => $brand->id,
        'name' => 'Stock Cat A '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);
    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category2->id,
        'brand_id' => $brand->id,
        'name' => 'Stock Cat B '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('report.inventory-stock', ['category_id' => $category1->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->where('filters.category_id', (string) $category1->id)
            ->where('products.data', fn ($data) => collect($data)->every(fn ($p) => $p['category_id'] === $category1->id)));
});

test('inventory stock report can filter by brand', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();
    $brand1 = Brand::factory()->create(['status' => 1]);
    $brand2 = Brand::factory()->create(['status' => 1]);
    $category = Category::factory()->create(['status' => 1]);
    $mainBranchId = Branch::resolveMainBranchId();

    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand1->id,
        'name' => 'Stock Brand A '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);
    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand2->id,
        'name' => 'Stock Brand B '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('report.inventory-stock', ['brand_id' => $brand1->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->where('filters.brand_id', (string) $brand1->id)
            ->where('products.data', fn ($data) => collect($data)->every(fn ($p) => $p['brand_id'] === $brand1->id)));
});

test('inventory stock report shows batch stock for admin branch filter', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Stock Qty '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    Batch::factory()->for($product)->withStock(17)->create([
        'branch_id' => $mainBranchId,
    ]);

    $this->actingAs($admin)
        ->get(route('report.inventory-stock', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->where('products.data.0.batches_sum_available', '17.00')
            ->where('summary.total_stock', 17)
            ->where('summary.product_count', 1)
            ->where('summary.in_stock_count', 1)
            ->where('summary.out_of_stock_count', 0)
            ->has('summary.total_cost_value')
            ->has('summary.total_selling_value')
            ->has('summary.expected_gross_profit'));
});

test('inventory stock summary includes cost selling and profit values', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Value Stock '.fake()->unique()->numerify('######'),
        'status' => 1,
        'purchase_price' => 100,
        'sale_price' => 150,
        'discount_price' => 0,
    ]);

    Batch::factory()->for($product)->create([
        'branch_id' => $mainBranchId,
        'available' => 10,
        'purchase_price' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('report.inventory-stock', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->where('summary.product_count', 1)
            ->where('summary.total_stock', 10)
            ->where('summary.total_cost_value', 1000)
            ->where('summary.total_selling_value', 1500)
            ->where('summary.expected_gross_profit', 500));
});

test('branch user sees stock for their branch only without branch filter', function () {
    inventoryStockMainBranch();
    $branch = Branch::factory()->create();
    $user = inventoryStockBranchUser($branch->id);
    $category = Category::factory()->create(['status' => 1, 'branch_id' => $branch->id]);
    $brand = Brand::factory()->create(['status' => 1, 'branch_id' => $branch->id]);

    $branchProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Branch Stock '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Main Stock '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    Batch::factory()->for($branchProduct)->withStock(8)->create([
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($user)
        ->get(route('report.inventory-stock'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->has('products.data', 1)
            ->where('products.data.0.id', $branchProduct->id)
            ->missing('filters.branch_id'));
});

test('admin can filter inventory stock report by branch', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();
    $branch = Branch::factory()->create();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $branchProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Branch Only Stock '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Main Only Stock '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('report.inventory-stock', ['branch_id' => $branch->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/inventory-stock')
            ->where('filters.branch_id', (string) $branch->id)
            ->has('products.data', 1)
            ->where('products.data.0.id', $branchProduct->id));
});

test('old inventory stock url redirects to report', function () {
    inventoryStockMainBranch();
    $admin = inventoryStockAdmin();

    $this->actingAs($admin)
        ->get('/inventory/stock')
        ->assertRedirect('/report/inventory-stock');
});
