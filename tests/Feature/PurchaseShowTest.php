<?php

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from purchase show', function () {
    $purchase = Purchase::factory()->purchase()->create();

    $this->get(route('inventory.purchase.show', $purchase))->assertRedirect(route('login'));
});

test('purchase show page loads with print-ready payload', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Purchase Clerk']);
    $user->givePermissionTo('inventory.purchase.view');

    $supplier = Supplier::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Alpha Supply',
        'company_name' => 'Alpha Co',
    ]);

    $purchase = Purchase::factory()
        ->purchase()
        ->withSupplier($supplier)
        ->withUser($user)
        ->withAmounts(1000, 50, 20, 400)
        ->create(['date' => now()->format('Y-m-d')]);

    $this->actingAs($user)
        ->get(route('inventory.purchase.show', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/show')
            ->where('purchase.id', $purchase->id)
            ->where('purchase.supplier.name', 'Alpha Supply')
            ->where('purchase.created_by_name', 'Purchase Clerk')
            ->where('purchase.gross_amount', '1000.00')
            ->where('purchase.discount', '50.00')
            ->has('purchase.purchase_products')
            ->has('purchase.direct_payment')
            ->has('purchase.supplier_payment_details'));
});
