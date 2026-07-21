<?php

use App\Enums\SaleType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use App\Services\SalesProfitTrendService;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from sales profit trend report', function () {
    $this->get('/report/sales-profit-trend')->assertRedirect(route('login'));
});

test('sales profit trend page loads with product group by default', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create();
    $user->givePermissionTo(ReportController::PERMISSION_SALES_PROFIT_TREND);

    $this->actingAs($user)
        ->get('/report/sales-profit-trend')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-profit-trend')
            ->where('filters.group_by', SalesProfitTrendService::GROUP_PRODUCT)
            ->where('filters.period', SalesProfitTrendService::PERIOD_DAILY)
            ->has('groups', 3)
            ->has('periods', 2)
            ->has('report.trend')
            ->has('report.comparison')
            ->has('report.breakdown.rows')
            ->has('report.totals'));
});

test('sales profit trend product breakdown includes sales cost and profit', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_PROFIT_TREND);

    $brand = Brand::factory()->create(['branch_id' => $branch->id, 'name' => 'Trend Brand']);
    $category = Category::factory()->create(['branch_id' => $branch->id, 'name' => 'Trend Category']);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'name' => 'Trend Tee',
        'purchase_price' => 100,
    ]);

    $date = now()->format('Y-m-d');
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 400,
        'discount' => 20,
        'vat' => 10,
        'paid_amount' => 390,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'free_quantity' => 0,
        'unit_price' => 200,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-profit-trend?group_by=product&period=daily&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.breakdown.rows.0.name', 'Trend Tee')
            ->where('report.breakdown.rows.0.sales', 380)
            ->where('report.breakdown.rows.0.discount', 20)
            ->where('report.breakdown.rows.0.vat', 10)
            ->where('report.breakdown.rows.0.cost', 200)
            ->where('report.breakdown.rows.0.profit', 180)
            ->where('report.totals.sales', 380)
            ->where('report.totals.discount', 20)
            ->where('report.totals.vat', 10)
            ->where('report.totals.profit', 180)
            ->where('report.comparison.0.name', 'Trend Tee')
            ->where('report.trend.0.period', $date)
            ->where('report.trend.0.sales', 380)
            ->where('report.trend.0.discount', 20)
            ->where('report.trend.0.vat', 10)
            ->where('report.trend.0.profit', 180));
});

test('sales profit trend supports brand and category grouping', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_PROFIT_TREND);

    $brand = Brand::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Brand']);
    $category = Category::factory()->create(['branch_id' => $branch->id, 'name' => 'Apparel']);
    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'name' => 'Grouped Item',
        'purchase_price' => 50,
    ]);

    $date = now()->format('Y-m-d');
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 150,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 150,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'free_quantity' => 0,
        'unit_price' => 150,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-profit-trend?group_by=brand&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.group_by', 'brand')
            ->where('report.breakdown.rows.0.name', 'Acme Brand')
            ->where('report.breakdown.rows.0.profit', 100));

    $this->actingAs($user)
        ->get('/report/sales-profit-trend?group_by=category&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.group_by', 'category')
            ->where('report.breakdown.rows.0.name', 'Apparel')
            ->where('report.breakdown.rows.0.profit', 100));
});
