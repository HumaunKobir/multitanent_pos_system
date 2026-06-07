<?php

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierPayment;
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

test('user can record supplier payment and reduce supplier due', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'party.supplier.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $this->actingAs($user)->post('/party/supplier', [
        'name' => 'Payable Supplier '.fake()->unique()->numerify('####'),
        'phone' => fake()->unique()->numerify('01#########'),
        'opening_balance' => '5000',
    ]);

    $supplier = Supplier::query()->latest('id')->first();

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'amount' => 2000,
            'payment_account_id' => $cash->id,
            'comment' => 'Partial payment',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $supplier->refresh();

    expect((float) $supplier->balance)->toBe(3000.0);

    $payment = SupplierPayment::query()->where('supplier_id', $supplier->id)->first();

    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(2000.0);
    expect($payment->comment)->toBe('Partial payment');
    expect($payment->created_by)->toBe($user->id);
});

test('payment amount cannot exceed supplier due balance', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 100,
    ]);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-06-04',
            'amount' => 500,
            'payment_account_id' => $cash->id,
        ])
        ->assertSessionHasErrors('amount');

    expect((float) $supplier->fresh()->balance)->toBe(100.0);
    expect(SupplierPayment::query()->where('supplier_id', $supplier->id)->exists())->toBeFalse();
});

test('user can delete supplier payment and restore supplier due', function () {
    $this->artisan('permissions:sync');

    $user = supplierPaymentUser([
        'party.supplier-payment.view',
        'party.supplier-payment.delete',
    ]);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 3000,
    ]);

    $payment = SupplierPayment::create([
        'branch_id' => $user->branch_id,
        'supplier_id' => $supplier->id,
        'date' => '2026-06-04',
        'amount' => 2000,
        'serial' => 'INVSP00000001',
        'created_by' => $user->id,
    ]);

    $supplier->update(['balance' => 1000]);

    $this->actingAs($user)
        ->delete("/party/supplier-payment/{$payment->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(SupplierPayment::find($payment->id))->toBeNull();
    expect((float) $supplier->fresh()->balance)->toBe(3000.0);
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
