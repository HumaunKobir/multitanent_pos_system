<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSecurityDeposit;
use App\Models\BranchSubscriptionPayment;
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

test('generating subscription invoice creates document without posting expense or income prior to approval', function () {
    SystemAccountService::seed(null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);

    $branch = Branch::factory()->create([
        'name' => 'Branch Invoice Document Only',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $initialIncome = (float) $superadminIncome->fresh()->current_balance;
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $initialExpense = (float) $branchExpense->fresh()->current_balance;

    $invoiceService = app(SubscriptionInvoiceService::class);
    // Generate next cycle invoice
    $invoice = $invoiceService->generateInvoice($branch, '2026-10-01', '2026-11-01', 1000);

    expect($invoice->status)->toBe('unpaid')
        ->and((float) $invoice->total_amount)->toBe(1000.0)
        ->and((float) $invoice->paid_amount)->toBe(0.0)
        ->and((float) $invoice->due_amount)->toBe(1000.0)
        ->and($invoice->invoice_number)->toStartWith('INV-');

    // SuperAdmin GL: No income posted without approval
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome);

    // Client Branch GL: No expense posted without approval
    expect((float) $branchExpense->fresh()->current_balance)->toBe($initialExpense);
});

test('client payment submission remains pending without GL posting until superadmin approves', function () {
    SystemAccountService::seed(null);

    $branch = Branch::factory()->create([
        'name' => 'Branch Pending Review',
        'subscription_fee' => 1500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    $initialSuperadminIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialSuperadminBkash = (float) $superadminBkash->fresh()->current_balance;
    $initialBranchExpense = (float) $branchExpense->fresh()->current_balance;
    $initialBranchBkash = (float) $branchBkash->fresh()->current_balance;

    $subscriptionService = app(BranchSubscriptionService::class);
    $clientUser = User::factory()->create(['branch_id' => $branch->id]);

    // 1. Client submits payment
    $pendingPayment = $subscriptionService->submitPayment($branch, [
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'transaction_reference' => 'PENDING-BKASH-7788',
        'paid_at' => '2026-09-09',
        'notes' => 'Monthly bill submitted',
    ], $clientUser);

    expect($pendingPayment->status)->toBe('pending');

    // Without approval: No GL posting on SuperAdmin or Client
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialSuperadminIncome);
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminBkash);
    expect((float) $branchExpense->fresh()->current_balance)->toBe($initialBranchExpense);
    expect((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash);

    // 2. SuperAdmin approves payment
    $admin = User::factory()->create(['branch_id' => null]);
    $approvedPayment = $subscriptionService->renew($branch, [
        'pending_payment_id' => $pendingPayment->id,
        'duration_days' => 30,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'transaction_reference' => 'PENDING-BKASH-7788',
        'paid_at' => '2026-09-09',
    ], $admin);

    expect($approvedPayment->status)->toBe('approved');

    // Upon approval: SuperAdmin posts Income and Cash, Client posts Expense and Cash
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialSuperadminIncome + 1500.0);
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialSuperadminBkash + 1500.0);
    expect((float) $branchExpense->fresh()->current_balance)->toBe($initialBranchExpense + 1500.0);
    expect((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 1500.0);
});

test('partial payment allocation marks invoice partial and recognizes income on superadmin and expense on client', function () {
    SystemAccountService::seed(null);

    $branch = Branch::factory()->create([
        'name' => 'Branch Partial Payment',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed($branch->id);

    $superadminBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
    $branchBkash = SystemAccountService::resolve(SystemAccountKey::Bkash, $branch->id);

    $initialBkash = (float) $superadminBkash->fresh()->current_balance;
    $initialIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialExpense = (float) $branchExpense->fresh()->current_balance;
    $initialBranchBkash = (float) $branchBkash->fresh()->current_balance;

    $invoiceService = app(SubscriptionInvoiceService::class);
    $subscriptionService = app(BranchSubscriptionService::class);

    // Generate next cycle invoice (no GL entries yet)
    $invoice = $invoiceService->generateInvoice($branch, '2026-10-01', '2026-11-01', 1000);

    // Client pays partial amount: ৳600, approved by admin
    $admin = User::factory()->create(['branch_id' => null]);
    $payment1 = $subscriptionService->renew($branch, [
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
        ->and((float) $invoice->due_amount)->toBe(400.0);

    // SuperAdmin GL: bKash +600, Income +600
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialBkash + 600.0);
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 600.0);

    // Client Branch GL: Expense +600, bKash -600
    expect((float) $branchExpense->fresh()->current_balance)->toBe($initialExpense + 600.0);
    expect((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 600.0);

    // Client pays second installment: ৳400, approved by admin
    $payment2 = $subscriptionService->renew($branch, [
        'duration_days' => 30,
        'amount' => 400,
        'payment_method' => 'bKash',
        'transaction_reference' => 'BKASH-PART-400',
        'paid_at' => '2026-09-09',
        'notes' => 'Final installment ৳400',
    ], $admin);

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')
        ->and((float) $invoice->paid_amount)->toBe(1000.0)
        ->and((float) $invoice->due_amount)->toBe(0.0);

    // SuperAdmin GL: Total bKash +1000, Total Income +1000
    expect((float) $superadminBkash->fresh()->current_balance)->toBe($initialBkash + 1000.0);
    expect((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 1000.0);

    // Client Branch GL: Total Expense +1000, Total bKash -1000
    expect((float) $branchExpense->fresh()->current_balance)->toBe($initialExpense + 1000.0);
    expect((float) $branchBkash->fresh()->current_balance)->toBe($initialBranchBkash - 1000.0);
});

test('excess payment is recorded as advance liability and auto applies to next invoice recognizing income and expense', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Advance Flow',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);

    SystemAccountService::seed(null);
    SystemAccountService::seed($branch->id);

    $superadminAdvance = SystemAccountService::resolve(SystemAccountKey::AdvanceFromClient, null);
    $superadminIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);
    $branchPrepaid = SystemAccountService::resolve(SystemAccountKey::PrepaidSubscription, $branch->id);
    $branchExpense = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);

    $initialAdvance = (float) $superadminAdvance->fresh()->current_balance;
    $initialIncome = (float) $superadminIncome->fresh()->current_balance;
    $initialPrepaid = (float) $branchPrepaid->fresh()->current_balance;
    $initialExpense = (float) $branchExpense->fresh()->current_balance;

    $invoiceService = app(SubscriptionInvoiceService::class);
    $subscriptionService = app(BranchSubscriptionService::class);

    // Generate current invoice for ৳1,000 (document only)
    $invoice1 = $invoiceService->generateInvoice($branch, '2026-09-01', '2026-10-01', 1000);

    // Client pays ৳3,000 (৳1,000 pays current invoice + ৳2,000 goes to advance liability)
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
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 1000.0)
        ->and((float) $branchPrepaid->fresh()->current_balance)->toBe($initialPrepaid + 2000.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($initialExpense + 1000.0);

    // When next month invoice is generated, advance auto-applies and recognizes revenue & expense!
    $invoice2 = $invoiceService->generateInvoice($branch, '2026-10-01', '2026-11-01', 1000);

    $invoice2->refresh();
    expect($invoice2->status)->toBe('paid')
        ->and((float) $invoice2->paid_amount)->toBe(1000.0)
        ->and((float) $invoice2->due_amount)->toBe(0.0)
        ->and((float) $superadminAdvance->fresh()->current_balance)->toBe($initialAdvance + 1000.0)
        ->and((float) $superadminIncome->fresh()->current_balance)->toBe($initialIncome + 2000.0)
        ->and((float) $branchPrepaid->fresh()->current_balance)->toBe($initialPrepaid + 1000.0)
        ->and((float) $branchExpense->fresh()->current_balance)->toBe($initialExpense + 2000.0);
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
