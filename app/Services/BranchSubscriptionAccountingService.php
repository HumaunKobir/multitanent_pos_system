<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\User;

class BranchSubscriptionAccountingService
{
    public function __construct(private TenantProvisioner $tenants) {}

    /**
     * Raise Subscription Payable (client) up to the outstanding due amount, and post the
     * same shortfall on SuperAdmin receivable/income. Never reverses excess.
     *
     * @return array{branch_accrual: ?Transaction, superadmin_accrual: ?Transaction}
     */
    public function syncOutstandingDue(Branch $branch, float $targetAmount, string $asOfDate): array
    {
        // Without approval, do not post as expense and income
        return ['branch_accrual' => null, 'superadmin_accrual' => null];
    }

    public function recordCumulativeLiabilityShortfall(Branch $branch, string $asOfDate, float $expectedCumulativeDue): array
    {
        // Without approval, do not post as expense and income
        return ['branch_accrual' => null, 'superadmin_accrual' => null];
    }

    /**
     * Accrue subscription payable liability & expense on the client branch panel,
     * and client subscription receivables & subscription income on the SuperAdmin panel
     * when a billing cycle is reached.
     *
     * @return array{branch_accrual: ?Transaction, superadmin_accrual: ?Transaction}
     */
    public function recordCycleAccrual(Branch $branch, string $cycleStartDate, string $cycleEndDate, float $fee): array
    {
        // Without approval, do not post as expense and income
        return ['branch_accrual' => null, 'superadmin_accrual' => null];
    }

    /**
     * Record accounting settlement when SuperAdmin confirms and approves a subscription payment:
     * 1. On Client Branch: Debit Subscription Expense (Expense increases), Credit Cash/Bank (Asset decreases).
     * 2. On SuperAdmin: Debit Cash/Bank (Asset increases), Credit Subscription Income (Income increases).
     *
     * @return array{branch_transaction: ?Transaction, superadmin_transaction: ?Transaction}
     */
    public function recordPaymentSettlement(
        BranchSubscriptionPayment $payment,
        ?User $actor = null,
        ?float $customAmount = null,
    ): array {
        $amount = $customAmount !== null ? round((float) $customAmount, 2) : (float) $payment->amount;

        if ($amount <= 0.005) {
            return ['branch_transaction' => null, 'superadmin_transaction' => null];
        }

        $paymentDate = $payment->paid_at ? $payment->paid_at->format('Y-m-d') : now()->toDateString();
        $paymentAccountKey = $this->resolvePaymentAccountKey($payment->payment_method);
        $branch = $payment->branch ?? Branch::query()->find($payment->branch_id);
        $branchName = $branch?->name ?? "Branch #{$payment->branch_id}";

        $branchDescription = "Subscription expense for {$branchName} (Ref: {$payment->transaction_reference})";
        $superadminDescription = "Subscription income from {$branchName} (Ref: {$payment->transaction_reference})";

        $branchTransaction = null;

        // 1. Client Branch: Debit Subscription Expense (Expense increases), Credit Payment Account (Asset decreases)
        if ($branch && ! Branch::isMainBranch($payment->branch_id)) {
            $branchTransaction = $this->onClientBranch($branch, function () use (
                $payment,
                $actor,
                $amount,
                $paymentDate,
                $paymentAccountKey,
                $branchDescription,
            ): ?Transaction {
                $existingBranchTx = Transaction::query()
                    ->where('source_type', BranchSubscriptionPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('description', $branchDescription)
                    ->first();

                if ($existingBranchTx !== null) {
                    return $existingBranchTx;
                }

                $expenseAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $payment->branch_id);
                $branchPaymentAccount = $this->resolveBranchPaymentAccount($payment, $paymentAccountKey);

                return TransactionService::recordTransaction([
                    'source_type' => BranchSubscriptionPayment::class,
                    'source_id' => $payment->id,
                    'performed_by_type' => $actor ? User::class : null,
                    'performed_by_id' => $actor?->id,
                    'date' => $paymentDate,
                    'amount' => $amount,
                    'debit_account_id' => $expenseAccount->id,
                    'credit_account_id' => $branchPaymentAccount->id,
                    'debit_decrease' => false,
                    'credit_decrease' => true,
                    'description' => $branchDescription,
                ], validateBalance: false);
            });
        }

        // 2. SuperAdmin: Debit Payment Account (Asset increases), Credit Subscription Income (Revenue increases)
        $superadminTransaction = $this->onMainPanel(function () use (
            $payment,
            $actor,
            $amount,
            $paymentDate,
            $paymentAccountKey,
            $superadminDescription,
        ): ?Transaction {
            $existingSuperadminTx = Transaction::query()
                ->where('source_type', BranchSubscriptionPayment::class)
                ->where('source_id', $payment->id)
                ->where('description', $superadminDescription)
                ->first();

            if ($existingSuperadminTx !== null) {
                return $existingSuperadminTx;
            }

            $superadminPaymentAccount = ($payment->payment_account_id ? ChartOfAccount::query()->find($payment->payment_account_id) : null)
                ?? ChartOfAccount::query()
                    ->whereNull('source_type')
                    ->whereNull('source_id')
                    ->where('type', AccountType::Asset)
                    ->where(function ($q) use ($payment): void {
                        $q->where('name', $payment->payment_method)
                            ->orWhere('code', $payment->payment_method);
                    })
                    ->first() ?? SystemAccountService::resolve($paymentAccountKey, null);

            $incomeAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);

            return TransactionService::recordTransaction([
                'source_type' => BranchSubscriptionPayment::class,
                'source_id' => $payment->id,
                'performed_by_type' => $actor ? User::class : null,
                'performed_by_id' => $actor?->id,
                'date' => $paymentDate,
                'amount' => $amount,
                'debit_account_id' => $superadminPaymentAccount->id,
                'credit_account_id' => $incomeAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $superadminDescription,
            ], validateBalance: false);
        });

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

        if (str_contains($normalized, 'rocket') || str_contains($normalized, 'bank')) {
            return SystemAccountKey::BankAccount;
        }

        return SystemAccountKey::CashAndBank;
    }

    private function resolveBranchPaymentAccount(
        BranchSubscriptionPayment $payment,
        SystemAccountKey $paymentAccountKey,
    ): ChartOfAccount {
        if ($payment->payment_account_id) {
            $selected = BranchPaymentAccountService::find(
                (int) $payment->payment_account_id,
                $payment->branch_id,
            );

            if ($selected !== null) {
                return $selected;
            }

            $byId = ChartOfAccount::query()->whereKey($payment->payment_account_id)->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        return ChartOfAccount::query()
            ->where('source_type', Branch::class)
            ->where('source_id', $payment->branch_id)
            ->where('type', AccountType::Asset)
            ->where(function ($q) use ($payment): void {
                $q->where('name', $payment->payment_method)
                    ->orWhere('code', $payment->payment_method);
            })
            ->first() ?? SystemAccountService::resolve($paymentAccountKey, $payment->branch_id);
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
