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
     * Accrue subscription payable liability and subscription expense on the client branch panel
     * when a billing cycle is reached.
     */
    public function recordCycleAccrual(Branch $branch, string $cycleStartDate, string $cycleEndDate, float $fee): ?Transaction
    {
        if (Branch::isMainBranch($branch->id) || $fee <= 0) {
            return null;
        }

        $description = "Subscription bill accrual: {$cycleStartDate} to {$cycleEndDate}";

        $alreadyAccrued = Transaction::query()
            ->where('source_type', Branch::class)
            ->where('source_id', $branch->id)
            ->where('description', $description)
            ->first();

        if ($alreadyAccrued !== null) {
            return $alreadyAccrued;
        }

        $expenseAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
        $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);

        return TransactionService::recordTransaction([
            'source_type' => Branch::class,
            'source_id' => $branch->id,
            'date' => $cycleStartDate,
            'amount' => $fee,
            'debit_account_id' => $expenseAccount->id,
            'credit_account_id' => $payableAccount->id,
            'debit_decrease' => false,
            'credit_decrease' => false,
            'description' => $description,
        ], validateBalance: false);
    }

    /**
     * Record accounting settlement when SuperAdmin confirms and approves a subscription payment:
     * 1. On Client Branch: Debit Subscription Payable (liability decreases), Credit Cash/Bank (asset decreases).
     * 2. On SuperAdmin: Debit Cash/Bank (asset increases), Credit Subscription Income (income increases).
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

        // 1. Client / Branch Side
        $branchDescription = "Subscription payment settled for {$branchName} (Ref: {$payment->transaction_reference})";
        $existingBranchTx = Transaction::query()
            ->where('source_type', BranchSubscriptionPayment::class)
            ->where('source_id', $payment->id)
            ->where('description', $branchDescription)
            ->first();

        $branchTransaction = $existingBranchTx;

        if ($branchTransaction === null && ! Branch::isMainBranch($payment->branch_id) && $branch) {
            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $payment->branch_id);
            $payableBalance = max(0.0, (float) $payableAccount->fresh()->current_balance);
            $unaccruedAmount = round($amount - $payableBalance, 2);

            // 1a. If this payment exceeds existing accrued payable liability, accrue the difference as Subscription Expense
            if ($unaccruedAmount > 0.005) {
                $startsAt = $payment->billing_period_starts_at ? $payment->billing_period_starts_at->format('Y-m-d') : $paymentDate;
                $endsAt = $payment->billing_period_ends_at ? $payment->billing_period_ends_at->format('Y-m-d') : $paymentDate;
                $this->recordCycleAccrual($branch, $startsAt, $endsAt, $unaccruedAmount);
            }

            // 1b. Settle payment on branch: Debit Subscription Payable (decreases liability), Credit Asset (decreases asset)
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

            $subscriptionIncome = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);

            $superadminTransaction = TransactionService::recordTransaction([
                'source_type' => BranchSubscriptionPayment::class,
                'source_id' => $payment->id,
                'performed_by_type' => $actor ? User::class : null,
                'performed_by_id' => $actor?->id,
                'date' => $paymentDate,
                'amount' => $amount,
                'debit_account_id' => $superadminPaymentAccount->id,
                'credit_account_id' => $subscriptionIncome->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
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
