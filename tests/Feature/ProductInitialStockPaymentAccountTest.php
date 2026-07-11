<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Services\BranchPaymentAccountService;
use App\Services\SystemAccountService;

test('branch payment account service accepts global accounts for main branch products', function () {
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $globalCash = BranchPaymentAccountService::listForBranch(null)->first();
    expect($globalCash)->not->toBeNull();

    expect(BranchPaymentAccountService::isValid($globalCash->id, Branch::MAIN_BRANCH_ID))->toBeTrue();

    $account = BranchPaymentAccountService::find($globalCash->id, Branch::MAIN_BRANCH_ID);
    expect($account)->not->toBeNull()
        ->and($account->id)->toBe($globalCash->id);
});

test('branch payment account service rejects global accounts for operating branch products', function () {
    $operatingBranch = Branch::factory()->create();
    seedAccountingAccounts(branchId: $operatingBranch->id);
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $globalCash = BranchPaymentAccountService::listForBranch(null)->first();

    expect(BranchPaymentAccountService::isValid($globalCash->id, $operatingBranch->id))->toBeFalse();
});

test('product store accepts branch payment account resolved for main branch product', function () {
    $admin = productStoreAdmin();
    $cash = seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $payload = validProductPayload([
        'initial_stock' => '10',
        'purchase_price' => '100',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '1000',
        'initial_stock_payment_account_id' => (string) $cash->id,
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    $transaction = Transaction::query()
        ->where('source_type', Product::class)
        ->where('source_id', $product->id)
        ->first();

    expect($transaction)->not->toBeNull();

    $ledgers = Ledger::query()->where('transaction_id', $transaction->id)->get();
    $payablesId = SystemAccountService::id(SystemAccountKey::SupplierPayables, Branch::MAIN_BRANCH_ID);

    expect(round($ledgers->sum('debit'), 2))->toBe(1000.0)
        ->and(round($ledgers->sum('credit'), 2))->toBe(1000.0)
        ->and(round((float) $ledgers->firstWhere('account_id', $payablesId)?->credit, 2))->toBe(1000.0);

    $payment = SupplierPayment::query()->where('supplier_id', $supplier->id)->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(1000.0);
});

test('product store returns validation warning when payment account balance is insufficient', function () {
    $admin = productStoreAdmin();
    $cash = seedAccountingAccounts(minimumBalance: 100, branchId: Branch::MAIN_BRANCH_ID);
    $supplier = Supplier::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $payload = validProductPayload([
        'initial_stock' => '10',
        'purchase_price' => '100',
        'initial_stock_supplier_id' => (string) $supplier->id,
        'initial_stock_paid_amount' => '1000',
        'initial_stock_payment_account_id' => (string) $cash->id,
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect()
        ->assertSessionHasErrors('initial_stock_payment_account_id');

    expect(Product::query()->where('name', $payload['name'])->exists())->toBeFalse();
});
