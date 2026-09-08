<?php

namespace App\Services;

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\User;

class BranchSubscriptionAccountingService
{
    /**
     * Accrue subscription payable liability & expense on the client branch panel,
     * and client subscription receivables & subscription income on the SuperAdmin panel
     * when a billing cycle is reached.
     *
     * @return array{branch_accrual: ?Transaction, superadmin_accrual: ?Transaction}
     */
    public function recordCycleAccrual(Branch $branch, string $cycleStartDate, string $cycleEndDate, float $fee): array
    {
        if (Branch::isMainBranch($branch->id) || $fee <= 0) {
            return ['branch_accrual' => null, 'superadmin_accrual' => null];
        }

        $branchName = $branch->name ?: "Branch #{$branch->id}";

        // 1. Client Branch Accrual: Debit Subscription Expense (Expense increases), Credit Subscription Payable (Liability increases)
        $branchDescription = "Subscription bill accrual: {$cycleStartDate} to {$cycleEndDate}";
        $existingBranchTx = Transaction::query()
            ->where('source_type', Branch::class)
            ->where('source_id', $branch->id)
            ->where('description', $branchDescription)
            ->first();

        $branchAccrualTx = $existingBranchTx;

        if ($branchAccrualTx === null) {
            $expenseAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);

            $branchAccrualTx = TransactionService::recordTransaction([
                'source_type' => Branch::class,
                'source_id' => $branch->id,
                'date' => $cycleStartDate,
                'amount' => $fee,
                'debit_account_id' => $expenseAccount->id,
                'credit_account_id' => $payableAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $branchDescription,
            ], validateBalance: false);
        }

        // 2. SuperAdmin Accrual: Debit Client Subscription Receivables (Asset increases), Credit Subscription Income (Income increases)
        $superadminDescription = "Subscription bill accrual for {$branchName}: {$cycleStartDate} to {$cycleEndDate}";
        $existingSuperadminTx = Transaction::query()
            ->where('source_type', Branch::class)
            ->where('source_id', $branch->id)
            ->where('description', $superadminDescription)
            ->first();

        $superadminAccrualTx = $existingSuperadminTx;

        if ($superadminAccrualTx === null) {
            $receivableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
            $incomeAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);

            $superadminAccrualTx = TransactionService::recordTransaction([
                'source_type' => Branch::class,
                'source_id' => $branch->id,
                'date' => $cycleStartDate,
                'amount' => $fee,
                'debit_account_id' => $receivableAccount->id,
                'credit_account_id' => $incomeAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $superadminDescription,
            ], validateBalance: false);
        }

        return [
            'branch_accrual' => $branchAccrualTx,
            'superadmin_accrual' => $superadminAccrualTx,
        ];
    }

    /**
     * Record accounting settlement when SuperAdmin confirms and approves a subscription payment:
     * 1. On Client Branch: Debit Subscription Payable (liability decreases), Credit Cash/Bank (asset decreases).
     * 2. On SuperAdmin: Debit Cash/Bank (asset increases), Credit Client Subscription Receivables (receivable asset decreases).
     *
     * @return array{branch_transaction: ?Transaction, superadmin_transaction: ?Transaction}
     */
    public function recordPaymentSettlement(BranchSubscriptionPayment $payment, ?User $actor = null): array
    {
        $amount = (float) $payment->amount;

        if ($amount <= 0) {
            return ['branch_transaction' => null, 'superadmin_transaction' => null];
        }

        $paymentDate = $payment->paid_at ? $payment->paid_at->format('Y-m-d') : now()->toDateString();
        $paymentAccountKey = $this->resolvePaymentAccountKey($payment->payment_method);
        $branch = $payment->branch ?? Branch::find($payment->branch_id);
        $branchName = $branch?->name ?? "Branch #{$payment->branch_id}";

        if ($branch && ! Branch::isMainBranch($payment->branch_id)) {
            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $payment->branch_id);
            $payableBalance = max(0.0, (float) $payableAccount->fresh()->current_balance);
            $unaccruedAmount = round($amount - $payableBalance, 2);

            // Ensure accrual is posted if payment exceeds previously accrued payable liability
            if ($unaccruedAmount > 0.005) {
                $startsAt = $payment->billing_period_starts_at ? $payment->billing_period_starts_at->format('Y-m-d') : $paymentDate;
                $endsAt = $payment->billing_period_ends_at ? $payment->billing_period_ends_at->format('Y-m-d') : $paymentDate;
                $this->recordCycleAccrual($branch, $startsAt, $endsAt, $unaccruedAmount);
            }
        }

        // 1. Client / Branch Side: Debit Subscription Payable (decreases liability), Credit Asset (decreases asset)
        $branchDescription = "Subscription payment settled for {$branchName} (Ref: {$payment->transaction_reference})";
        $existingBranchTx = Transaction::query()
            ->where('source_type', BranchSubscriptionPayment::class)
            ->where('source_id', $payment->id)
            ->where('description', $branchDescription)
            ->first();

        $branchTransaction = $existingBranchTx;

        if ($branchTransaction === null && ! Branch::isMainBranch($payment->branch_id) && $branch) {
            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $payment->branch_id);
            $branchPaymentAccount = ChartOfAccount::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $payment->branch_id)
                ->where('type', \App\Enums\AccountType::Asset)
                ->where(function ($q) use ($payment) {
                    $q->where('name', $payment->payment_method)
                        ->orWhere('code', $payment->payment_method);
                })
                ->first() ?? SystemAccountService::resolve($paymentAccountKey, $payment->branch_id);

            $branchTransaction = TransactionService::recordTransaction([
                'source_type' => BranchSubscriptionPayment::class,
                'source_id' => $payment->id,
                'performed_by_type' => $actor ? User::class : null,
                'performed_by_id' => $actor?->id,
                'date' => $paymentDate,
                'amount' => $amount,
                'debit_account_id' => $payableAccount->id,
                'credit_account_id' => $branchPaymentAccount->id,
                'debit_decrease' => true,
                'credit_decrease' => true,
                'description' => $branchDescription,
            ], validateBalance: false);
        }

        // 2. SuperAdmin Side (Global Panel: branch_id === null)
        // Debit: Selected SuperAdmin Cash/Bank Asset (increases cash asset)
        // Credit: Client Subscription Receivables (decreases client receivable asset)
        $superadminDescription = "Subscription payment received from {$branchName} (Ref: {$payment->transaction_reference})";
        $existingSuperadminTx = Transaction::query()
            ->where('source_type', BranchSubscriptionPayment::class)
            ->where('source_id', $payment->id)
            ->where('description', $superadminDescription)
            ->first();

        $superadminTransaction = $existingSuperadminTx;

        if ($superadminTransaction === null) {
            $superadminPaymentAccount = ChartOfAccount::query()
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->where('type', \App\Enums\AccountType::Asset)
                ->where(function ($q) use ($payment) {
                    $q->where('name', $payment->payment_method)
                        ->orWhere('code', $payment->payment_method);
                })
                ->first() ?? SystemAccountService::resolve($paymentAccountKey, null);

            $receivableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);

            $superadminTransaction = TransactionService::recordTransaction([
                'source_type' => BranchSubscriptionPayment::class,
                'source_id' => $payment->id,
                'performed_by_type' => $actor ? User::class : null,
                'performed_by_id' => $actor?->id,
                'date' => $paymentDate,
                'amount' => $amount,
                'debit_account_id' => $superadminPaymentAccount->id,
                'credit_account_id' => $receivableAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => true,
                'description' => $superadminDescription,
            ], validateBalance: false);
        }

        return [
            'branch_transaction' => $branchTransaction,
            'superadmin_transaction' => $superadminTransaction,
        ];
    }

    public function resolvePaymentAccountKey(?string $method): SystemAccountKey
    {
        $normalized = strtolower(trim((string) $method));

        if (str_contains($normalized, 'bkash')) {
            return SystemAccountKey::Bkash;
        }
        if (str_contains($normalized, 'nagad')) {
            return SystemAccountKey::Nagad;
        }
        if (str_contains($normalized, 'ssl') || str_contains($normalized, 'card')) {
            return SystemAccountKey::SslCommerz;
        }
        if (str_contains($normalized, 'cash')) {
            return SystemAccountKey::CashInHand;
        }

        return SystemAccountKey::CashAndBank;
    }
}
