<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\BranchSecurityDepositService;
use App\Services\BranchSubscriptionService;
use App\Services\SubscriptionInvoiceService;
use App\Services\SystemAccountService;

beforeEach(function () {
    test()->artisan('permissions:sync');

    Branch::firstOrCreate(['id' => Branch::MAIN_BRANCH_ID], [
        'name' => Branch::MAIN_BRANCH_NAME,
        'status' => 1,
        'subscription_status' => 'lifetime',
    ]);

    SystemAccountService::seed(null);
});

test('project handover security money records liability on superadmin and asset on client branch', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Chittagong Security Check',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);
    $superadminSecurity = SystemAccountService::resolve(SystemAccountKey::ClientSecurityDeposit, null);
    $branchSecurityAsset = SystemAccountService::resolve(SystemAccountKey::SecurityDepositPaid, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    $initialSuperadminBkash = (float) $superadminBkash->fresh()->current_balance;
    $initialSuperadminSecurity = (float) $superadminSecurity->fresh()->current_balance;

    $admin = User::factory()->create(['branch_id' => null]);
    $depositService = app(BranchSecurityDepositService::class);

    $deposit = $depositService->recordDeposit($branch, [
        'amount' => 10000,
        'payment_method' => 'bKash',
        'transaction_reference' => 'SEC-BKASH-9988',
        'paid_at' => '2026-09-09',
        'notes' => 'Project handover security deposit',
    ], $admin);

    expect($deposit->status)->toBe('approved')
        ->and((float) $deposit->amount)->toBe(10000.0);

    // SuperAdmin ledger: bKash increased by 10,000, Client Security Deposit liability increased by 10,000
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminBkash + 10000.0);
    expect((float) $superadminSecurity->fresh()->current_balance)->toBe($initialSuperadminSecurity + 10000.0);

    // Client Branch ledger: Security Deposit Paid asset increased by 10,000, bKash decreased by 10,000
    expect((float) $branchSecurityAsset->fresh()->current_balance)->toBe(10000.0);
    expect((float) $branchBkash->fresh()->current_balance)->toBe(-10000.0);
});

test('generating subscription invoice posts expense payable receivable and income', function () {
    SystemAccountService::seed(null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminReceivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $initialIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialReceivable = (float) $superadminReceivable->fresh()->current_balance;

    $branch = Branch::factory()->create([
        'name' => 'Branch Invoice Accrual',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $invoiceService = app(SubscriptionInvoiceService::class);
    $invoice = $invoiceService->generateInvoice($branch, '2026-10-01', '2026-11-01', 1000);

    expect($invoice->status)->toBe('unpaid')
        ->and((float) $invoice->due_amount)->toBe(1000.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 1000.0)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($initialReceivable + 1000.0)
        ->and((float) SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id)->fresh()->current_balance)->toBe(1000.0)
        ->and((float) SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id)->fresh()->current_balance)->toBe(1000.0);
});

test('client payment submission remains pending without settlement until superadmin approves', function () {
    SystemAccountService::seed(null);

    $branch = Branch::factory()->create([
        'name' => 'Branch Pending Review',
        'subscription_fee' => 1500,
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 30,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    app(SubscriptionInvoiceService::class)->generateInvoice($branch, '2026-09-01', '2026-10-01', 1500);

    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminReceivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    $incomeAfterAccrual = (float) $superadminIncome->fresh()->current_balance;
    $receivableAfterAccrual = (float) $superadminReceivable->fresh()->current_balance;
    $initialSuperadminBkash = (float) $superadminBkash->fresh()->current_balance;
    $expenseAfterAccrual = (float) $branchExpense->fresh()->current_balance;
    $payableAfterAccrual = (float) $branchPayable->fresh()->current_balance;
    $initialBranchBkash = (float) $branchBkash->fresh()->current_balance;

    $subscriptionService = app(BranchSubscriptionService::class);
    $clientUser = User::factory()->create(['branch_id' => $branch->id]);

    $pendingPayment = $subscriptionService->submitPayment($branch, [
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'transaction_reference' => 'PENDING-BKASH-7788',
        'paid_at' => '2026-09-09',
        'notes' => 'Monthly bill submitted',
    ], $clientUser);

    expect($pendingPayment->status)->toBe('pending')
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($incomeAfterAccrual)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($receivableAfterAccrual)
        ->and((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminBkash)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($expenseAfterAccrual)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe($payableAfterAccrual)
        ->and((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash);

    $admin = User::factory()->create(['branch_id' => null]);
    $approvedPayment = $subscriptionService->renew($branch, [
        'pending_payment_id' => $pendingPayment->id,
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'transaction_reference' => 'PENDING-BKASH-7788',
        'paid_at' => '2026-09-09',
    ], $admin);

    expect($approvedPayment->status)->toBe('approved')
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($incomeAfterAccrual)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($receivableAfterAccrual - 1500.0)
        ->and((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminBkash + 1500.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($expenseAfterAccrual)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(0.0)
        ->and((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 1500.0);
});

test('partial payment leaves remaining receivable payable and keeps full accrued income', function () {
    SystemAccountService::seed(null);

    $branch = Branch::factory()->create([
        'name' => 'Branch Partial Payment',
        'subscription_fee' => 1000,
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 30,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminReceivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    $invoiceService = app(SubscriptionInvoiceService::class);
    $subscriptionService = app(BranchSubscriptionService::class);

    $invoice = $invoiceService->generateInvoice($branch, '2026-09-01', '2026-10-01', 1000);

    $incomeAfterAccrual = (float) $superadminIncome->fresh()->current_balance;
    $receivableAfterAccrual = (float) $superadminReceivable->fresh()->current_balance;
    $expenseAfterAccrual = (float) $branchExpense->fresh()->current_balance;
    $initialBkash = (float) $superadminBkash->fresh()->current_balance;
    $initialBranchBkash = (float) $branchBkash->fresh()->current_balance;

    $admin = User::factory()->create(['branch_id' => null]);
    $subscriptionService->renew($branch, [
        'duration_days' => 30,
        'amount' => 600,
        'payment_method' => 'bKash',
        'transaction_reference' => 'BKASH-PART-600',
        'paid_at' => '2026-09-09',
        'notes' => 'Partial payment ৳600',
    ], $admin);

    $invoice->refresh();
    expect($invoice->status)->toBe('partial')
        ->and((float) $invoice->paid_amount)->toBe(600.0)
        ->and((float) $invoice->due_amount)->toBe(400.0)
        ->and((float) $superadminBkash->fresh()->current_balance)->toBe($initialBkash + 600.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($incomeAfterAccrual)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($receivableAfterAccrual - 600.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($expenseAfterAccrual)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(400.0)
        ->and((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 600.0);

    $subscriptionService->renew($branch, [
        'duration_days' => 30,
        'amount' => 400,
        'payment_method' => 'bKash',
        'transaction_reference' => 'BKASH-PART-400',
        'paid_at' => '2026-09-09',
        'notes' => 'Final installment ৳400',
    ], $admin);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')
        ->and((float) $invoice->due_amount)->toBe(0.0)
        ->and((float) $superadminBkash->fresh()->current_balance)->toBe($initialBkash + 1000.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($incomeAfterAccrual)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($receivableAfterAccrual - 1000.0)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(0.0)
        ->and((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 1000.0);
});

test('excess payment is recorded as advance and auto applies by clearing payable receivable', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Advance Flow',
        'subscription_fee' => 1000,
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 30,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $superadminAdvance = SystemAccountService::resolve(SystemAccountKey::AdvanceFromClient, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminReceivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $branchPrepaid = SystemAccountService::resolve(SystemAccountKey::PrepaidSubscription, $branch->id);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);

    $invoiceService = app(SubscriptionInvoiceService::class);
    $subscriptionService = app(BranchSubscriptionService::class);

    $invoice1 = $invoiceService->generateInvoice($branch, '2026-09-01', '2026-10-01', 1000);

    $incomeAfterFirst = (float) $superadminIncome->fresh()->current_balance;
    $receivableAfterFirst = (float) $superadminReceivable->fresh()->current_balance;
    $expenseAfterFirst = (float) $branchExpense->fresh()->current_balance;
    $initialAdvance = (float) $superadminAdvance->fresh()->current_balance;
    $initialPrepaid = (float) $branchPrepaid->fresh()->current_balance;

    $admin = User::factory()->create(['branch_id' => null]);
    $subscriptionService->renew($branch, [
        'duration_days' => 30,
        'amount' => 3000,
        'payment_method' => 'bKash',
        'transaction_reference' => 'ADV-BKASH-3000',
        'paid_at' => '2026-09-09',
        'notes' => 'Advance payment 3 months',
    ], $admin);

    $invoice1->refresh();
    expect($invoice1->status)->toBe('paid')
        ->and((float) $superadminAdvance->fresh()->current_balance)->toBe($initialAdvance + 2000.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($incomeAfterFirst)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($receivableAfterFirst - 1000.0)
        ->and((float) $branchPrepaid->fresh()->current_balance)->toBe($initialPrepaid + 2000.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($expenseAfterFirst)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(0.0);

    $invoice2 = $invoiceService->generateInvoice($branch, '2026-10-01', '2026-11-01', 1000);

    $invoice2->refresh();
    expect($invoice2->status)->toBe('paid')
        ->and((float) $invoice2->paid_amount)->toBe(1000.0)
        ->and((float) $superadminAdvance->fresh()->current_balance)->toBe($initialAdvance + 1000.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($incomeAfterFirst + 1000.0)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($receivableAfterFirst - 1000.0)
        ->and((float) $branchPrepaid->fresh()->current_balance)->toBe($initialPrepaid + 1000.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($expenseAfterFirst + 1000.0)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(0.0);
});

test('scheduled command processes overdue branches and generates missing invoices', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Cron Test',
        'subscription_fee' => 1500,
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 2,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-09-03',
        'subscription_status' => 'active',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $this->travelTo('2026-09-09');

    $this->artisan('subscription:process-billing')
        ->assertSuccessful();

    $invoices = SubscriptionInvoice::where('branch_id', $branch->id)->get();
    expect($invoices->count())->toBeGreaterThanOrEqual(3);
});

test('changing branch subscription fee updates open invoice and reverses and updates COA ledger balances', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Fee Adjustment Coa Test',
        'subscription_fee' => 1500,
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 30,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
        'subscription_status' => 'active',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminReceivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);

    $initialIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialReceivable = (float) $superadminReceivable->fresh()->current_balance;

    $invoiceService = app(SubscriptionInvoiceService::class);
    $subscriptionService = app(BranchSubscriptionService::class);

    // 1. Generate initial invoice with fee = 1500
    $invoice = $invoiceService->generateInvoice($branch, '2026-09-01', '2026-10-01', 1500);

    expect($invoice->status)->toBe('unpaid')
        ->and((float) $invoice->total_amount)->toBe(1500.0)
        ->and((float) $invoice->due_amount)->toBe(1500.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 1500.0)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($initialReceivable + 1500.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe(1500.0)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(1500.0);

    // 2. Admin updates the branch subscription fee to 1000
    $branch->update(['subscription_fee' => 1000]);
    $subscriptionService->syncBranchSubscriptionFee($branch->fresh(), 1000);

    $invoice->refresh();

    // Verify invoice reflects new amount 1000
    expect((float) $invoice->total_amount)->toBe(1000.0)
        ->and((float) $invoice->due_amount)->toBe(1000.0)
        ->and($invoice->status)->toBe('unpaid');

    // Verify COA ledgers adjusted to 1000 without orphan duplicates
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 1000.0)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($initialReceivable + 1000.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe(1000.0)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(1000.0);
});

test('changing subscription fee on partially paid invoice adjusts remaining due and COA balances', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Fee Adjustment Partial Test',
        'subscription_fee' => 1500,
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 30,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
        'subscription_status' => 'active',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $superadminReceivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchPayable = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    $initialIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialReceivable = (float) $superadminReceivable->fresh()->current_balance;
    $initialSuperadminBkash = (float) $superadminBkash->fresh()->current_balance;
    $initialBranchBkash = (float) $branchBkash->fresh()->current_balance;

    $invoiceService = app(SubscriptionInvoiceService::class);
    $subscriptionService = app(BranchSubscriptionService::class);

    // 1. Accrue 1500
    $invoice = $invoiceService->generateInvoice($branch, '2026-09-01', '2026-10-01', 1500);

    // 2. Branch pays 600 partially
    $admin = User::factory()->create(['branch_id' => null]);
    $subscriptionService->renew($branch, [
        'duration_days' => 30,
        'amount' => 600,
        'payment_method' => 'bKash',
        'transaction_reference' => 'BKASH-PART-600',
        'paid_at' => '2026-09-05',
    ], $admin);

    $invoice->refresh();
    expect($invoice->status)->toBe('partial')
        ->and((float) $invoice->paid_amount)->toBe(600.0)
        ->and((float) $invoice->due_amount)->toBe(900.0)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(900.0)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($initialReceivable + 900.0);

    // 3. Admin updates subscription fee to 1000
    $branch->update(['subscription_fee' => 1000]);
    $subscriptionService->syncBranchSubscriptionFee($branch->fresh(), 1000);

    $invoice->refresh();

    // Total becomes 1000, paid remains 600, due becomes 400
    expect((float) $invoice->total_amount)->toBe(1000.0)
        ->and((float) $invoice->paid_amount)->toBe(600.0)
        ->and((float) $invoice->due_amount)->toBe(400.0)
        ->and($invoice->status)->toBe('partial');

    // Chart of accounts should reflect:
    // Income = 1000
    // Cash = +600
    // SuperAdmin Receivable = 1000 - 600 = 400
    // Branch Expense = 1000
    // Branch Payable = 1000 - 600 = 400
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 1000.0)
        ->and((float) $superadminReceivable->fresh()->current_balance)->toBe($initialReceivable + 400.0)
        ->and((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminBkash + 600.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe(1000.0)
        ->and((float) $branchPayable->fresh()->current_balance)->toBe(400.0)
        ->and((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 600.0);
});

