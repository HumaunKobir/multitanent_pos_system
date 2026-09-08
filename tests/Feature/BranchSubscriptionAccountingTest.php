<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BranchSubscriptionAccountingService;
use App\Services\BranchSubscriptionService;
use App\Services\SystemAccountService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    test()->artisan('permissions:sync');

    Branch::firstOrCreate(['id' => Branch::MAIN_BRANCH_ID], [
        'name' => Branch::MAIN_BRANCH_NAME,
        'status' => 1,
        'subscription_status' => 'lifetime',
    ]);

    SystemAccountService::seed(null);
});

test('system accounts for subscription exist on branch and superadmin charts', function () {
    $branch = Branch::factory()->create();
    SystemAccountService::seed($branch->id);

    // Branch panel has subscription payable and subscription expense
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);

    expect($branchPayable)->not->toBeNull();
    expect($branchPayable->type->value)->toBe(\App\Enums\AccountType::Liability->value);
    expect($branchPayable->source_type)->toBe(Branch::class);
    expect((int) $branchPayable->source_id)->toBe($branch->id);

    expect($branchExpense)->not->toBeNull();
    expect($branchExpense->type->value)->toBe(\App\Enums\AccountType::Expenses->value);
    expect($branchExpense->source_type)->toBe(Branch::class);
    expect((int) $branchExpense->source_id)->toBe($branch->id);

    // SuperAdmin global panel has subscription income
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    expect($superadminIncome)->not->toBeNull();
    expect($superadminIncome->type->value)->toBe(\App\Enums\AccountType::Income->value);
    expect($superadminIncome->source_type)->toBeNull();
    expect($superadminIncome->source_id)->toBeNull();
});

test('cycle reached accrues subscription expense and subscription payable on branch panel', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Khulna',
        'subscription_fee' => 2000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $accountingService = app(BranchSubscriptionAccountingService::class);
    $transaction = $accountingService->recordCycleAccrual($branch, '2026-09-01', '2026-10-01', 2000);

    expect($transaction)->not->toBeNull();
    expect((float) $transaction->amount)->toBe(2000.0);

    $payable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $expense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);

    // Liability balance increased
    expect((float) $payable->fresh()->current_balance)->toBe(2000.0);
    // Expense balance increased
    expect((float) $expense->fresh()->current_balance)->toBe(2000.0);

    // Calling again does not duplicate accrual
    $duplicate = $accountingService->recordCycleAccrual($branch, '2026-09-01', '2026-10-01', 2000);
    expect($duplicate->id)->toBe($transaction->id);
    expect((float) $payable->fresh()->current_balance)->toBe(2000.0);
});

test('superadmin approving payment settles branch payable and records superadmin income and cash', function () {
    Storage::fake('public');

    $branch = Branch::factory()->create([
        'name' => 'Branch Barisal',
        'subscription_fee' => 1500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    // 1. Accrue cycle bill
    $subscriptionService = app(BranchSubscriptionService::class);
    $subscriptionService->syncCycleAccrual($branch);

    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);

    expect((float) $branchPayable->fresh()->current_balance)->toBe(1500.0);
    $initialSuperadminIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialSuperadminCash = (float) $superadminBkash->fresh()->current_balance;

    // 2. Client submits payment
    $clientUser = User::factory()->create(['branch_id' => $branch->id]);
    $receipt = UploadedFile::fake()->image('bkash_receipt.png');

    $this->actingAs($clientUser)->post(route('branch-panel.subscription.pay'), [
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bkash',
        'transaction_reference' => 'BKASH-ACC-TRX-1234',
        'paid_at' => '2026-09-09',
        'notes' => 'Subscription monthly renewal',
        'attachment' => $receipt,
    ]);

    $pendingPayment = BranchSubscriptionPayment::where('branch_id', $branch->id)->latest('id')->first();
    expect($pendingPayment->status)->toBe('pending');

    // 3. SuperAdmin approves payment
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['branch.view', 'branch.update']);

    $this->actingAs($admin)->post(route('branch-clients.renew', $branch->id), [
        'pending_payment_id' => $pendingPayment->id,
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bkash',
        'transaction_reference' => 'BKASH-ACC-TRX-1234',
        'paid_at' => '2026-09-09',
        'notes' => 'Verified deposit on bKash merchant account',
        'existing_attachment_path' => $pendingPayment->attachment_path,
    ])->assertRedirect();

    // 4. Verify Branch Ledger:
    // Expense: Subscription Expense increased by 1500
    // Liability: Subscription Payable decreased by 1500 to 0 (cleared)
    // Asset: Branch bKash decreased by 1500
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    expect((float) $branchExpense->fresh()->current_balance)->toBe(1500.0);
    expect((float) $branchPayable->fresh()->current_balance)->toBe(0.0);
    expect((float) $branchBkash->fresh()->current_balance)->toBe(-1500.0);

    // 5. Verify SuperAdmin Ledger: Subscription Income increased by 1500 & bKash asset increased by 1500
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialSuperadminIncome + 1500.0);
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminCash + 1500.0);

    // 6. Verify Transaction entries exist
    $branchSettlementTx = Transaction::query()
        ->where('source_type', BranchSubscriptionPayment::class)
        ->where('source_id', $pendingPayment->id)
        ->where('debit_account_id', $branchPayable->id)
        ->first();

    expect($branchSettlementTx)->not->toBeNull();
    expect((float) $branchSettlementTx->amount)->toBe(1500.0);

    $superadminSettlementTx = Transaction::query()
        ->where('source_type', BranchSubscriptionPayment::class)
        ->where('source_id', $pendingPayment->id)
        ->where('credit_account_id', $superadminIncome->id)
        ->first();

    expect($superadminSettlementTx)->not->toBeNull();
    expect((float) $superadminSettlementTx->amount)->toBe(1500.0);
});

test('superadmin direct renewal posts expense, decreases asset, clears payable, and increases superadmin income', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Sylhet',
        'subscription_fee' => 2500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['branch.view', 'branch.update']);

    $initialSuperadminIncome = (float) SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null)->fresh()->current_balance;
    $initialSuperadminNagad = (float) SystemAccountService::resolve(SystemAccountKey::Nagad, null)->fresh()->current_balance;

    // Direct renewal by SuperAdmin
    $this->actingAs($admin)->post(route('branch-clients.renew', $branch->id), [
        'duration_days' => 30,
        'amount' => 2500,
        'payment_method' => 'Nagad',
        'transaction_reference' => 'NAGAD-TRX-DIRECT-999',
        'paid_at' => '2026-09-09',
        'notes' => 'Direct counter renewal by SuperAdmin',
    ])->assertRedirect();

    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchNagad = SystemAccountService::resolve(SystemAccountKey::Nagad, $branch->id);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminNagad = SystemAccountService::resolve(SystemAccountKey::Nagad, null);

    // 1. Client Branch Expense increased by 2500
    expect((float) $branchExpense->fresh()->current_balance)->toBe(2500.0);

    // 2. Client Branch Payable liability settled (net 0)
    expect((float) $branchPayable->fresh()->current_balance)->toBe(0.0);

    // 3. Client Branch Asset decreased by 2500
    expect((float) $branchNagad->fresh()->current_balance)->toBe(-2500.0);

    // 4. SuperAdmin Income increased by 2500
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialSuperadminIncome + 2500.0);

    // 5. SuperAdmin Asset increased by 2500
    expect((float) $superadminNagad->fresh()->current_balance)->toBe($initialSuperadminNagad + 2500.0);
});
