<?php

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('supplier list paid comes from supplier payments only', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('party.supplier.view', 'web');
    $user->givePermissionTo('party.supplier.view');

    $supplier = Supplier::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Due Paid Supplier',
        'balance' => 300,
    ]);

    // Purchase paid_amount must NOT count toward the Paid column.
    Purchase::factory()
        ->purchase()
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(1000, 0, 0, 700)
        ->create();

    Purchase::factory()
        ->initialStock()
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(500, 0, 0, 0)
        ->create();

    SupplierPayment::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'date' => now()->format('Y-m-d'),
        'amount' => 250,
        'serial' => 'INVSP-TEST-001',
        'created_by' => $user->id,
    ]);

    SupplierPayment::query()->create([
        'branch_id' => $branch->id,
        'supplier_id' => $supplier->id,
        'date' => now()->format('Y-m-d'),
        'amount' => 50,
        'serial' => 'INVSP-TEST-002',
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('party.supplier.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/supplier/index')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Due Paid Supplier')
            ->where('suppliers.data.0.paid_amount', 300)
            ->where('suppliers.data.0.due_amount', 800)
            ->where('suppliers.data.0.balance', 300));
});
