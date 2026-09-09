<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\BranchSecurityDeposit;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class BranchSecurityDepositService
{
    public function __construct(
        private BranchSubscriptionAccountingService $accounting,
        private TenantProvisioner $tenants,
    ) {}

    /**
     * Record security money taken upon project handover:
     * - SuperAdmin: Debit Payment Account (Asset increases), Credit Client Security Deposit (Liability increases)
     * - Client Branch: Debit Project Security Expense (Expense increases), Credit Payment Account (Asset decreases)
     *
     * @param  array<string, mixed>  $data
     */
    public function recordDeposit(Branch $branch, array $data, ?User $actor = null): BranchSecurityDeposit
    {
        $amount = (float) ($data['amount'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $paymentAccountId = isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null;
        $transactionReference = $data['transaction_reference'] ?? null;
        $paidAt = $data['paid_at'] ?? now()->toDateString();
        $notes = $data['notes'] ?? 'Project handover security money';
        $attachmentPath = null;

        if (isset($data['attachment']) && $data['attachment'] instanceof UploadedFile) {
            $attachmentPath = $data['attachment']->store('security-deposits', 'public');
        } elseif (! empty($data['existing_attachment_path']) && is_string($data['existing_attachment_path'])) {
            $attachmentPath = $data['existing_attachment_path'];
        }

        $deposit = BranchSecurityDeposit::create([
            'branch_id' => $branch->id,
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_account_id' => $paymentAccountId,
            'transaction_reference' => $transactionReference,
            'paid_at' => $paidAt,
            'status' => 'approved',
            'notes' => $notes,
            'recorded_by_user_id' => $actor?->id,
            'attachment_path' => $attachmentPath,
        ]);

        if ($amount > 0 && ! Branch::isMainBranch($branch->id)) {
            $this->postDepositAccounting($deposit, $actor);
        }

        return $deposit;
    }

    /**
     * Post GL entries for security deposit.
     */
    public function postDepositAccounting(BranchSecurityDeposit $deposit, ?User $actor = null): void
    {
        $branch = $deposit->branch ?? Branch::find($deposit->branch_id);
        if (! $branch || Branch::isMainBranch($branch->id) || $deposit->amount <= 0) {
            return;
        }

        $amount = (float) $deposit->amount;
        $paidDate = $deposit->paid_at ? $deposit->paid_at->format('Y-m-d') : now()->toDateString();
        $paymentAccountKey = $this->accounting->resolvePaymentAccountKey($deposit->payment_method);
        $branchName = $branch->name ?: "Branch #{$branch->id}";
        $description = "Project security money for {$branchName} (Ref: {$deposit->transaction_reference})";

        // 1. SuperAdmin: Debit Payment Account (Asset), Credit Client Security Deposit (Liability)
        $this->onMainPanel(function () use ($deposit, $actor, $amount, $paidDate, $paymentAccountKey, $description): ?Transaction {
            $existing = Transaction::where('source_type', BranchSecurityDeposit::class)
                ->where('source_id', $deposit->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $superadminPaymentAccount = ChartOfAccount::query()
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->where('type', AccountType::Asset)
                ->where(function ($q) use ($deposit): void {
                    $q->where('name', $deposit->payment_method)
                        ->orWhere('code', $deposit->payment_method);
                })
                ->first() ?? SystemAccountService::resolve($paymentAccountKey, null);

            $securityDepositAccount = SystemAccountService::resolve(SystemAccountKey::ClientSecurityDeposit, null);

            return TransactionService::recordTransaction([
                'source_type' => BranchSecurityDeposit::class,
                'source_id' => $deposit->id,
                'performed_by_type' => $actor ? User::class : null,
                'performed_by_id' => $actor?->id,
                'date' => $paidDate,
                'amount' => $amount,
                'debit_account_id' => $superadminPaymentAccount->id,
                'credit_account_id' => $securityDepositAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => false,
                'description' => $description,
            ], validateBalance: false);
        });

        // 2. Client Branch: Debit Security Deposit Paid (Asset), Credit Payment Account (Asset)
        $this->onClientBranch($branch, function () use ($deposit, $branch, $amount, $paidDate, $paymentAccountKey): ?Transaction {
            $depositAssetAccount = SystemAccountService::resolve(SystemAccountKey::SecurityDepositPaid, $branch->id);

            $existing = Transaction::where('source_type', BranchSecurityDeposit::class)
                ->where('source_id', $deposit->id)
                ->where('debit_account_id', $depositAssetAccount->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $branchPaymentAccount = ChartOfAccount::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $branch->id)
                ->where('type', AccountType::Asset)
                ->where(function ($q) use ($deposit): void {
                    $q->where('name', $deposit->payment_method)
                        ->orWhere('code', $deposit->payment_method);
                })
                ->first() ?? SystemAccountService::resolve($paymentAccountKey, $branch->id);

            return TransactionService::recordTransaction([
                'source_type' => BranchSecurityDeposit::class,
                'source_id' => $deposit->id,
                'date' => $paidDate,
                'amount' => $amount,
                'debit_account_id' => $depositAssetAccount->id,
                'credit_account_id' => $branchPaymentAccount->id,
                'debit_decrease' => false,
                'credit_decrease' => true,
                'description' => "Project handover security deposit paid (Ref: {$deposit->transaction_reference})",
            ], validateBalance: false);
        });
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
