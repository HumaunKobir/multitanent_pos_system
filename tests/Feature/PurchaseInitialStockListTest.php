<?php

use App\Enums\PurchaseType;
use App\Enums\StockAdjustmentType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('purchase index lists initial stock with supplier from product create', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.purchase.view', 'web');
    $user->givePermissionTo('inventory.purchase.view');

    $supplier = Supplier::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Initial Stock Supplier',
        'company_name' => 'ISS Co',
    ]);

    Purchase::factory()
        ->initialStock()
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(450, 0, 0, 0)
        ->create([
            'date' => now()->format('Y-m-d'),
            'comment' => 'Initial stock — Widget',
        ]);

    Purchase::factory()
        ->state(['purchase_type' => PurchaseType::OpeningBalance])
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(999, 0, 0, 0)
        ->create(['date' => now()->format('Y-m-d')]);

    $this->actingAs($user)
        ->get(route('inventory.purchase.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.purchase_type_label', 'Initial Stock')
            ->where('purchases.data.0.supplier.name', 'Initial Stock Supplier')
            ->where('purchases.data.0.can_edit', false)
            ->where('purchases.data.0.can_delete', false));
});

test('purchase index lists supplier stock adjustments', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('inventory.purchase.view', 'web');
    $user->givePermissionTo('inventory.purchase.view');
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
        ->get(route('inventory.purchase.index', ['date_from' => $date, 'date_to' => $date]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('purchases.data', 2)
            ->where('purchases.data.0.purchase_type_label', 'Stock Adjustment (Increase)')
            ->where('purchases.data.0.row_type', 'stock_adjustment')
            ->where('purchases.data.0.show_route', 'inventory.stock-adjustment.show')
            ->where('purchases.data.0.supplier.name', 'Adjustment Supplier')
            ->where('purchases.data.0.due_amount', 100)
            ->where('purchases.data.1.purchase_type_label', 'Purchase'));
});
