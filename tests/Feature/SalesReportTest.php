<?php

use App\Enums\SaleType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use App\Services\SalesReportService;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from sales report', function () {
    $this->get('/report/sales-report')->assertRedirect(route('login'));
});

test('sales report page loads with daily type by default', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create();
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $this->actingAs($user)
        ->get('/report/sales-report')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/sales-report')
            ->where('filters.type', SalesReportService::TYPE_DAILY)
            ->has('types', 13)
            ->has('report.rows')
            ->has('report.columns'));
});

test('sales report product wise includes profit and profit percent', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Profit Tee',
        'purchase_price' => 100,
    ]);

    $date = now()->format('Y-m-d');
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 400,
        'discount' => 0,
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
        'discount' => 20,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-report?type=product&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.name', 'Profit Tee')
            ->where('report.rows.0.amount', 380)
            ->where('report.rows.0.discount', 20)
            ->where('report.rows.0.discount_pct', 5)
            ->where('report.rows.0.vat', 10)
            ->where('report.rows.0.vat_pct', 2.6)
            ->where('report.rows.0.cost', 200)
            ->where('report.rows.0.profit', 180)
            ->where('report.rows.0.margin', 47.4)
            ->where('report.totals.discount', 20)
            ->where('report.totals.discount_pct', 5)
            ->where('report.totals.vat', 10)
            ->where('report.totals.vat_pct', 2.6));
});

test('sales report allocates invoice coin and special discounts to product lines', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Discounted Hoodie',
        'purchase_price' => 100,
    ]);

    $date = now()->format('Y-m-d');
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 500,
        'discount' => 5,
        'special_discount_amount' => 0,
        'coin_discount_amount' => 8,
        'round_off_amount' => 0,
        'vat' => 9.74,
        'paid_amount' => 496.74,
    ]);

    SellProduct::query()->create([
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'free_quantity' => 0,
        'unit_price' => 500,
        'discount' => 0,
        'batches' => [],
    ]);

    $this->actingAs($user)
        ->get('/report/sales-report?type=product&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.name', 'Discounted Hoodie')
            ->where('report.rows.0.discount', 13)
            ->where('report.rows.0.amount', 487)
            ->where('report.rows.0.vat', 9.74)
            ->where('report.rows.0.profit', 387));

    $this->actingAs($user)
        ->get('/report/sales-report?type=fast_moving&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.discount', 13)
            ->where('report.rows.0.vat', 9.74));

    $this->actingAs($user)
        ->get('/report/sales-report?type=profit&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rows.0.discount', 13)
            ->where('report.rows.0.vat', 9.74));
});

test('superadmin sales report includes sales from every branch', function () {
    $this->artisan('permissions:sync');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $branchA = Branch::factory()->create(['name' => 'Branch A']);
    $branchB = Branch::factory()->create(['name' => 'Branch B']);
    $date = now()->addYears(80)->addDays(fake()->unique()->numberBetween(1, 10000))->format('Y-m-d');

    foreach ([$branchA, $branchB] as $branch) {
        $product = Product::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'All Branch Item '.$branch->id,
            'purchase_price' => 50,
        ]);

        $sell = Sell::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $admin->id,
            'type' => SaleType::Sale,
            'date' => $date,
            'gross_amount' => 150,
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
    }

    $this->actingAs($admin)
        ->get('/report/sales-report?type=daily&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isBranchScoped', false)
            ->has('branches')
            ->where('report.totals.invoice_count', 2)
            ->where('report.totals.gross', 300)
            ->where('report.rows.0.profit', fn ($profit) => is_numeric($profit)));

    $this->actingAs($admin)
        ->get('/report/sales-report?type=product&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('report.rows', 2)
            ->where('report.rows.0.branch', fn ($branch) => filled($branch))
            ->where('report.totals.quantity', 2));

    $this->actingAs($admin)
        ->get('/report/sales-report?type=daily&date_from='.$date.'&date_to='.$date.'&branch_id='.$branchA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.branch_id', $branchA->id)
            ->where('report.totals.invoice_count', 1)
            ->where('report.totals.gross', 150));
});

test('main branch user sales report starts with all branches and can filter', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = Branch::resolveMainBranchId();
    $admin = User::factory()->create(['branch_id' => $mainBranchId]);
    $admin->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $otherBranch = Branch::factory()->create(['name' => 'Other Branch '.uniqid()]);
    $date = now()->addYears(81)->addDays(fake()->unique()->numberBetween(1, 10000))->format('Y-m-d');

    foreach ([$mainBranchId, $otherBranch->id] as $branchId) {
        $product = Product::factory()->create([
            'branch_id' => $branchId,
            'name' => 'Main Scope Item '.$branchId,
            'purchase_price' => 40,
        ]);

        $sell = Sell::factory()->create([
            'branch_id' => $branchId,
            'user_id' => $admin->id,
            'type' => SaleType::Sale,
            'date' => $date,
            'gross_amount' => 120,
            'paid_amount' => 120,
        ]);

        SellProduct::query()->create([
            'branch_id' => $branchId,
            'sell_id' => $sell->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'free_quantity' => 0,
            'unit_price' => 120,
            'discount' => 0,
            'batches' => [],
        ]);
    }

    $this->actingAs($admin)
        ->get('/report/sales-report?type=daily&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isBranchScoped', false)
            ->where('filters.branch_id', null)
            ->has('branches')
            ->where('report.totals.invoice_count', 2)
            ->where('report.totals.gross', 240));

    $this->actingAs($admin)
        ->get('/report/sales-report?type=daily&date_from='.$date.'&date_to='.$date.'&branch_id='.$otherBranch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.branch_id', $otherBranch->id)
            ->where('report.totals.invoice_count', 1)
            ->where('report.totals.gross', 120));
});

test('branch user sales report is scoped without branch filter', function () {
    $this->artisan('permissions:sync');

    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branchA->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $date = now()->addYears(82)->addDays(fake()->unique()->numberBetween(1, 10000))->format('Y-m-d');

    foreach ([$branchA, $branchB] as $branch) {
        Sell::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'type' => SaleType::Sale,
            'date' => $date,
            'gross_amount' => 200,
            'paid_amount' => 200,
        ]);
    }

    $this->actingAs($user)
        ->get('/report/sales-report?type=daily&date_from='.$date.'&date_to='.$date.'&branch_id='.$branchB->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isBranchScoped', true)
            ->where('branches', [])
            ->where('filters.branch_id', null)
            ->where('report.totals.invoice_count', 1)
            ->where('report.totals.gross', 200));
});

test('sales report salesman type groups by user', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Sales Clerk A',
    ]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $date = now()->format('Y-m-d');
    Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 500,
        'paid_amount' => 500,
    ]);

    $this->actingAs($user)
        ->get('/report/sales-report?type=salesman&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.type', 'salesman')
            ->where('report.rows.0.name', 'Sales Clerk A')
            ->where('report.totals.invoice_count', 1));
});

test('sales report low stock uses threshold filter', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $low = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Low Stock Item',
    ]);
    Batch::factory()->for($low)->withStock(2)->create(['branch_id' => $branch->id]);

    $high = Product::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'High Stock Item',
    ]);
    Batch::factory()->for($high)->withStock(50)->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->get('/report/sales-report?type=low_stock&threshold=5')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.type', 'low_stock')
            ->where('report.meta.threshold', 5)
            ->where('report.rows', fn ($rows) => collect($rows)->contains(
                fn ($row) => ($row['name'] ?? null) === 'Low Stock Item',
            ))
            ->where('report.rows', fn ($rows) => ! collect($rows)->contains(
                fn ($row) => ($row['name'] ?? null) === 'High Stock Item',
            )));
});

test('sales report exports pdf excel and csv', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_SALES_REPORT);

    $date = now()->format('Y-m-d');
    Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
        'date' => $date,
        'gross_amount' => 250,
        'paid_amount' => 250,
    ]);

    $query = [
        'type' => 'daily',
        'date_from' => $date,
        'date_to' => $date,
    ];

    $this->actingAs($user)
        ->get(route('report.sales-report.export-excel', $query))
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->actingAs($user)
        ->get(route('report.sales-report.export-pdf', $query))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)
        ->get(route('report.sales-report.export-csv', $query))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition');
});
