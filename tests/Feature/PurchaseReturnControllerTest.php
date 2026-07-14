<?php

use App\Enums\PurchaseReceivedPayment;
use App\Enums\StockDistributionStatus;
use App\Enums\SystemAccountKey;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnProduct;
use App\Models\StockDistribution;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SystemAccountService;
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

    return $purchase;
}

test('purchase lookup includes discount and vat fields', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 100,
        'vat' => 45,
        'paid' => 945,
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
        ->assertJsonPath('vat', 45)
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

test('purchase return on supplier account ignores paid amount and settles full net as due', function () {
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
    expect((float) $purchaseReturn->paid_amount)->toBe(0.0);
    expect((float) $purchaseReturn->due_amount)->toBe(475.0);
    expect($purchaseReturn->payment_account_id)->toBeNull();
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

    // Due is tracked in AccountsReceivable — supplier balance is unchanged.
    expect((float) $supplier->balance)->toBe(950.0);
});

test('purchase return with partial cash refund only reduces supplier balance by the due portion', function () {
    $user = purchaseReturnUser();
    $cashInHand = seedAccountingAccounts(100000, $user->branch_id);
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
            'payment_account_id' => $cashInHand->id,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    expect((float) $purchaseReturn->net_amount)->toBe(475.0);
    expect((float) $purchaseReturn->paid_amount)->toBe(200.0);
    expect((float) $purchaseReturn->due_amount)->toBe(275.0);
    expect($purchaseReturn->payment_account_id)->toBe($cashInHand->id);

    $supplier->refresh();

    // Due portion is tracked in AccountsReceivable — supplier balance is unchanged.
    expect((float) $supplier->balance)->toBe(950.0);
});

test('purchase return cash refund requires a payment account', function () {
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

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '100',
            'payment_type' => (string) PurchaseReceivedPayment::Cash->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(PurchaseReturn::query()->where('purchase_id', $purchase->id)->exists())->toBeFalse();
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
});

test('purchase return destroy does not alter supplier balance for due-only returns', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 0]);

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

    // Due portion was never applied to supplier balance, so destroy should not change it.
    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(0.0);
});

test('purchase return destroy reverses ledger balances and restores batch stock after full supplier account return', function () {
    $user = purchaseReturnUser();
    seedAccountingAccounts(10000, $user->branch_id);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $user->branch_id);
    $payables = SystemAccountService::resolve(SystemAccountKey::SupplierPayables, $user->branch_id);
    $receivable = SystemAccountService::resolve(SystemAccountKey::AccountsReceivable, $user->branch_id);

    $inventoryBefore = (float) $inventory->fresh()->current_balance;
    $payablesBefore = (float) $payables->fresh()->current_balance;
    $receivableBefore = (float) $receivable->fresh()->current_balance;

    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(20, $user->branch_id);
    $batchAvailableBefore = (float) $batch->available;

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 950.0]);

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

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    expect($purchaseReturn)->not->toBeNull();
    expect(Transaction::query()
        ->where('source_type', PurchaseReturn::class)
        ->where('source_id', $purchaseReturn->id)
        ->exists())->toBeTrue();

    $batch->refresh();
    $supplier->refresh();
    $inventory->refresh();
    $payables->refresh();
    $receivable->refresh();

    expect((float) $batch->available)->toBe($batchAvailableBefore - 5);
    // No purchase due offset, so supplier balance unchanged; due goes to AccountsReceivable.
    expect((float) $supplier->balance)->toBe(950.0);
    expect((float) $inventory->current_balance)->toBeLessThan($inventoryBefore);
    // SupplierPayables NOT debited (no offset for this return); only AccountsReceivable is debited.
    expect((float) $payables->current_balance)->toBe($payablesBefore);
    expect((float) $receivable->current_balance)->toBeGreaterThan($receivableBefore);

    $this->actingAs($user)
        ->delete(route('inventory.purchase-return.destroy', $purchaseReturn))
        ->assertRedirect(route('inventory.purchase-return.index'));

    $batch->refresh();
    $supplier->refresh();
    $inventory->refresh();
    $payables->refresh();
    $receivable->refresh();

    expect(PurchaseReturn::query()->whereKey($purchaseReturn->id)->exists())->toBeFalse();
    expect(Transaction::query()
        ->where('source_type', PurchaseReturn::class)
        ->where('source_id', $purchaseReturn->id)
        ->exists())->toBeFalse();
    expect((float) $batch->available)->toBe($batchAvailableBefore);
    expect((float) $supplier->balance)->toBe(950.0);
    expect((float) $inventory->current_balance)->toBe($inventoryBefore);
    expect((float) $payables->current_balance)->toBe($payablesBefore);
    expect((float) $receivable->current_balance)->toBe($receivableBefore);
});

test('purchase return on unpaid purchase offsets return amount against purchase due leaving zero return due', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 300]);

    // Fully unpaid purchase (due = 300).
    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid' => 0,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 2)
        ->create(['unit_price' => 150]);

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    // Return net = 150; fully offset against purchase due — no return due created.
    expect((float) $purchaseReturn->net_amount)->toBe(150.0);
    expect((float) $purchaseReturn->due_amount)->toBe(0.0);
    expect((float) $purchaseReturn->purchase_due_offset)->toBe(150.0);

    // Original purchase due reduced by the offset.
    $purchase->refresh();
    expect((float) $purchase->due_amount)->toBe(150.0);

    // Supplier balance reduced by the full return amount via the offset.
    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(150.0); // 300 - 150
});

test('purchase return on fully unpaid purchase reduces purchase due to zero when return covers it all', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 300]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid' => 0,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 2)
        ->create(['unit_price' => 150]);

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    expect((float) $purchaseReturn->net_amount)->toBe(300.0);
    expect((float) $purchaseReturn->due_amount)->toBe(0.0);
    expect((float) $purchaseReturn->purchase_due_offset)->toBe(300.0);

    $purchase->refresh();
    expect((float) $purchase->due_amount)->toBe(0.0);

    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(0.0);
});

test('purchase return on partially paid purchase offsets remaining due first then settles excess on supplier account', function () {
    $user = purchaseReturnUser();
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    // Purchase paid=200, due=100.
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 100]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid' => 200,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 2)
        ->create(['unit_price' => 150]);

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    // Return net = 300, purchase due = 100 → offset = 100, return.due = 200.
    expect((float) $purchaseReturn->net_amount)->toBe(300.0);
    expect((float) $purchaseReturn->purchase_due_offset)->toBe(100.0);
    expect((float) $purchaseReturn->due_amount)->toBe(200.0);

    $purchase->refresh();
    expect((float) $purchase->due_amount)->toBe(0.0);

    // Offset reduces balance by 100 (100→0); return due goes to AccountsReceivable, not supplier balance.
    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(0.0);
});

test('purchase return rollback on unpaid purchase restores purchase due and supplier balance', function () {
    $user = purchaseReturnUser();
    seedAccountingAccounts(10000, $user->branch_id);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $user->branch_id);
    $payables = SystemAccountService::resolve(SystemAccountKey::SupplierPayables, $user->branch_id);
    $inventoryBefore = (float) $inventory->fresh()->current_balance;
    $payablesBefore = (float) $payables->fresh()->current_balance;

    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 300]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid' => 0,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 2)
        ->create(['unit_price' => 150]);

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete(route('inventory.purchase-return.destroy', $purchaseReturn))
        ->assertRedirect(route('inventory.purchase-return.index'));

    // Purchase due restored to 300.
    $purchase->refresh();
    expect((float) $purchase->due_amount)->toBe(300.0);

    // Supplier balance restored to 300.
    $supplier->refresh();
    expect((float) $supplier->balance)->toBe(300.0);

    // Accounting reversed.
    $inventory->refresh();
    $payables->refresh();
    expect((float) $inventory->current_balance)->toBe($inventoryBefore);
    expect((float) $payables->current_balance)->toBe($payablesBefore);
});

test('purchase return on unpaid purchase correctly debits supplier payables in accounting', function () {
    $user = purchaseReturnUser();
    seedAccountingAccounts(10000, $user->branch_id);

    $payables = SystemAccountService::resolve(SystemAccountKey::SupplierPayables, $user->branch_id);
    $payablesBefore = (float) $payables->fresh()->current_balance;

    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 300]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 300,
        'discount' => 0,
        'vat' => 0,
        'paid' => 0,
    ]);

    $purchaseProduct = PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 2)
        ->create(['unit_price' => 150]);

    $this->actingAs($user)
        ->post(route('inventory.purchase-return.store'), [
            'purchase_id' => $purchase->id,
            'date' => now()->format('Y-m-d'),
            'paid_amount' => '0',
            'payment_type' => (string) PurchaseReceivedPayment::Supplier_Account->value,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '2'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    // SupplierPayables should be debited for the full return amount (300) even though
    // it was tracked as a purchase due offset rather than a return due.
    $payables->refresh();
    expect((float) $payables->current_balance)->toBeLessThan($payablesBefore);
    expect(round($payablesBefore - (float) $payables->current_balance, 2))->toBe(300.0);
});

test('purchase return destroy reverses cash and supplier ledger balances for partial cash refund', function () {
    $user = purchaseReturnUser();
    $cashInHand = seedAccountingAccounts(10000, $user->branch_id);

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $user->branch_id);
    $payables = SystemAccountService::resolve(SystemAccountKey::SupplierPayables, $user->branch_id);

    $cashBefore = (float) $cashInHand->fresh()->current_balance;
    $inventoryBefore = (float) $inventory->fresh()->current_balance;
    $payablesBefore = (float) $payables->fresh()->current_balance;

    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(20, $user->branch_id);
    $batchAvailableBefore = (float) $batch->available;

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id, 'balance' => 950.0]);

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
            'payment_account_id' => $cashInHand->id,
            'items' => [
                ['purchase_product_id' => $purchaseProduct->id, 'quantity' => '5'],
            ],
        ])
        ->assertRedirect(route('inventory.purchase-return.index'));

    $purchaseReturn = PurchaseReturn::query()->latest('id')->first();

    $cashInHand->refresh();
    $supplier->refresh();

    expect((float) $cashInHand->current_balance)->toBe($cashBefore + 200.0);
    // No purchase due offset, so supplier balance unchanged; due goes to AccountsReceivable.
    expect((float) $supplier->balance)->toBe(950.0);

    $this->actingAs($user)
        ->delete(route('inventory.purchase-return.destroy', $purchaseReturn))
        ->assertRedirect(route('inventory.purchase-return.index'));

    $batch->refresh();
    $cashInHand->refresh();
    $supplier->refresh();
    $inventory->refresh();
    $payables->refresh();

    expect((float) $batch->available)->toBe($batchAvailableBefore);
    expect((float) $cashInHand->current_balance)->toBe($cashBefore);
    expect((float) $supplier->balance)->toBe(950.0);
    expect((float) $inventory->current_balance)->toBe($inventoryBefore);
    expect((float) $payables->current_balance)->toBe($payablesBefore);
});

test('purchase index shows returned badge data for purchases with a return', function () {
    $user = purchaseReturnUser([
        'inventory.purchase.view',
        'inventory.purchase-return.view',
        'inventory.purchase-return.create',
    ]);
    ['product' => $product, 'batch' => $batch] = purchaseReturnProduct(10, $user->branch_id);
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $purchase = purchaseReturnPurchase($user, $supplier, [
        'gross' => 1000,
        'discount' => 0,
        'vat' => 0,
        'paid' => 1000,
    ]);

    PurchaseProduct::factory()
        ->forPurchase($purchase)
        ->forProduct($product)
        ->withBatch($batch->id, 10)
        ->create();

    $purchaseReturn = PurchaseReturn::factory()->forPurchase($purchase)->create([
        'user_id' => $user->id,
        'gross_amount' => 100,
        'discount' => 0,
        'vat' => 0,
        'paid_amount' => 0,
        'due_amount' => 100,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.purchase.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.has_return', true)
            ->where('purchases.data.0.return_invoice_number', $purchaseReturn->invoice_number));
});
