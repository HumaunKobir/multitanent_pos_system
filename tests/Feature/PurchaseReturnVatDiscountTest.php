<?php

use App\Enums\PurchaseReceivedPayment;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnProduct;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function purchaseReturnVatUser(): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ([
        'inventory.purchase-return.view',
        'inventory.purchase-return.create',
        'inventory.purchase-return.update',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('purchase return stores submitted discount as return-level amount without re-scaling', function () {
    $this->artisan('permissions:sync');

    $user = purchaseReturnVatUser();
    seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $batch = Batch::factory()->for($product)->withStock(200)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 300,
    ]);

    // Parent: gross 60000, discount 2000 → taxable 58000; 15% VAT → 8700; net 66700
    $purchase = Purchase::factory()
        ->withUser($user)
        ->withSupplier($supplier)
        ->withAmounts(gross: 60000, discount: 2000, vat: 8700, paid: 0)
        ->create(['due_amount' => 66700]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 200)
        ->create(['unit_price' => 300, 'quantity' => 200]);

    // Return 10 × 300 = 3000 with the parent discount 2000 applied as return-level.
    // Taxable 1000; VAT 15% = 150; net 1150.
    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '2000',
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '10'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    expect($purchaseReturn)->not->toBeNull()
        ->and((float) $purchaseReturn->gross_amount)->toBe(3000.0)
        ->and((float) $purchaseReturn->discount)->toBe(2000.0)
        ->and((float) $purchaseReturn->vat)->toBe(150.0)
        ->and((float) $purchaseReturn->net_amount)->toBe(1150.0)
        ->and((float) $purchaseReturn->vat_percent)->toBe(15.0);
});

test('purchase return edit keeps return-level discount and vat after discount', function () {
    $this->artisan('permissions:sync');

    $user = purchaseReturnVatUser();
    seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);
    $batch = Batch::factory()->for($product)->withStock(200)->create([
        'branch_id' => $user->branch_id,
        'purchase_price' => 300,
    ]);

    $purchase = Purchase::factory()
        ->withUser($user)
        ->withSupplier($supplier)
        ->withAmounts(gross: 60000, discount: 2000, vat: 8700, paid: 0)
        ->create(['due_amount' => 66700]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 200)
        ->create(['unit_price' => 300, 'quantity' => 200]);

    $purchaseReturn = PurchaseReturn::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'purchase_id' => $purchase->id,
        'supplier_id' => $supplier->id,
        'gross_amount' => 3000,
        'discount' => 100,
        'vat' => 435,
        'paid_amount' => 0,
        'due_amount' => 0,
        'purchase_due_offset' => 3335,
        'payment_type' => PurchaseReceivedPayment::Supplier_Account,
    ]);

    PurchaseReturnProduct::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_return_id' => $purchaseReturn->id,
        'purchase_product_id' => $purchaseProduct->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 300,
        'batches' => [(string) $batch->id => 10],
    ]);

    // Correct the previously scaled discount to the intended return-level amount.
    $this->actingAs($user)
        ->put(route('inventory.purchase-return.update', $purchaseReturn), [
            'date' => now()->format('Y-m-d'),
            'discount' => '2000',
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '10'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn->refresh();

    expect((float) $purchaseReturn->discount)->toBe(2000.0)
        ->and((float) $purchaseReturn->vat)->toBe(150.0)
        ->and((float) $purchaseReturn->net_amount)->toBe(1150.0);
});
