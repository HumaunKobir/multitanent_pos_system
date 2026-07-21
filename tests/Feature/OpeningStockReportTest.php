<?php

use App\Http\Controllers\Reports\OpeningStockController;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function openingStockMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

function openingStockAdmin(array $permissions = [OpeningStockController::PERMISSION_VIEW]): User
{
    test()->artisan('permissions:sync');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo($permissions);

    return $admin;
}

test('opening stock report requires permission', function () {
    openingStockMainBranch();
    $user = User::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get(route('report.opening-stock'))
        ->assertForbidden();
});

test('opening stock report page loads', function () {
    openingStockMainBranch();
    $admin = openingStockAdmin();

    $this->actingAs($admin)
        ->get(route('report.opening-stock'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/opening-stock')
            ->has('rows.data')
            ->has('summary')
            ->has('categories')
            ->has('brands'));
});

test('opening stock report lists initial stock records', function () {
    $mainBranchId = openingStockMainBranch();
    $admin = openingStockAdmin();
    $category = Category::factory()->create(['status' => 1]);
    $brand = Brand::factory()->create(['status' => 1]);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Opening Tee '.fake()->unique()->numerify('######'),
        'status' => 1,
        'purchase_price' => 100,
    ]);

    ProductInitialStock::query()->create([
        'product_id' => $product->id,
        'product_variation_id' => null,
        'batch_id' => null,
        'branch_id' => $mainBranchId,
        'quantity' => 12,
        'unit_cost' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('report.opening-stock', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.product', $product->name)
            ->where('rows.data.0.quantity', 12)
            ->where('rows.data.0.unit_cost', 100)
            ->where('rows.data.0.stock_value', 1200)
            ->where('summary.total_qty', 12)
            ->where('summary.total_value', 1200));
});
