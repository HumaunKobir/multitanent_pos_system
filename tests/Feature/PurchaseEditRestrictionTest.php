<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function purchaseEditRestrictionUser(): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach (['inventory.purchase.view', 'inventory.purchase.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user->givePermissionTo(['inventory.purchase.view', 'inventory.purchase.update']);

    return $user;
}

function purchaseEditRestrictionPurchase(User $user, float $paid, float $due): array
{
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $batch = Batch::factory()->for($product)->withStock(10)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 100,
    ]);

    $purchase = Purchase::factory()
        ->withUser($user)
        ->withSupplier($supplier)
        ->withAmounts(gross: 1000, discount: 0, vat: 0, paid: $paid)
        ->create(['due_amount' => $due]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create(['unit_price' => 100]);

    return compact('purchase', 'purchaseProduct', 'supplier', 'product');
}

test('fully paid purchase cannot open edit page', function () {
    $user = purchaseEditRestrictionUser();
    ['purchase' => $purchase] = purchaseEditRestrictionPurchase($user, paid: 1000, due: 0);

    $this->actingAs($user)
        ->get(route('inventory.purchase.edit', $purchase))
        ->assertRedirect(route('inventory.purchase.show', $purchase))
        ->assertSessionHas('error', 'This purchase cannot be edited because it is fully paid.');
});

test('fully paid purchase cannot be updated directly', function () {
    $user = purchaseEditRestrictionUser();
    ['purchase' => $purchase, 'purchaseProduct' => $purchaseProduct, 'supplier' => $supplier] = purchaseEditRestrictionPurchase($user, paid: 1000, due: 0);

    $this->actingAs($user)
        ->from(route('inventory.purchase.show', $purchase))
        ->put(route('inventory.purchase.update', $purchase), [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'comment' => 'Attempt update',
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '1000',
            'items' => [[
                'product_id' => $purchaseProduct->product_id,
                'variation_id' => null,
                'unit_price' => '100',
                'quantity' => '10',
                'free_quantity' => '0',
            ]],
        ])
        ->assertRedirect(route('inventory.purchase.show', $purchase))
        ->assertSessionHas('error', 'This purchase cannot be edited because it is fully paid.');
});

test('partially paid purchase cannot change product lines', function () {
    $user = purchaseEditRestrictionUser();
    ['purchase' => $purchase, 'purchaseProduct' => $purchaseProduct, 'supplier' => $supplier] = purchaseEditRestrictionPurchase($user, paid: 400, due: 600);

    $this->actingAs($user)
        ->from(route('inventory.purchase.edit', $purchase))
        ->put(route('inventory.purchase.update', $purchase), [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'comment' => null,
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '400',
            'items' => [[
                'product_id' => $purchaseProduct->product_id,
                'variation_id' => null,
                'unit_price' => '100',
                'quantity' => '9',
                'free_quantity' => '0',
            ]],
        ])
        ->assertRedirect(route('inventory.purchase.edit', $purchase))
        ->assertSessionHasErrors(['items' => 'Products cannot be changed because this purchase is partially paid.']);
});
