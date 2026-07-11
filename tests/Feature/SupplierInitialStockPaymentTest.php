<?php

use App\Enums\PurchaseType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Spatie\Permission\Models\Permission;

test('product initial stock supplier payment appears in supplier payment menu', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('party.supplier-payment.view', 'web');
    Permission::findOrCreate('product.create', 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['product.create', 'party.supplier-payment.view']);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'balance' => 0]);

    $payload = validProductPayload([
        'initial_stock' => '10',
        'purchase_price' => '100',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '600',
        'initial_stock_payment_account_id' => (string) $cash->id,
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull();

    $purchase = Purchase::query()
        ->where('product_id', $product->id)
        ->initialStock()
        ->first();

    expect($purchase)->not->toBeNull()
        ->and((float) $purchase->gross_amount)->toBe(1000.0)
        ->and((float) $purchase->paid_amount)->toBe(600.0)
        ->and((float) $purchase->due_amount)->toBe(400.0)
        ->and($purchase->purchase_type)->toBe(PurchaseType::InitialStock);

    $payment = SupplierPayment::query()->where('supplier_id', $supplier->id)->first();

    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(600.0)
        ->and($payment->allocations)->toHaveCount(1)
        ->and((int) $payment->allocations->first()->purchase_id)->toBe($purchase->id);

    expect((float) $supplier->fresh()->balance)->toBe(400.0);

    $this->actingAs($admin)
        ->get(route('party.supplier-payment.index', ['search' => $payment->serial]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/supplier-payment/index')
            ->has('payments.data', 1)
            ->where('payments.data.0.amount', '600.00'));
});

test('supplier opening balance and initial stock due appear in due purchases api', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('party.supplier-payment.view', 'web');
    Permission::findOrCreate('party.supplier.create', 'web');
    Permission::findOrCreate('product.create', 'web');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $user->givePermissionTo([
        'party.supplier-payment.view',
        'party.supplier.create',
        'product.create',
    ]);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $supplierName = 'Opening Supplier '.fake()->unique()->numerify('######');

    $this->actingAs($user)
        ->post(route('party.supplier.store'), [
            'name' => $supplierName,
            'phone' => '017'.fake()->unique()->numerify('########'),
            'company_name' => 'Opening Co',
            'opening_balance' => '1500',
        ])
        ->assertRedirect();

    $supplier = Supplier::query()->where('name', $supplierName)->first();

    expect($supplier)->not->toBeNull();

    $openingPurchase = Purchase::query()
        ->where('supplier_id', $supplier->id)
        ->openingBalance()
        ->first();

    expect($openingPurchase)->not->toBeNull()
        ->and((float) $openingPurchase->due_amount)->toBe(1500.0);

    $payload = validProductPayload([
        'initial_stock' => '5',
        'purchase_price' => '200',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '0',
    ]);

    $this->actingAs($user)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $this->actingAs($user)
        ->getJson(route('api.suppliers.due-purchases', $supplier))
        ->assertOk()
        ->assertJsonCount(2, 'purchases')
        ->assertJsonFragment(['purchase_type_label' => 'Opening Balance'])
        ->assertJsonFragment(['purchase_type_label' => 'Initial Stock']);
});

test('supplier payment can clear initial stock due from product settlement', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $user->givePermissionTo([
        'party.supplier-payment.view',
        'party.supplier-payment.create',
        'product.create',
    ]);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID, 'balance' => 0]);

    $payload = validProductPayload([
        'initial_stock' => '4',
        'purchase_price' => '250',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '0',
    ]);

    $this->actingAs($user)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();
    $purchase = Purchase::query()->initialStock()->where('supplier_id', $supplier->id)->first();

    expect($purchase)->not->toBeNull()
        ->and((float) $purchase->due_amount)->toBe(1000.0)
        ->and((float) $product->initial_stock_paid_amount)->toBe(0.0);

    $this->actingAs($user)
        ->post('/party/supplier-payment', [
            'supplier_id' => $supplier->id,
            'date' => '2026-07-11',
            'payment_account_id' => $cash->id,
            'allocations' => [
                ['purchase_id' => $purchase->id, 'amount' => 400],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $purchase->refresh();
    $supplier->refresh();
    $product->refresh();

    expect((float) $purchase->paid_amount)->toBe(400.0)
        ->and((float) $purchase->due_amount)->toBe(600.0)
        ->and((float) $supplier->balance)->toBe(600.0)
        ->and((float) $product->initial_stock_paid_amount)->toBe(400.0)
        ->and($product->initial_stock_payment_account_id)->toBe($cash->id);
});

test('daily summary initial stock paid uses supplier payment allocations', function () {
    $this->artisan('permissions:sync');

    Permission::findOrCreate('product.create', 'web');
    Permission::findOrCreate(ReportController::PERMISSION_DAILY_SUMMARY, 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    $reportAdmin = User::factory()->create(['branch_id' => null]);
    $reportAdmin->givePermissionTo(ReportController::PERMISSION_DAILY_SUMMARY);

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $date = '2026-07-16';
    $this->travelTo($date.' 10:00:00');

    $payload = validProductPayload([
        'initial_stock' => '10',
        'purchase_price' => '50',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '200',
        'initial_stock_payment_account_id' => (string) $cash->id,
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $this->travelBack();

    $this->actingAs($reportAdmin)
        ->get('/report/daily-summary?date='.$date.'&branch_id='.Branch::MAIN_BRANCH_ID)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/daily-summary')
            ->where('summary.initial_stock.gross', fn ($value) => (float) $value >= 500.0)
            ->where('summary.initial_stock.paid', fn ($value) => (float) $value >= 200.0)
            ->where('summary.initial_stock.due', fn ($value) => (float) $value >= 300.0)
            ->where('summary.supplier_payments.count', fn ($value) => (int) $value >= 1));
});
