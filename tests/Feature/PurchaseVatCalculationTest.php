<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function purchaseVatUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('purchase vat is calculated on gross after discount', function () {
    $this->artisan('permissions:sync');

    $user = purchaseVatUser(['inventory.purchase.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    // Gross 1000, discount 100 → taxable 900; 5% VAT → 45; net 945
    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '100',
            'vat' => '5',
            'paid_amount' => '0',
            'payment_account_id' => $cash->id,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => null,
                'unit_price' => '1000',
                'quantity' => '1',
                'free_quantity' => '0',
            ]],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $purchase = Purchase::query()->latest('id')->first();

    expect($purchase)->not->toBeNull()
        ->and((float) $purchase->gross_amount)->toBe(1000.0)
        ->and((float) $purchase->discount)->toBe(100.0)
        ->and((float) $purchase->vat)->toBe(45.0)
        ->and((float) $purchase->net_amount)->toBe(945.0)
        ->and((float) $purchase->due_amount)->toBe(945.0);

    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(945.0);
});

test('purchase edit page recovers vat percent from post-discount taxable base', function () {
    $this->artisan('permissions:sync');

    $user = purchaseVatUser(['inventory.purchase.view', 'inventory.purchase.update']);
    seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = Purchase::factory()
        ->withUser($user)
        ->withSupplier($supplier)
        ->withAmounts(gross: 1000, discount: 100, vat: 45, paid: 0)
        ->create(['due_amount' => 945]);

    PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->create(['unit_price' => 1000, 'quantity' => 1]);

    $this->actingAs($user)
        ->get(route('inventory.purchase.edit', $purchase))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/edit')
            ->where('purchase.vat_percent', 5)
            ->where('purchase.discount', 100)
        );
});
