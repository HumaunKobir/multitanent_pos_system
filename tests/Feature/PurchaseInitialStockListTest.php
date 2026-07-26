<?php

use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
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
