<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function supplierPaymentUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function supplierDuePurchase(User $user, Supplier $supplier, float $dueAmount): Purchase
{
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    test()->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '0',
            'comment' => null,
            'items' => [
                [
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'unit_price' => (string) $dueAmount,
                    'quantity' => '1',
                    'free_quantity' => '0',
                ],
            ],
        ])
        ->assertRedirect();

    $purchase = Purchase::query()->latest('id')->first();

    expect($purchase)->not->toBeNull();
    expect((float) $purchase->due_amount)->toBe($dueAmount);

    return $purchase;
}

test('guests cannot access supplier payments', function () {
    $this->get('/party/supplier-payment')->assertRedirect(route('login'));
});

test('user without permission cannot view supplier payments', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser();

    $this->actingAs($user)
        ->get('/party/supplier-payment')
        ->assertForbidden();
});

test('user can record supplier payment against purchases and reduce supplier due', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'inventory.purchase.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 5000);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'comment' => 'Partial payment',
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 2000],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $supplier->refresh();
    $purchase->refresh();

    expect((float) $supplier->balance)->toBe(3000.0);
    expect((float) $purchase->paid_amount)->toBe(2000.0);
    expect((float) $purchase->due_amount)->toBe(3000.0);

    $payment = SupplierPayment::query()->where('supplier_id', $supplier->id)->first();

    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(2000.0);
    expect($payment->comment)->toBe('Partial payment');
    expect($payment->created_by)->toBe($user->id);
    expect($payment->allocations)->toHaveCount(1);
    expect((float) $payment->allocations->first()->amount)->toBe(2000.0);
});

test('supplier payment can allocate across multiple purchases', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'inventory.purchase.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $firstPurchase = supplierDuePurchase($user, $supplier, 1000);
    $secondPurchase = supplierDuePurchase($user, $supplier, 1500);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $firstPurchase->id, 'amount' => 400],
                ['purchase_id' => $secondPurchase->id, 'amount' => 600],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect((float) $supplier->fresh()->balance)->toBe(1500.0);
    expect((float) $firstPurchase->fresh()->due_amount)->toBe(600.0);
    expect((float) $secondPurchase->fresh()->due_amount)->toBe(900.0);
});

test('payment allocation cannot exceed purchase due amount', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'inventory.purchase.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 100);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 500],
            ],
        ])
        ->assertSessionHasErrors('allocations.0.amount');

    expect((float) $supplier->fresh()->balance)->toBe(100.0);
    expect(SupplierPayment::query()->where('supplier_id', $supplier->id)->exists())->toBeFalse();
});

test('user can delete supplier payment and restore supplier and purchase due', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.delete',
        'inventory.purchase.create',
    ]);
    seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 3000);

    $payment = SupplierPayment::create([
        'branch_id' => $user->branch_id,
        'supplier_id' => $supplier->id,
        'date' => '2026-06-04',
        'amount' => 2000,
        'serial' => 'INVSP00000001',
        'created_by' => $user->id,
    ]);

    SupplierPaymentAllocation::create([
        'supplier_payment_id' => $payment->id,
        'purchase_id' => $purchase->id,
        'amount' => 2000,
    ]);

    $purchase->update(['paid_amount' => 2000, 'due_amount' => 1000]);
    $supplier->update(['balance' => 1000]);

    $this->actingAs($user)
        ->delete("/party/supplier-payment/{$payment->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SupplierPayment::find($payment->id))->toBeNull();
    expect((float) $supplier->fresh()->balance)->toBe(3000.0);
    expect((float) $purchase->fresh()->paid_amount)->toBe(0.0);
    expect((float) $purchase->fresh()->due_amount)->toBe(3000.0);
});

test('user can update supplier payment allocations and supplier due', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'party.supplier-payment.update',
        'inventory.purchase.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $firstPurchase = supplierDuePurchase($user, $supplier, 1000);
    $secondPurchase = supplierDuePurchase($user, $supplier, 1500);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $firstPurchase->id, 'amount' => 300],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment = SupplierPayment::query()->latest('id')->first();

    $this->actingAs($user)
        ->put("/party/supplier-payment/{$payment->id}", [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-05',
            'payment_account_id' => $cash->id,
            'comment' => 'Updated payment',
            'allocations' => [
                ['purchase_id' => $firstPurchase->id, 'amount' => 500],
                ['purchase_id' => $secondPurchase->id, 'amount' => 400],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $payment->refresh();

    expect((float) $payment->amount)->toBe(900.0);
    expect($payment->comment)->toBe('Updated payment');
    expect((float) $supplier->fresh()->balance)->toBe(1600.0);
    expect((float) $firstPurchase->fresh()->paid_amount)->toBe(500.0);
    expect((float) $firstPurchase->fresh()->due_amount)->toBe(500.0);
    expect((float) $secondPurchase->fresh()->paid_amount)->toBe(400.0);
    expect((float) $secondPurchase->fresh()->due_amount)->toBe(1100.0);
    expect($payment->fresh()->allocations)->toHaveCount(2);
});

test('supplier payment shows warning when payment account has insufficient balance', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'inventory.purchase.create',
    ]);
    $cash = seedAccountingAccounts(minimumBalance: 100, user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 5000);

    $this->actingAs($user)
        ->from(route('party.supplier-payment.index'))
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 2000],
            ],
        ])
        ->assertRedirect(route('party.supplier-payment.index'))
        ->assertSessionHas('warning', 'Insufficient balance in the selected payment account.');

    expect((float) $supplier->fresh()->balance)->toBe(5000.0);
    expect((float) $purchase->fresh()->due_amount)->toBe(5000.0);
    expect(SupplierPayment::query()->where('supplier_id', $supplier->id)->exists())->toBeFalse();
});

test('branch user cannot delete payment from another branch', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser(['party.supplier-payment.delete']);
    $otherBranch = Branch::factory()->create();

    $payment = SupplierPayment::create([
        'branch_id' => $otherBranch->id,
        'supplier_id' => Supplier::factory()->create(['branch_id' => $otherBranch->id])->id,
        'date' => '2026-06-04',
        'amount' => 500,
        'serial' => 'INVSP00000099',
    ]);

    $this->actingAs($user)
        ->delete("/party/supplier-payment/{$payment->id}")
        ->assertNotFound();
});

test('supplier payment index includes payment account from journal', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'inventory.purchase.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 1000);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 300],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->get(route('party.supplier-payment.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/supplier-payment/index')
            ->has('payments.data', 1)
            ->where('payments.data.0.payment_account_id', $cash->id)
            ->where('payments.data.0.payment_account_label', fn ($label) => is_string($label) && $label !== ''));
});

test('purchase edit shows payment account from supplier payment allocation', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.create',
        'inventory.purchase.create',
        'inventory.purchase.update',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 5000);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 3000],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->get(route('inventory.purchase.edit', $purchase))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/edit')
            ->where('purchase.payment_account_id', $cash->id)
            ->where('purchase.paid_amount', 3000)
            ->has('purchase.supplier_payment_allocations', 1)
            ->where('purchase.supplier_payment_allocations.0.payment_account_id', $cash->id)
            ->where('purchase.supplier_payment_allocations.0.amount', 3000));
});

test('purchase show reflects supplier payment allocation with updated due and payment details', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.create',
        'party.supplier-payment.view',
        'inventory.purchase.create',
        'inventory.purchase.view',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $purchase = supplierDuePurchase($user, $supplier, 5000);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 3000],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $purchase->refresh();

    $this->actingAs($user)
        ->get(route('inventory.purchase.show', $purchase))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/show')
            ->where('purchase.paid_amount', '3000.00')
            ->where('purchase.due_amount', '2000.00')
            ->has('purchase.supplier_payment_details', 1)
            ->where('purchase.supplier_payment_details.0.amount', 3000)
            ->where('purchase.supplier_payment_details.0.payment_account_id', $cash->id));

    $this->actingAs($user)
        ->get('/party/supplier-payment')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/supplier-payment/index')
            ->has('payments.data', 1)
            ->where('payments.data.0.allocations.0.amount', 3000)
            ->where('payments.data.0.allocations.0.document.paid_amount', 3000)
            ->where('payments.data.0.allocations.0.document.due_amount', 2000));
});
