<?php

use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('purchase index shows all branch purchases like purchase report', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('inventory.purchase.view', 'web');

    $dressShop = Branch::factory()->create(['name' => 'Dress Shop']);
    $branchUser = User::factory()->create(['branch_id' => $dressShop->id]);
    $branchUser->givePermissionTo('inventory.purchase.view');

    $supplier = Supplier::factory()->create(['branch_id' => $dressShop->id]);

    foreach (range(1, 7) as $index) {
        Purchase::factory()->create([
            'branch_id' => $dressShop->id,
            'user_id' => User::SUPER_ADMIN_ID,
            'supplier_id' => $supplier->id,
            'gross_amount' => 100 * $index,
            'paid_amount' => 0,
            'due_amount' => 100 * $index,
            'purchase_type' => PurchaseType::InitialStock,
            'date' => now()->format('Y-m-d'),
            'comment' => "Initial stock — Product {$index}",
        ]);
    }

    $this->actingAs($branchUser)
        ->get(route('inventory.purchase.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('purchases.data', 7));
});

test('branch user with update permission can edit another users purchase in same branch', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('inventory.purchase.view', 'web');
    Permission::findOrCreate('inventory.purchase.update', 'web');

    $branch = Branch::factory()->create();
    $editor = User::factory()->create(['branch_id' => $branch->id]);
    $owner = User::factory()->create(['branch_id' => $branch->id]);
    $editor->givePermissionTo(['inventory.purchase.view', 'inventory.purchase.update']);

    $purchase = Purchase::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $owner->id,
        'date' => now()->toDateString(),
        'gross_amount' => 100,
        'paid_amount' => 0,
        'due_amount' => 100,
        'purchase_type' => PurchaseType::Purchase,
        'serial' => 'INVP00000003',
    ]);

    $this->actingAs($editor)
        ->get(route('inventory.purchase.show', $purchase))
        ->assertOk();

    $this->actingAs($editor)
        ->get(route('inventory.purchase.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/index')
            ->where('purchases.data.0.can_edit', true));

    $this->actingAs($editor)
        ->get(route('inventory.purchase.edit', $purchase))
        ->assertOk();
});
