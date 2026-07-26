<?php

use App\Http\Controllers\Reports\OpeningStockController;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductInitialStock;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

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

test('opening stock report reflects non-variant product edit initial stock changes', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $mainBranchId = openingStockMainBranch();
    $admin = openingStockAdmin();
    Permission::findOrCreate('product.update', 'web');
    $admin->givePermissionTo('product.update');

    seedAccountingAccounts(branchId: $mainBranchId);

    $product = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'category_id' => Category::factory()->create(['status' => 1])->id,
        'brand_id' => Brand::factory()->create(['status' => 1])->id,
        'unit_id' => Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id,
        'code' => fake()->unique()->numerify('########'),
        'purchase_price' => 50,
        'sale_price' => 80,
        'status' => 1,
    ]);

    $payload = [
        'category_id' => (string) $product->category_id,
        'brand_id' => (string) $product->brand_id,
        'unit_id' => (string) $product->unit_id,
        'name' => $product->name,
        'code' => $product->code,
        'purchase_price' => '50',
        'sale_price' => '80',
        'visible' => 'yes',
        'status' => '1',
    ];

    $this->actingAs($admin)
        ->patch(route('product.update', $product), array_merge($payload, ['initial_stock' => '10']))
        ->assertRedirect(route('product.index'));

    $this->actingAs($admin)
        ->get(route('report.opening-stock', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.quantity', 10)
            ->where('rows.data.0.stock_value', 500));

    $this->actingAs($admin)
        ->patch(route('product.update', $product), array_merge($payload, ['initial_stock' => '7']))
        ->assertRedirect(route('product.index'));

    $this->actingAs($admin)
        ->get(route('report.opening-stock', ['search' => $product->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.quantity', 7)
            ->where('rows.data.0.stock_value', 350)
            ->where('summary.total_qty', 7)
            ->where('summary.total_value', 350));
});
