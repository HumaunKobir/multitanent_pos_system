<?php

use App\Enums\PurchaseReceivedPayment;
use App\Enums\StockDistributionStatus;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnProduct;
use App\Models\StockDistribution;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function purchaseReturnUser(array $permissions = [
    'inventory.purchase-return.view',
    'inventory.purchase-return.create',
    'inventory.purchase-return.update',
    'inventory.purchase-return.delete',
]): User
{
    $user = User::factory()->create();
    $branch = Branch::factory()->create();
    $user->update(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function purchaseReturnProduct(float $available = 20, ?int $branchId = null): array
{
    $product = Product::factory()->create(['branch_id' => $branchId]);
    $batch = Batch::factory()->for($product)->withStock($available)->create([
        'branch_id' => $branchId,
        'purchase_price' => 100,
    ]);

    return compact('product', 'batch');
}

function purchaseReturnPurchase(User $user, Supplier $supplier, array $amounts): Purchase
{
    $purchase = Purchase::factory()
        ->withUser($user)
        ->withSupplier($supplier)
        ->withAmounts(...$amounts)
        ->create();

    $purchase->update(['serial' => 'INVP'.str_pad((string) $purchase->id, 8, '0', STR_PAD_LEFT)]);

    return $purchase;
}

test('purchase lookup includes discount and vat fields', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 50,
        'paid' => 950,
    ]);

    PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $this->actingAs($user)
        ->getJson(route('api.purchases.lookup', ['invoice' => $purchase->invoice_number]))
        ->assertSuccessful()
        ->assertJsonPath('gross_amount', 1000)
        ->assertJsonPath('discount', 100)
        ->assertJsonPath('vat', 50)
        ->assertJsonPath('vat_percent', 5);
});

test('purchase return is blocked while distributed stock is held at a branch', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid' => 1000,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $targetBranch = Branch::factory()->create(['name' => 'Gulshan Outlet '.fake()->unique()->numerify('###')]);

    $distribution = StockDistribution::create([
        'branch_id' => $user->branch_id,
        'from_branch_id' => $user->branch_id,
        'to_branch_id' => $targetBranch->id,
        'date' => now()->format('Y-m-d'),
        'status' => StockDistributionStatus::Received,
        'purchase_id' => $purchase->id,
        'serial' => 'INVT'.str_pad((string) (StockDistribution::max('id') + 1), 8, '0', STR_PAD_LEFT),
    ]);

    $distribution->products()->create([
        'branch_id' => $user->branch_id,
        'product_id' => $product->id,
        'quantity' => 5,
        'received_at' => now(),
    ]);

    $this->actingAs($user)
        ->getJson(route('api.purchases.lookup', ['invoice' => $purchase->invoice_number]))
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($message) => str_contains($message, $targetBranch->name));

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(PurchaseReturn::query()->where('purchase_id', $purchase->id)->exists())->toBeFalse();
});

test('purchase return stores proportional discount and vat and caps paid at net', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 50,
        'paid' => 950,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '1000',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    expect($purchaseReturn)->not->toBeNull();
    expect((float) $purchaseReturn->gross_amount)->toBe(500.0);
    expect((float) $purchaseReturn->discount)->toBe(50.0);
    expect((float) $purchaseReturn->vat)->toBe(25.0);
    expect((float) $purchaseReturn->net_amount)->toBe(475.0);
    expect((float) $purchaseReturn->paid_amount)->toBe(475.0);
    expect((float) $purchaseReturn->due_amount)->toBe(0.0);
});

test('purchase return on supplier account decreases supplier balance by net amount', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 950]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 50,
        'paid' => 950,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $supplier->refresh();

    expect((float) $supplier->balance)->toBe(475.0); // 950 - 475
});

test('purchase return with partial cash refund only reduces supplier balance by the due portion', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 950]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 50,
        'paid' => 950,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '200',
            'payment_type' => (string) PurchaseReceivedPayment::Cash->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    expect((float) $purchaseReturn->net_amount)->toBe(475.0);
    expect((float) $purchaseReturn->paid_amount)->toBe(200.0);
    expect((float) $purchaseReturn->due_amount)->toBe(275.0);

    $supplier->refresh();

    // Only the unpaid (due) portion is settled against the supplier account.
    expect((float) $supplier->balance)->toBe(675.0); // 950 - 275
});

test('purchase return update recalculates discount and vat', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 50,
        'paid' => 950,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $purchaseReturn = PurchaseReturn::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'purchase_id' => $purchase->id,
        'supplier_id' => $supplier->id,
        'gross_amount' => 500,
        'discount' => 50,
        'vat' => 25,
        'paid_amount' => 0,
        'due_amount' => 475,
        'payment_type' => PurchaseReceivedPayment::Supplier_Account,
    ]);

    PurchaseReturnProduct::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_return_id' => $purchaseReturn->id,
        'purchase_product_id' => $purchaseProduct->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100,
        'batches' => [(string) $batch->id => 5],
    ]);

    $supplier->update(['balance' => -475]);

    $this->actingAs($user)
        ->put(route('inventory.purchase-return.update', $purchaseReturn), [
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '10'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn->refresh();

    expect((float) $purchaseReturn->gross_amount)->toBe(1000.0);
    expect((float) $purchaseReturn->discount)->toBe(100.0);
    expect((float) $purchaseReturn->vat)->toBe(50.0);
    expect((float) $purchaseReturn->net_amount)->toBe(950.0);

    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(-950.0);
});

test('purchase return destroy rolls back supplier balance by net amount', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => -475]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 50,
        'paid' => 950,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $purchaseReturn = PurchaseReturn::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'purchase_id' => $purchase->id,
        'supplier_id' => $supplier->id,
        'gross_amount' => 500,
        'discount' => 50,
        'vat' => 25,
        'paid_amount' => 0,
        'due_amount' => 475,
        'payment_type' => PurchaseReceivedPayment::Supplier_Account,
    ]);

    PurchaseReturnProduct::factory()->create([
        'branch_id' => $user->branch_id,
        'purchase_return_id' => $purchaseReturn->id,
        'purchase_product_id' => $purchaseProduct->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100,
        'batches' => [(string) $batch->id => 5],
    ]);

    $this->actingAs($user)
        ->delete(route('inventory.purchase-return.destroy', $purchaseReturn))
        ->assertRedirect(route('inventory.purchase-return.index'));

    $supplier->refresh();

    expect((float) $supplier->balance)->toBe(0.0);
});
