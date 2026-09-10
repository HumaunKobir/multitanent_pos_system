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
        $targetAmount = round(max(0.0, $targetAmount), 2);

        if (Branch::isMainBranch($branch->id) || $targetAmount <= 0) {
            return ['branch_accrual' => null, 'superadmin_accrual' => null];
        }

        $branchName = $branch->name ?: "Branch #{$branch->id}";

        $shortfall = $this->onClientBranch($branch, function () use ($branch, $targetAmount): float {
            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);
            $currentBalance = max(0.0, (float) $payableAccount->fresh()->current_balance);

            return round($targetAmount - $currentBalance, 2);
        });

        if ($shortfall <= 0.005) {
            return ['branch_accrual' => null, 'superadmin_accrual' => null];
        }

        $branchDescription = sprintf(
            'Subscription outstanding due sync as of %s (+%s)',
            $asOfDate,
            number_format($shortfall, 2, '.', ''),
        );
        $superadminDescription = sprintf(
            'Subscription outstanding due sync for %s as of %s (+%s)',
            $branchName,
            $asOfDate,
            number_format($shortfall, 2, '.', ''),
        );

        $branchAccrualTx = $this->onClientBranch($branch, function () use ($branch, $asOfDate, $shortfall, $branchDescription): ?Transaction {
            $existingBranchTx = Transaction::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branch->id)
                ->where('description', $branchDescription)
                ->first();

            if ($existingBranchTx !== null) {
                return $existingBranchTx;
            }

            $expenseAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionExpense, $branch->id);
            $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $branch->id);

            return TransactionService::recordTransaction([
                'source_type' => Branch::class,
                'source_id' => $branch->id,
                'date' => $asOfDate,
                'amount' => $shortfall,
                'debit_account_id' => $expenseAccount->id,
                'credit_account_id' => $payableAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $branchDescription,
            ], validateBalance: false);
        });

        $superadminAccrualTx = $this->onMainPanel(function () use ($branch, $asOfDate, $shortfall, $superadminDescription): ?Transaction {
            $existingSuperadminTx = Transaction::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branch->id)
                ->where('description', $superadminDescription)
                ->first();

            if ($existingSuperadminTx !== null) {
                return $existingSuperadminTx;
            }

            $receivableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
            $incomeAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);

            return TransactionService::recordTransaction([
                'source_type' => Branch::class,
                'source_id' => $branch->id,
                'date' => $asOfDate,
                'amount' => $shortfall,
                'debit_account_id' => $receivableAccount->id,
                'credit_account_id' => $incomeAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $superadminDescription,
            ], validateBalance: false);
        });

        return [
            'branch_accrual' => $branchAccrualTx,
            'superadmin_accrual' => $superadminAccrualTx,
        ];
    }

    public function recordCumulativeLiabilityShortfall(Branch $branch, string $asOfDate, float $expectedCumulativeDue): array
    {
        return $this->syncOutstandingDue($branch, $expectedCumulativeDue, $asOfDate);
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
        if (Branch::isMainBranch($branch->id) || $fee <= 0) {
            return ['branch_accrual' => null, 'superadmin_accrual' => null];
        }

        $branchName = $branch->name ?: "Branch #{$branch->id}";
        $branchDescription = "Subscription bill accrual: {$cycleStartDate} to {$cycleEndDate}";
        $superadminDescription = "Subscription bill accrual for {$branchName}: {$cycleStartDate} to {$cycleEndDate}";

        $branchAccrualTx = $this->onClientBranch($branch, function () use ($branch, $cycleStartDate, $fee, $branchDescription): ?Transaction {
            $existingBranchTx = Transaction::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branch->id)
                ->where('description', $branchDescription)
                ->first();

            if ($existingBranchTx !== null) {
                if (abs((float) $existingBranchTx->amount - $fee) > 0.005) {
                    TransactionService::reverseTransaction($existingBranchTx);
                } else {
                    return $existingBranchTx;
                }
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
                'description' => $branchDescription,
            ], validateBalance: false);
        });

        $superadminAccrualTx = $this->onMainPanel(function () use ($branch, $cycleStartDate, $fee, $superadminDescription): ?Transaction {
            $existingSuperadminTx = Transaction::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branch->id)
                ->where('description', $superadminDescription)
                ->first();

            if ($existingSuperadminTx !== null) {
                if (abs((float) $existingSuperadminTx->amount - $fee) > 0.005) {
                    TransactionService::reverseTransaction($existingSuperadminTx);
                } else {
                    return $existingSuperadminTx;
                }
            }

            $receivableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
            $incomeAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionIncome, null);

            return TransactionService::recordTransaction([
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
        });

        return [
            'branch_accrual' => $branchAccrualTx,
            'superadmin_accrual' => $superadminAccrualTx,
        ];
    }

    /**
     * Settle an approved payment against accrued payable/receivable and move the payment channel.
     * Does not re-recognize Expense or Income.
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

        if ($branch && ! Branch::isMainBranch($payment->branch_id)) {
            $this->onClientBranch($branch, function () use ($branch, $payment, $amount, $paymentDate): void {
                $alreadySettledOnBranch = Transaction::query()
                    ->where('source_type', BranchSubscriptionPayment::class)
                    ->where('source_id', $payment->id)
                    ->exists();

                // Do not invent a new billing accrual when replaying settlement for an
                // already-posted payment (payable may already be cleared).
                if ($alreadySettledOnBranch) {
                    return;
                }

                $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $payment->branch_id);
                $payableBalance = max(0.0, (float) $payableAccount->fresh()->current_balance);
                $unaccruedAmount = round($amount - $payableBalance, 2);

                if ($unaccruedAmount > 0.005) {
                    $startsAt = $payment->billing_period_starts_at
                        ? $payment->billing_period_starts_at->format('Y-m-d')
                        : $paymentDate;
                    $endsAt = $payment->billing_period_ends_at
                        ? $payment->billing_period_ends_at->format('Y-m-d')
                        : $paymentDate;
                    $this->recordCycleAccrual($branch, $startsAt, $endsAt, $unaccruedAmount);
                }
            });
        }

        $branchDescription = "Subscription payment settled for {$branchName} (Ref: {$payment->transaction_reference})";
        $superadminDescription = "Subscription payment received from {$branchName} (Ref: {$payment->transaction_reference})";

        $branchTransaction = null;

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

                $payableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionPayable, $payment->branch_id);
                $branchPaymentAccount = $this->resolveBranchPaymentAccount($payment, $paymentAccountKey);

                return TransactionService::recordTransaction([
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
            });
        }

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

            $superadminPaymentAccount = ($payment->payment_account_id
                ? ChartOfAccount::query()
                    ->whereNull('source_type')
                    ->whereNull('source_id')
                    ->whereKey($payment->payment_account_id)
                    ->first()
                : null)
                ?? ChartOfAccount::query()
                    ->whereNull('source_type')
                    ->whereNull('source_id')
                    ->where('type', AccountType::Asset)
                    ->where(function ($q) use ($payment): void {
                        $q->where('name', $payment->payment_method)
                            ->orWhere('code', $payment->payment_method);
                    })
                    ->first() ?? SystemAccountService::resolve($paymentAccountKey, null);

            $receivableAccount = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);

            return TransactionService::recordTransaction([
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
        });

        return [
            'branch_transaction' => $branchTransaction,
            'superadmin_transaction' => $superadminTransaction,
        ];
    }

    /**
     * Ensure approved payments are reflected on both charts (client + SuperAdmin).
     * Skips a panel when that settlement leg already exists.
     *
     * @return array{checked: int, posted: int}
     */
    public function syncMissingPaymentSettlements(?User $actor = null, ?int $onlyBranchId = null): array
    {
        $payments = BranchSubscriptionPayment::query()
            ->where('status', 'approved')
            ->where('amount', '>', 0)
            ->when($onlyBranchId !== null, fn ($q) => $q->where('branch_id', $onlyBranchId))
            ->orderBy('id')
            ->get();

        $posted = 0;

        foreach ($payments as $payment) {
            $branch = $payment->branch ?? Branch::query()->find($payment->branch_id);

            if ($branch === null || Branch::isMainBranch($branch->id)) {
                continue;
            }

            $missingOnClient = $this->onClientBranch($branch, function () use ($payment): bool {
                return ! Transaction::query()
                    ->where('source_type', BranchSubscriptionPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('description', 'like', 'Subscription payment settled%')
                    ->exists();
            });

            $missingOnAdmin = $this->onMainPanel(function () use ($payment): bool {
                return ! Transaction::query()
                    ->where('source_type', BranchSubscriptionPayment::class)
                    ->where('source_id', $payment->id)
                    ->where('description', 'like', 'Subscription payment received%')
                    ->exists();
            });

            if (! $missingOnClient && ! $missingOnAdmin) {
                continue;
            }

            $result = $this->recordPaymentSettlement($payment, $actor);

            if ($result['branch_transaction'] !== null || $result['superadmin_transaction'] !== null) {
                $posted++;
            }
        }

        return [
            'checked' => $payments->count(),
            'posted' => $posted,
        ];
    }

    /**
     * @deprecated Use syncMissingPaymentSettlements() — kept for callers expecting the old name.
     */
    public function syncMissingSuperAdminSettlements(?User $actor = null): int
    {
        return $this->syncMissingPaymentSettlements($actor)['posted'];
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
