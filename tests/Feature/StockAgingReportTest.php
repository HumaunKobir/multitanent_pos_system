<?php

use App\Http\Controllers\Reports\StockAgingController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Size;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function stockAgingMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

function stockAgingAdmin(array $permissions = [StockAgingController::PERMISSION_VIEW]): User
{
    test()->artisan('permissions:sync');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo($permissions);

    return $admin;
}

test('stock aging report requires permission', function () {
    stockAgingMainBranch();
    $user = User::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get(route('report.stock-aging'))
        ->assertForbidden();
});

test('stock aging report renders with filters and summary buckets', function () {
    stockAgingMainBranch();
    $admin = stockAgingAdmin();

    $this->actingAs($admin)
        ->get(route('report.stock-aging'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/stock-aging')
            ->has('rows')
            ->has('summary')
            ->has('summary.0-30')
            ->has('summary.31-60')
            ->has('summary.61-90')
            ->has('summary.90+')
            ->has('categories')
            ->has('brands')
            ->has('sizes')
            ->has('branches'));
});

test('stock aging report buckets batch stock by created at age', function () {
    stockAgingMainBranch();
    $admin = stockAgingAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Aging Batch '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $fresh = Batch::factory()->for($product)->create([
        'branch_id' => $mainBranchId,
        'available' => 5,
    ]);
    $fresh->forceFill([
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ])->saveQuietly();

    $mid = Batch::factory()->for($product)->create([
        'branch_id' => $mainBranchId,
        'available' => 3,
    ]);
    $mid->forceFill([
        'created_at' => now()->subDays(45),
        'updated_at' => now()->subDays(45),
    ])->saveQuietly();

    $old = Batch::factory()->for($product)->create([
        'branch_id' => $mainBranchId,
        'available' => 2,
    ]);
    $old->forceFill([
        'created_at' => now()->subDays(100),
        'updated_at' => now()->subDays(100),
    ])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('report.stock-aging', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/stock-aging')
            ->where('summary.0-30', 5)
            ->where('summary.31-60', 3)
            ->where('summary.61-90', 0)
            ->where('summary.90+', 2)
            ->where('summary.total', 10)
            ->where('rows.0.id', $product->id)
            ->where('rows.0.qty_0_30', 5)
            ->where('rows.0.qty_31_60', 3)
            ->where('rows.0.qty_90_plus', 2)
            ->where('rows.0.total_qty', 10));
});

test('stock aging report ages variant stock without batches using variation timestamps', function () {
    stockAgingMainBranch();
    $admin = stockAgingAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Aging Variant '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $variation = ProductVariation::query()->create([
        'branch_id' => $mainBranchId,
        'product_id' => $product->id,
        'sku' => fake()->unique()->numerify('########'),
        'variation_data' => ['label' => 'Red-M', 'Color' => 'Red', 'Size' => 'M'],
        'price' => 250,
        'purchase_price' => 120,
        'stock' => 7,
        'status' => 1,
    ]);
    $variation->forceFill([
        'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays(20),
    ])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('report.stock-aging', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.0-30', 7)
            ->where('summary.total', 7)
            ->where('rows.0.id', $product->id)
            ->where('rows.0.qty_0_30', 7));
});

test('stock aging report can filter by size brand and category', function () {
    stockAgingMainBranch();
    $admin = stockAgingAdmin();
    $mainBranchId = Branch::resolveMainBranchId();
    $category = Category::factory()->create(['status' => 1]);
    $otherCategory = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);
    $size = Size::query()->create([
        'branch_id' => $mainBranchId,
        'name' => 'Age Size '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $matched = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Age Filter Match '.fake()->unique()->numerify('######'),
        'status' => 1,
        'sizes' => [$size->id],
    ]);
    Batch::factory()->for($matched)->create([
        'branch_id' => $mainBranchId,
        'available' => 4,
    ])->forceFill([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ])->saveQuietly();

    $miss = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $otherCategory->id,
        'brand_id' => $brand->id,
        'name' => 'Age Filter Miss '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);
    Batch::factory()->for($miss)->create([
        'branch_id' => $mainBranchId,
        'available' => 9,
    ])->forceFill([
        'created_at' => now()->subDays(5),
        'updated_at' => now()->subDays(5),
    ])->saveQuietly();

    $this->actingAs($admin)
        ->get(route('report.stock-aging', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'size_id' => $size->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $matched->id)
            ->where('filters.size_id', (string) $size->id));
});
