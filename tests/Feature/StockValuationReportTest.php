<?php

use App\Http\Controllers\Reports\StockValuationController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Size;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function stockValuationMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

function stockValuationAdmin(array $permissions = [StockValuationController::PERMISSION_VIEW]): User
{
    test()->artisan('permissions:sync');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo($permissions);

    return $admin;
}

test('stock valuation report requires permission', function () {
    stockValuationMainBranch();
    $user = User::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get(route('report.stock-valuation'))
        ->assertForbidden();
});

test('stock valuation report renders with filters and empty rows', function () {
    stockValuationMainBranch();
    $admin = stockValuationAdmin();

    $this->actingAs($admin)
        ->get(route('report.stock-valuation'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/stock-valuation')
            ->has('rows.data')
            ->has('summary')
            ->has('categories')
            ->has('brands')
            ->has('sizes')
            ->has('branches'));
});

test('stock valuation report lists product qty cost selling and profit', function () {
    stockValuationMainBranch();
    $admin = stockValuationAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Valuation Tee '.fake()->unique()->numerify('######'),
        'status' => 1,
        'purchase_price' => 100,
        'sale_price' => 180,
        'discount_price' => 0,
    ]);

    Batch::factory()->for($product)->create([
        'branch_id' => $mainBranchId,
        'available' => 10,
        'purchase_price' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('report.stock-valuation', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/stock-valuation')
            ->where('rows.data.0.id', $product->id)
            ->where('rows.data.0.qty', 10)
            ->where('rows.data.0.unit_cost', 100)
            ->where('rows.data.0.cost_value', 1000)
            ->where('rows.data.0.selling_value', 1800)
            ->where('rows.data.0.profit', 800)
            ->where('summary.total_qty', 10)
            ->where('summary.total_cost_value', 1000)
            ->where('summary.expected_gross_profit', 800));
});

test('stock valuation report can filter by brand category and size', function () {
    stockValuationMainBranch();
    $admin = stockValuationAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $otherCategory = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);
    $otherBrand = Brand::factory()->create(['status' => 1]);
    $size = Size::query()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Val Size '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $matched = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Val Filter Match '.fake()->unique()->numerify('######'),
        'status' => 1,
        'sizes' => [$size->id],
    ]);
    Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $otherCategory->id,
        'brand_id' => $otherBrand->id,
        'name' => 'Val Filter Miss '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('report.stock-valuation', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'size_id' => $size->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.id', $matched->id)
            ->where('filters.brand_id', (string) $brand->id)
            ->where('filters.category_id', (string) $category->id)
            ->where('filters.size_id', (string) $size->id));
});
