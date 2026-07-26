<?php

use App\Enums\StockAdjustmentType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from purchase report', function () {
    $this->get('/report/purchase-report')->assertRedirect(route('login'));
});

test('purchase report page loads with default date range', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create();
    $user->givePermissionTo(ReportController::PERMISSION_PURCHASE_REPORT);

    $this->actingAs($user)
        ->get('/report/purchase-report')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/purchase-report')
            ->has('suppliers')
            ->has('rows')
            ->has('supplier_summaries')
            ->has('totals')
            ->where('filters.date_from', now()->startOfMonth()->format('Y-m-d'))
            ->where('filters.date_to', now()->format('Y-m-d')));
});

test('purchase report filters by supplier and date and returns totals', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_PURCHASE_REPORT);

    $supplierA = Supplier::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Alpha Supply',
        'company_name' => 'Alpha Trading Co',
    ]);
    $supplierB = Supplier::factory()->create(['branch_id' => $branch->id, 'name' => 'Beta Supply']);

    $date = now()->format('Y-m-d');

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplierA)
        ->withUser($user)
        ->withAmounts(1000, 100, 50, 400)
        ->create(['date' => $date]);

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplierA)
        ->withUser($user)
        ->withAmounts(500, 50, 0, 0)
        ->create(['date' => $date]);

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplierB)
        ->withUser($user)
        ->withAmounts(200, 0, 0, 200)
        ->create(['date' => $date]);

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplierA)
        ->withUser($user)
        ->withAmounts(999, 0, 0, 0)
        ->create(['date' => now()->subMonth()->format('Y-m-d')]);

    $this->actingAs($user)
        ->get('/report/purchase-report?supplier_id='.$supplierA->id.'&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/purchase-report')
            ->where('supplier.name', 'Alpha Supply')
            ->where('supplier.company_name', 'Alpha Trading Co')
            ->has('rows', 2)
            ->where('rows.0.supplier_company', 'Alpha Trading Co')
            ->where('rows.0.supplier_name', 'Alpha Supply')
            ->has('supplier_summaries', 1)
            ->where('totals.invoice_count', 2)
            ->where('totals.gross_amount', 1500)
            ->where('totals.discount', 150)
            ->where('totals.vat', 50)
            ->where('totals.net_amount', 1400)
            ->where('supplier_summaries.0.supplier_name', 'Alpha Supply')
            ->where('supplier_summaries.0.supplier_company', 'Alpha Trading Co')
            ->where('supplier_summaries.0.discount', 150)
            ->where('supplier_summaries.0.net_amount', 1400));
});

test('purchase report all suppliers includes supplier-wise summary', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_PURCHASE_REPORT);

    $supplierA = Supplier::factory()->create(['branch_id' => $branch->id, 'name' => 'Alpha Supply']);
    $supplierB = Supplier::factory()->create(['branch_id' => $branch->id, 'name' => 'Beta Supply']);
    $date = now()->format('Y-m-d');

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplierA)
        ->withUser($user)
        ->withAmounts(1000, 100, 0, 0)
        ->create(['date' => $date]);

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplierB)
        ->withUser($user)
        ->withAmounts(300, 20, 0, 0)
        ->create(['date' => $date]);

    $this->actingAs($user)
        ->get('/report/purchase-report?date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('supplier', null)
            ->has('rows', 2)
            ->has('supplier_summaries', 2)
            ->where('totals.gross_amount', 1300)
            ->where('totals.discount', 120)
            ->where('totals.net_amount', 1180));
});

test('purchase report includes initial stock supplier from product create settlement', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_PURCHASE_REPORT);

    $supplier = Supplier::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Product Create Supplier',
    ]);
    $date = now()->format('Y-m-d');

    Purchase::factory()
        ->initialStock()
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(800, 0, 0, 0)
        ->create([
            'date' => $date,
            'comment' => 'Initial stock — Demo Product',
        ]);

    $this->actingAs($user)
        ->get('/report/purchase-report?supplier_id='.$supplier->id.'&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/purchase-report')
            ->has('rows', 1)
            ->where('rows.0.supplier_name', 'Product Create Supplier')
            ->where('rows.0.purchase_type_label', 'Initial Stock')
            ->where('totals.gross_amount', 800)
            ->where('supplier_summaries.0.supplier_name', 'Product Create Supplier'));
});

test('purchase report excludes stock adjustments', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(ReportController::PERMISSION_PURCHASE_REPORT);
    $user->givePermissionTo([
        'inventory.stock-adjustment.view',
        'inventory.stock-adjustment.create',
    ]);

    seedAccountingAccounts(branchId: $branch->id);

    $supplier = Supplier::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Adjustment Supplier',
    ]);
    $date = now()->format('Y-m-d');

    Purchase::factory()
        ->purchase()
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(500, 0, 0, 0)
        ->create(['date' => $date]);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'initial_stock_supplier_id' => $supplier->id,
        'purchase_price' => 50,
        'sale_price' => 100,
        'status' => 1,
    ]);

    Batch::factory()->create([
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'purchase_price' => 50,
        'available' => 10,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.stock-adjustment.store'), [
            'date' => $date,
            'type' => StockAdjustmentType::Increase->value,
            'items' => [
                ['product_id' => $product->id, 'variation_id' => null, 'quantity' => 2],
            ],
        ])
        ->assertRedirect(route('inventory.stock-adjustment.index'));

    $this->actingAs($user)
        ->get('/report/purchase-report?supplier_id='.$supplier->id.'&date_from='.$date.'&date_to='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/purchase-report')
            ->has('rows', 1)
            ->where('rows.0.purchase_type_label', 'Purchase')
            ->where('totals.invoice_count', 1)
            ->where('totals.net_amount', 500)
            ->where('supplier_summaries.0.net_amount', 500));
});
