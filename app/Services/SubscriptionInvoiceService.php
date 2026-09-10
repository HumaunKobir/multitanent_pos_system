<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\ChartOfAccount;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPaymentAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Support\BusinessSettings;
use Carbon\Carbon;

class SubscriptionInvoiceService
{
    public function __construct(
        private BranchSubscriptionAccountingService $accounting,
        private TenantProvisioner $tenants,
    ) {}

    /**
     * Generate a new subscription invoice for the given branch and billing period,
     * and post the double-entry accounting entries:
     * - SuperAdmin: Debit Subscription Receivable (Asset), Credit Subscription Income (Revenue)
     * - Branch: Debit Subscription Expense (Expense), Credit Subscription Payable (Liability)
     */
    public function generateInvoice(Branch $branch, string $startDate, string $endDate, ?float $amount = null, ?string $notes = null): SubscriptionInvoice
    {
        $fee = $amount !== null ? $amount : (float) ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500));
        $startDateStr = Carbon::parse($startDate)->toDateString();
        $endDateStr = Carbon::parse($endDate)->toDateString();

        // Prevent duplicate invoice for the same branch and exact period
        $existing = SubscriptionInvoice::where('branch_id', $branch->id)
            ->where('billing_period_starts_at', $startDateStr)
            ->where('billing_period_ends_at', $endDateStr)
            ->first();

        if ($existing) {
            $currentSubtotal = (float) $existing->subtotal;
            if (abs($currentSubtotal - $fee) > 0.005) {
                $paid = (float) $existing->paid_amount;
                $discount = (float) $existing->discount;
                $newTotal = max(0.0, round($fee - $discount, 2));
                $newDue = max(0.0, round($newTotal - $paid, 2));
                $newStatus = $newDue <= 0.005 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

                $existing->update([
                    'subtotal' => $fee,
                    'total_amount' => $newTotal,
                    'due_amount' => $newDue,
                    'status' => $newStatus,
                ]);
            }

            // Catch up / sync GL for invoice
            $this->postInvoiceAccounting($existing->fresh());

            return $existing->fresh(['paymentAllocations']);
        }

        $invoice = SubscriptionInvoice::create([
            'branch_id' => $branch->id,
            'invoice_number' => SubscriptionInvoice::generateInvoiceNumber(),
            'billing_period_starts_at' => $startDateStr,
            'billing_period_ends_at' => $endDateStr,
            'due_date' => $startDateStr,
            'subtotal' => $fee,
            'discount' => 0,
            'total_amount' => $fee,
            'paid_amount' => 0,
            'due_amount' => $fee,
            'status' => 'unpaid',
            'notes' => $notes,
        ]);

        $this->postInvoiceAccounting($invoice);

        // Auto-apply available advance balance if any (after accrual so prepaid clears payable/receivable)
        $this->applyAvailableAdvanceToInvoice($invoice->fresh());

        return $invoice->fresh(['paymentAllocations']);
    }

    /**
     * Synchronize open / unpaid / partial billing cycle invoices for a branch to match the updated subscription fee.
     * Also updates journal entries / GL accruals on both SuperAdmin and Client Branch charts.
     */
    public function syncBranchInvoicesForFee(Branch $branch, ?float $newFee = null): void
    {
        if (Branch::isMainBranch($branch->id) || ($branch->subscription_status ?: 'active') === 'lifetime') {
            return;
        }

        $fee = $newFee !== null ? $newFee : (float) ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500));

        $openInvoices = SubscriptionInvoice::where('branch_id', $branch->id)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->get();

        foreach ($openInvoices as $invoice) {
            $paid = (float) $invoice->paid_amount;
            $discount = (float) $invoice->discount;
            $newTotal = max(0.0, round($fee - $discount, 2));
            $newDue = max(0.0, round($newTotal - $paid, 2));
            $newStatus = $newDue <= 0.005 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

            $invoice->update([
                'subtotal' => $fee,
                'total_amount' => $newTotal,
                'due_amount' => $newDue,
                'status' => $newStatus,
            ]);

            $this->postInvoiceAccounting($invoice->fresh());
            $this->applyAvailableAdvanceToInvoice($invoice->fresh());
        }
    }

    /**
     * Post full-accrual journal entries for a generated invoice.
     * Client: Dr Subscription Expense, Cr Subscription Payable.
     * SuperAdmin: Dr Subscription Receivable, Cr Subscription Income.
     */
    public function postInvoiceAccounting(SubscriptionInvoice $invoice): void
    {
        $branch = $invoice->branch ?? Branch::query()->find($invoice->branch_id);

        if ($branch === null || Branch::isMainBranch($branch->id)) {
            return;
        }

        $startDate = $invoice->billing_period_starts_at?->format('Y-m-d')
            ?? $invoice->due_date?->format('Y-m-d')
            ?? now()->toDateString();
        $endDate = $invoice->billing_period_ends_at?->format('Y-m-d') ?? $startDate;
        $fee = round((float) $invoice->total_amount, 2);

        if ($fee <= 0.005) {
            return;
        }

        $this->accounting->recordCycleAccrual($branch, $startDate, $endDate, $fee);
    }

    /**
     * Generate any missing billing-cycle invoices from subscription start through today.
     *
     * @return list<SubscriptionInvoice>
     */
    public function generateMissingInvoicesThroughToday(Branch $branch, ?BranchSubscriptionService $subscriptions = null): array
    {
        if (Branch::isMainBranch($branch->id) || ($branch->subscription_status ?: 'active') === 'lifetime') {
            return [];
        }

        $subscriptions ??= app(BranchSubscriptionService::class);
        $cycleDays = $subscriptions->resolveCycleDays($branch);

        if ($cycleDays === null || $cycleDays <= 0) {
            return [];
        }

        $fee = (float) ($branch->subscription_fee ?? BusinessSettings::getFloat('subscription_default_fee', 1500));
        $startDate = $branch->subscription_starts_at
            ? Carbon::parse($branch->subscription_starts_at)->startOfDay()
            : Carbon::today();
        $today = Carbon::today();
        $created = [];

        $cursorStart = $startDate->copy();
        while ($cursorStart->lte($today)) {
            $cursorEnd = $cursorStart->copy()->addDays($cycleDays);
            $periodStart = $cursorStart->toDateString();
            $periodEnd = $cursorEnd->toDateString();

            $alreadyExisted = SubscriptionInvoice::where('branch_id', $branch->id)
                ->where('billing_period_starts_at', $periodStart)
                ->where('billing_period_ends_at', $periodEnd)
                ->exists();

            $invoice = $this->generateInvoice($branch, $periodStart, $periodEnd, $fee);

            if (! $alreadyExisted) {
                $created[] = $invoice;
            }

            $cursorStart = $cursorEnd->copy();
        }

        return $created;
    }

    /**
     * Allocate an approved payment to unpaid / partial invoices.
     * Supports partial payments (e.g. ৳600 on ৳1000 invoice leaves ৳400 due).
     */
    public function allocatePayment(BranchSubscriptionPayment $payment, ?User $actor = null): array
    {
        $branch = $payment->branch ?? Branch::find($payment->branch_id);
        $paymentAmount = (float) $payment->amount;

        if (! $branch || $paymentAmount <= 0) {
            return [];
        }

        $unpaidInvoices = SubscriptionInvoice::where('branch_id', $branch->id)
            ->whereIn('status', ['unpaid', 'partial', 'overdue'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $remainingToAllocate = $paymentAmount;
        $allocations = [];

        foreach ($unpaidInvoices as $invoice) {
            if ($remainingToAllocate <= 0.005) {
                break;
            }

            $dueOnInvoice = (float) $invoice->due_amount;
            $allocateAmount = min($remainingToAllocate, $dueOnInvoice);

            $allocation = SubscriptionPaymentAllocation::create([
                'branch_subscription_payment_id' => $payment->id,
                'subscription_invoice_id' => $invoice->id,
                'amount' => $allocateAmount,
            ]);

            $newPaid = round((float) $invoice->paid_amount + $allocateAmount, 2);
            $newDue = round((float) $invoice->total_amount - $newPaid, 2);
            $newStatus = $newDue <= 0.005 ? 'paid' : 'partial';

            $invoice->update([
                'paid_amount' => $newPaid,
                'due_amount' => max(0.0, $newDue),
                'status' => $newStatus,
            ]);

            $remainingToAllocate = round($remainingToAllocate - $allocateAmount, 2);
            $allocations[] = $allocation;
        }

        $allocatedTotal = round($paymentAmount - $remainingToAllocate, 2);

        // Settle both charts for the invoice-applied portion (idempotent if already posted):
        // Client: Dr Payable / Cr Cash — Admin: Dr Cash / Cr Receivable.
        if ($allocatedTotal > 0.005) {
            $this->accounting->recordPaymentSettlement($payment, $actor, $allocatedTotal);
        }

        // If excess payment remains after paying all due invoices, credit it to Advance
        if ($remainingToAllocate > 0.005) {
            $this->recordAdvancePayment($branch, $payment, $remainingToAllocate, $actor);
        }

        return $allocations;
    }

    /**
     * Record an advance payment from a client branch.
     * - SuperAdmin: Debit Bank/Cash (Asset), Credit Advance from Client (Liability)
     * - Client Branch: Debit Prepaid Subscription (Asset), Credit Bank/Cash (Asset)
     */
    public function recordAdvancePayment(Branch $branch, BranchSubscriptionPayment $payment, float $advanceAmount, ?User $actor = null): void
    {
        $paymentDate = $payment->paid_at ? $payment->paid_at->format('Y-m-d') : now()->toDateString();
        $paymentAccountKey = $this->accounting->resolvePaymentAccountKey($payment->payment_method);
        $branchName = $branch->name ?: "Branch #{$branch->id}";
        $description = "Advance payment from {$branchName} (Ref: {$payment->transaction_reference})";

        // 1. SuperAdmin: Debit Channel Account, Credit Advance from Client (Liability)
        $this->onMainPanel(function () use ($payment, $actor, $advanceAmount, $paymentDate, $paymentAccountKey, $description): void {
            $superadminPaymentAccount = ChartOfAccount::query()
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->where('type', AccountType::Asset)
                ->where(function ($q) use ($payment): void {
                    $q->where('name', $payment->payment_method)
                        ->orWhere('code', $payment->payment_method);
                })
                ->first() ?? SystemAccountService::resolve($paymentAccountKey, null);

            $advanceAccount = SystemAccountService::resolve(SystemAccountKey::AdvanceFromClient, null);

            TransactionService::recordTransaction([
                'source_type' => BranchSubscriptionPayment::class,
                'source_id' => $payment->id,
                'performed_by_type' => $actor ? User::class : null,
                'performed_by_id' => $actor?->id,
                'date' => $paymentDate,
                'amount' => $advanceAmount,
                'debit_account_id' => $superadminPaymentAccount->id,
                'credit_account_id' => $advanceAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $description,
            ], validateBalance: false);
        });

        // 2. Client Branch: Debit Prepaid Subscription (Asset), Credit Channel Account
        $this->onClientBranch($branch, function () use ($branch, $payment, $advanceAmount, $paymentDate, $paymentAccountKey): void {
            $prepaidAccount = SystemAccountService::resolve(SystemAccountKey::PrepaidSubscription, $branch->id);
            $branchPaymentAccount = ChartOfAccount::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branch->id)
                ->where('type', AccountType::Asset)
                ->where(function ($q) use ($payment): void {
                    $q->where('name', $payment->payment_method)
                        ->orWhere('code', $payment->payment_method);
                })
                ->first() ?? SystemAccountService::resolve($paymentAccountKey, $branch->id);

            TransactionService::recordTransaction([
                'source_type' => BranchSubscriptionPayment::class,
                'source_id' => $payment->id,
                'date' => $paymentDate,
                'amount' => $advanceAmount,
                'debit_account_id' => $prepaidAccount->id,
                'credit_account_id' => $branchPaymentAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => true,
                'description' => "Advance subscription payment (Ref: {$payment->transaction_reference})",
            ], validateBalance: false);
        });
    }

    /**
     * Get the available advance balance for a client branch from its Prepaid Subscription asset or SuperAdmin Advance liability.
     */
    public function getAdvanceBalance(Branch $branch): float
    {
        return $this->onClientBranch($branch, function () use ($branch): float {
            $prepaidAccount = SystemAccountService::resolve(SystemAccountKey::PrepaidSubscription, $branch->id);

            return max(0.0, (float) $prepaidAccount->fresh()->current_balance);
        });
    }

    /**
     * Auto-apply available advance/prepaid against an open invoice after accrual.
     * Clears payable/receivable — does not re-recognize expense or income.
     */
    public function applyAvailableAdvanceToInvoice(SubscriptionInvoice $invoice): void
    {
        $branch = $invoice->branch ?? Branch::find($invoice->branch_id);
        if (! $branch || $invoice->due_amount <= 0.005) {
            return;
        }

        $advanceBalance = $this->getAdvanceBalance($branch);

        if ($advanceBalance <= 0.005) {
            return;
        }

        $applyAmount = min((float) $invoice->due_amount, $advanceBalance);
        $invoiceDate = $invoice->due_date?->format('Y-m-d') ?? now()->toDateString();

        // SuperAdmin: Dr Advance from Client, Cr Subscription Receivable
        $this->onMainPanel(function () use ($invoice, $applyAmount, $invoiceDate): void {
            $description = "Advance applied to Invoice {$invoice->invoice_number}";

            $existing = Transaction::query()
                ->where('source_type', SubscriptionInvoice::class)
                ->where('source_id', $invoice->id)
                ->where('description', $description)
                ->first();

            if ($existing !== null) {
                return;
            }

            $advanceAccount = SystemAccountService::resolve(SystemAccountKey::AdvanceFromClient, null);
            $receivableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);

            TransactionService::recordTransaction([
                'source_type' => SubscriptionInvoice::class,
                'source_id' => $invoice->id,
                'date' => $invoiceDate,
                'amount' => $applyAmount,
                'debit_account_id' => $advanceAccount->id,
                'credit_account_id' => $receivableAccount->id,
                'debit_decrease' => true,
                'credit_decrease' => true,
                'description' => $description,
            ], validateBalance: false);
        });

        // Client: Dr Subscription Payable, Cr Prepaid Subscription
        $this->onClientBranch($branch, function () use ($invoice, $branch, $applyAmount, $invoiceDate): void {
            $description = "Prepaid advance adjusted for Invoice {$invoice->invoice_number}";

            $existing = Transaction::query()
                ->where('source_type', SubscriptionInvoice::class)
                ->where('source_id', $invoice->id)
                ->where('description', $description)
                ->first();

            if ($existing !== null) {
                return;
            }

            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
            $prepaidAccount = SystemAccountService::resolve(SystemAccountKey::PrepaidSubscription, $branch->id);

            TransactionService::recordTransaction([
                'source_type' => SubscriptionInvoice::class,
                'source_id' => $invoice->id,
                'date' => $invoiceDate,
                'amount' => $applyAmount,
                'debit_account_id' => $payableAccount->id,
                'credit_account_id' => $prepaidAccount->id,
                'debit_decrease' => true,
                'credit_decrease' => true,
                'description' => $description,
            ], validateBalance: false);
        });

        $newPaid = round((float) $invoice->paid_amount + $applyAmount, 2);
        $newDue = round((float) $invoice->total_amount - $newPaid, 2);

        $invoice->update([
            'paid_amount' => $newPaid,
            'due_amount' => max(0.0, $newDue),
            'status' => $newDue <= 0.005 ? 'paid' : 'partial',
        ]);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function onClientBranch(Branch $branch, callable $callback): mixed
    {
        return $this->tenants->usingBranch($branch, $callback);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function onMainPanel(callable $callback): mixed
    {
        $main = Branch::query()->find(Branch::resolveMainBranchId());

        if ($main === null) {
            return $callback();
        }

        return $this->tenants->usingBranch($main, $callback);
    }
}
