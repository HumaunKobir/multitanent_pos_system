<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\BusinessSession;
use App\Models\BusinessSessionAccountBalance;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Collection;

class BusinessSessionReportService
{
    /** @var list<int> */
    private array $accountsAddedDuringSession = [];

    /**
     * @return array<string, mixed>
     */
    public function buildReport(BusinessSession $session): array
    {
        $this->accountsAddedDuringSession = [];

        $session->loadMissing(['startedBy', 'closedBy', 'branch', 'accountBalances']);

        $this->syncMissingAccountBalances($session);

        $transactionScope = app(BusinessSessionTransactionScope::class);
        $transactionScope->syncSessionTransactions($session);

        $transactions = Transaction::query()
            ->where('business_session_id', $session->id)
            ->where('performed_by_type', User::class)
            ->where('performed_by_id', $session->started_by_user_id)
            ->tap(fn ($query) => $transactionScope->scopeForBranch($query, $session->branch_id))
            ->tap(fn ($query) => $transactionScope->scopeWithinSessionWindow($query, $session))
            ->withTrashed()
            ->with([
                'debitAccount:id,code,name',
                'creditAccount:id,code,name',
                'performedBy',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $transactionIds = $transactions->pluck('id');

        $ledgers = Ledger::query()
            ->whereIn('transaction_id', $transactionIds)
            ->with('account:id,code,name,type')
            ->orderBy('id')
            ->get();

        $accountBalances = $this->buildAccountBalances($session, $ledgers, $transactions);
        $contraTransactionIds = $this->contraTransactionIds($transactions);
        $transfers = $this->buildTransfers($transactions, $contraTransactionIds);
        $incomeSummary = $this->buildIncomeSummary($transactions);
        $expenseSummary = $this->buildExpenseSummary($transactions);
        $transactionRows = $this->buildTransactionRows($transactions, $session, $contraTransactionIds);
        $closingSummary = $this->buildClosingSummary($accountBalances, $incomeSummary, $expenseSummary);
        $accountBalances = $this->filterAccountsUsedInSession($accountBalances);

        $closedAt = $session->closed_at ?? now();
        $durationMinutes = (int) round($session->started_at->diffInMinutes($closedAt));

        return [
            'session' => [
                'id' => $session->id,
                'session_number' => $session->session_number,
                'session_date' => $session->session_date->format('Y-m-d'),
                'branch_name' => $session->branch?->name ?? 'Head Office',
                'started_by' => $session->startedBy?->name ?? '—',
                'started_at' => $session->started_at->timezone(config('app.timezone'))->format('Y-m-d g:i A'),
                'closed_at' => $session->closed_at?->timezone(config('app.timezone'))->format('Y-m-d g:i A'),
                'closed_by' => $session->closedBy?->name,
                'duration' => $this->formatDuration($durationMinutes),
                'status' => $session->status->label(),
                'opening_method' => $session->opening_method->label(),
            ],
            'account_balances' => $accountBalances,
            'transactions' => $transactionRows->all(),
            'transfers' => $transfers,
            'income_summary' => $incomeSummary,
            'expense_summary' => $expenseSummary,
            'closing_summary' => $closingSummary,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $accountBalances
     */
    public function persistAccountClosingBalances(BusinessSession $session, array $accountBalances): void
    {
        foreach ($accountBalances as $row) {
            $account = ChartOfAccount::query()->find($row['account_id']);

            $balance = BusinessSessionAccountBalance::query()->firstOrNew([
                'business_session_id' => $session->id,
                'account_id' => $row['account_id'],
            ]);

            if (! $balance->exists) {
                $balance->account_name = $row['account_name'];
                $balance->account_type = $account?->type ?? AccountType::Asset;
                $balance->opening_balance = $row['opening_balance'];
            }

            $balance->fill([
                'total_debit' => $row['total_debit'],
                'total_credit' => $row['total_credit'],
                'total_received' => $row['total_received'],
                'total_paid' => $row['total_paid'],
                'total_transfer_in' => $row['total_transfer_in'],
                'total_transfer_out' => $row['total_transfer_out'],
                'closing_balance' => $row['closing_balance'],
            ]);

            $balance->save();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function syncMissingAccountBalances(BusinessSession $session): void
    {
        $existingAccountIds = $session->accountBalances->pluck('account_id')->all();

        $missingAccounts = $this->branchAccounts($session->branch_id)
            ->whereNotIn('id', $existingAccountIds);

        $changed = false;

        foreach ($missingAccounts as $account) {
            BusinessSessionAccountBalance::query()->create([
                'business_session_id' => $session->id,
                'account_id' => $account->id,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'opening_balance' => $this->resolveSessionOpeningBalance($account->id, $session),
            ]);

            $this->accountsAddedDuringSession[] = $account->id;

            $changed = true;
        }

        foreach ($session->accountBalances as $balance) {
            $account = ChartOfAccount::query()->find($balance->account_id);

            if ($account === null || $account->created_at->lt($session->started_at)) {
                continue;
            }

            $sessionOpening = $this->resolveSessionOpeningBalance($account->id, $session);

            if ($sessionOpening > 0 && (float) $balance->opening_balance !== $sessionOpening) {
                $balance->update(['opening_balance' => $sessionOpening]);
                $this->accountsAddedDuringSession[] = $account->id;
                $changed = true;
            }
        }

        if ($changed) {
            $session->unsetRelation('accountBalances');
            $session->load('accountBalances');
        }
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    private function branchAccounts(?int $branchId): Collection
    {
        $query = ChartOfAccount::query()->whereNotNull('parent_id');

        if ($branchId === null) {
            $query->whereNull('source_type')->whereNull('source_id');
        } else {
            $query->where('source_type', Branch::class)->where('source_id', $branchId);
        }

        return $query->orderBy('code')->get();
    }

    private function buildAccountBalances(BusinessSession $session, Collection $ledgers, Collection $transactions): array
    {
        $paymentAccountIds = $this->paymentAccountIds($session);
        $contraTransactionIds = $this->contraTransactionIds($transactions);
        $openingBalanceTransactionIds = $this->openingBalanceTransactionIds($transactions);

        $aggregates = [];

        foreach ($session->accountBalances as $balance) {
            $aggregates[$balance->account_id] = [
                'account_id' => $balance->account_id,
                'account_name' => $balance->account_name,
                'account_type' => $balance->account_type->label(),
                'account_type_enum' => $balance->account_type,
                'opening_balance' => round((float) $balance->opening_balance, 2),
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'total_received' => 0.0,
                'total_paid' => 0.0,
                'total_transfer_in' => 0.0,
                'total_transfer_out' => 0.0,
                'closing_balance' => round((float) $balance->opening_balance, 2),
            ];
        }

        foreach ($ledgers as $ledger) {
            $accountId = $ledger->account_id;

            if (! isset($aggregates[$accountId])) {
                continue;
            }

            if ($openingBalanceTransactionIds->contains($ledger->transaction_id)) {
                continue;
            }

            $debit = (float) $ledger->debit;
            $credit = (float) $ledger->credit;
            $aggregates[$accountId]['total_debit'] += $debit;
            $aggregates[$accountId]['total_credit'] += $credit;

            $isPaymentAccount = in_array($accountId, $paymentAccountIds, true);
            $isContra = $contraTransactionIds->contains($ledger->transaction_id);

            if ($isPaymentAccount) {
                $aggregates[$accountId]['total_received'] += $debit;
                $aggregates[$accountId]['total_paid'] += $credit;
            }

            if ($isContra) {
                $aggregates[$accountId]['total_transfer_in'] += $debit;
                $aggregates[$accountId]['total_transfer_out'] += $credit;
            }
        }

        foreach ($aggregates as $accountId => $row) {
            $aggregates[$accountId]['closing_balance'] = round(
                $row['opening_balance'] + $this->sessionBalanceChange(
                    $row['account_type_enum'],
                    $row['total_debit'],
                    $row['total_credit'],
                ),
                2,
            );
        }

        return array_values(array_map(function (array $row) {
            return [
                'account_id' => $row['account_id'],
                'account_name' => $row['account_name'],
                'account_type' => $row['account_type'],
                'opening_balance' => round($row['opening_balance'], 2),
                'total_debit' => round($row['total_debit'], 2),
                'total_credit' => round($row['total_credit'], 2),
                'total_received' => round($row['total_received'], 2),
                'total_paid' => round($row['total_paid'], 2),
                'total_transfer_in' => round($row['total_transfer_in'], 2),
                'total_transfer_out' => round($row['total_transfer_out'], 2),
                'closing_balance' => round($row['closing_balance'], 2),
            ];
        }, $aggregates));
    }

    /**
     * @param  list<array<string, mixed>>  $accountBalances
     * @return list<array<string, mixed>>
     */
    private function filterAccountsUsedInSession(array $accountBalances): array
    {
        return array_values(array_filter(
            $accountBalances,
            fn (array $row) => $this->accountWasUsedInSession($row),
        ));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function accountWasUsedInSession(array $row): bool
    {
        $hasActivity = $row['total_debit'] > 0
            || $row['total_credit'] > 0
            || $row['total_received'] > 0
            || $row['total_paid'] > 0
            || $row['total_transfer_in'] > 0
            || $row['total_transfer_out'] > 0;

        if ($hasActivity) {
            return true;
        }

        if (in_array((int) $row['account_id'], $this->accountsAddedDuringSession, true)) {
            return (float) $row['opening_balance'] > 0;
        }

        return false;
    }

    private function sessionBalanceChange(AccountType $type, float $totalDebit, float $totalCredit): float
    {
        return match ($type) {
            AccountType::Asset, AccountType::Expenses => $totalDebit - $totalCredit,
            AccountType::Liability, AccountType::Income, AccountType::Equity => $totalCredit - $totalDebit,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTransfers(Collection $transactions, Collection $contraTransactionIds): array
    {
        if ($contraTransactionIds->isEmpty()) {
            return [];
        }

        $contraTransactions = $transactions->whereIn('id', $contraTransactionIds->all());
        $voucherIds = $contraTransactions
            ->where('source_type', Voucher::class)
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->values();

        $vouchers = Voucher::query()
            ->where('type', VoucherType::Contra)
            ->where(function ($query) use ($voucherIds, $contraTransactionIds) {
                $query->whereIn('id', $voucherIds)
                    ->orWhereIn('transaction_id', $contraTransactionIds);
            })
            ->with(['fromAccount:id,name', 'toAccount:id,name', 'createdBy:id,name'])
            ->get()
            ->keyBy('id');

        $vouchersByTransactionId = $vouchers->keyBy('transaction_id');

        return $contraTransactions
            ->map(function (Transaction $transaction) use ($vouchers, $vouchersByTransactionId) {
                $voucher = $transaction->source_type === Voucher::class
                    ? $vouchers->get($transaction->source_id)
                    : null;

                $voucher ??= $vouchersByTransactionId->get($transaction->id);

                if ($voucher !== null) {
                    return [
                        'date' => $voucher->date->format('Y-m-d'),
                        'time' => $voucher->created_at?->timezone(config('app.timezone'))->format('g:i A'),
                        'reference' => $voucher->voucher_no,
                        'from_account' => $voucher->fromAccount?->name ?? '—',
                        'to_account' => $voucher->toAccount?->name ?? '—',
                        'amount' => round((float) $voucher->total_amount, 2),
                        'method' => 'Contra',
                        'created_by' => $voucher->createdBy?->name ?? '—',
                        'approved_by' => '—',
                        'remarks' => $voucher->narration ?? '—',
                    ];
                }

                return [
                    'date' => $transaction->date->format('Y-m-d'),
                    'time' => $transaction->created_at?->timezone(config('app.timezone'))->format('g:i A'),
                    'reference' => 'Transfer #'.$transaction->id,
                    'from_account' => $transaction->creditAccount?->name ?? '—',
                    'to_account' => $transaction->debitAccount?->name ?? '—',
                    'amount' => round((float) $transaction->amount, 2),
                    'method' => 'Contra',
                    'created_by' => '—',
                    'approved_by' => '—',
                    'remarks' => $transaction->description ?? '—',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildIncomeSummary(Collection $transactions): array
    {
        return $this->buildVoucherCategorySummary($transactions, VoucherType::Income, 'income');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildExpenseSummary(Collection $transactions): array
    {
        return $this->buildVoucherCategorySummary($transactions, VoucherType::Expense, 'expense');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildVoucherCategorySummary(Collection $transactions, VoucherType $type, string $kind): array
    {
        $voucherIds = $transactions
            ->where('source_type', Voucher::class)
            ->pluck('source_id')
            ->filter()
            ->unique();

        $vouchers = Voucher::query()
            ->whereIn('id', $voucherIds)
            ->where('type', $type)
            ->with(['lines.account:id,name', 'paymentAccount:id,name', 'toAccount:id,name'])
            ->get();

        $summary = [];

        foreach ($vouchers as $voucher) {
            $categoryAccount = $kind === 'income'
                ? ($voucher->toAccount?->name ?? $voucher->lines->first()?->account?->name ?? 'Other Income')
                : ($voucher->lines->first()?->account?->name ?? 'Other Expense');

            $paymentAccount = $voucher->paymentAccount?->name ?? '—';
            $key = $categoryAccount.'|'.$paymentAccount;

            if (! isset($summary[$key])) {
                $summary[$key] = [
                    'category' => $categoryAccount,
                    'account_name' => $categoryAccount,
                    'payment_account' => $paymentAccount,
                    'transaction_count' => 0,
                    'total_amount' => 0.0,
                    'details' => [],
                ];
            }

            $summary[$key]['transaction_count']++;
            $summary[$key]['total_amount'] += (float) $voucher->total_amount;
            $summary[$key]['details'][] = [
                'reference' => $voucher->voucher_no,
                'date' => $voucher->date->format('Y-m-d'),
                'amount' => round((float) $voucher->total_amount, 2),
                'narration' => $voucher->narration ?? '—',
            ];
        }

        return array_values(array_map(function (array $row) {
            $row['total_amount'] = round($row['total_amount'], 2);

            return $row;
        }, $summary));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildTransactionRows(Collection $transactions, BusinessSession $session, Collection $contraTransactionIds): Collection
    {
        $voucherIds = $transactions
            ->where('source_type', Voucher::class)
            ->pluck('source_id')
            ->filter()
            ->unique();

        $vouchers = $voucherIds->isEmpty()
            ? collect()
            : Voucher::query()->whereIn('id', $voucherIds)->get(['id', 'voucher_no', 'type'])->keyBy('id');

        $performerIds = $transactions
            ->where('performed_by_type', User::class)
            ->pluck('performed_by_id')
            ->filter()
            ->unique();

        $performers = $performerIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $performerIds)->pluck('name', 'id');

        return $transactions
            ->reject(fn (Transaction $transaction) => $contraTransactionIds->contains($transaction->id))
            ->map(function (Transaction $transaction) use ($session, $vouchers, $performers) {
                $sourceLabel = $transaction->source_type
                    ? class_basename($transaction->source_type)
                    : 'Manual';

                $voucherNo = null;
                $typeLabel = $sourceLabel;

                if ($transaction->source_type === Voucher::class) {
                    $voucher = $vouchers->get($transaction->source_id);
                    $voucherNo = $voucher?->voucher_no;
                    $typeLabel = $voucher?->type?->label() ?? 'Voucher';
                }

                $performedBy = '—';
                if ($transaction->performed_by_type === User::class && $transaction->performed_by_id) {
                    $performedBy = $performers->get($transaction->performed_by_id) ?? '—';
                }

                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date->format('Y-m-d'),
                    'time' => $transaction->created_at?->timezone(config('app.timezone'))->format('g:i A'),
                    'reference' => $voucherNo ?? ($sourceLabel.' #'.$transaction->source_id),
                    'type' => $typeLabel,
                    'source_account' => $transaction->debitAccount
                        ? "{$transaction->debitAccount->code} — {$transaction->debitAccount->name}"
                        : '—',
                    'destination_account' => $transaction->creditAccount
                        ? "{$transaction->creditAccount->code} — {$transaction->creditAccount->name}"
                        : '—',
                    'description' => $transaction->description ?? '—',
                    'debit' => round((float) $transaction->amount, 2),
                    'credit' => round((float) $transaction->amount, 2),
                    'created_by' => $performedBy,
                    'branch' => $session->branch?->name ?? 'Head Office',
                    'is_deleted' => $transaction->trashed(),
                ];
            })
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $accountBalances
     * @param  list<array<string, mixed>>  $incomeSummary
     * @param  list<array<string, mixed>>  $expenseSummary
     * @return array<string, mixed>
     */
    private function buildClosingSummary(array $accountBalances, array $incomeSummary, array $expenseSummary): array
    {
        $totalOpening = round(array_sum(array_column($accountBalances, 'opening_balance')), 2);
        $totalClosing = round(array_sum(array_column($accountBalances, 'closing_balance')), 2);
        $totalReceipts = round(array_sum(array_column($accountBalances, 'total_received')), 2);
        $totalPayments = round(array_sum(array_column($accountBalances, 'total_paid')), 2);
        $totalTransferIn = round(array_sum(array_column($accountBalances, 'total_transfer_in')), 2);
        $totalTransferOut = round(array_sum(array_column($accountBalances, 'total_transfer_out')), 2);
        $totalIncome = round(array_sum(array_column($incomeSummary, 'total_amount')), 2);
        $totalExpenses = round(array_sum(array_column($expenseSummary, 'total_amount')), 2);

        return [
            'total_opening_balance' => $totalOpening,
            'total_receipts' => $totalReceipts,
            'total_payments' => $totalPayments,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_transfer_in' => $totalTransferIn,
            'total_transfer_out' => $totalTransferOut,
            'total_closing_balance' => $totalClosing,
        ];
    }

    /**
     * @return Collection<int, int>
     */
    private function openingBalanceTransactionIds(Collection $transactions): Collection
    {
        return $transactions
            ->filter(fn (Transaction $transaction) => $transaction->source_type === ChartOfAccount::class
                && str_starts_with((string) $transaction->description, 'Opening balance —'))
            ->pluck('id');
    }

    private function resolveSessionOpeningBalance(int $accountId, BusinessSession $session): float
    {
        $transaction = Transaction::query()
            ->where('source_type', ChartOfAccount::class)
            ->where('source_id', $accountId)
            ->where('description', 'like', 'Opening balance —%')
            ->where('created_at', '>=', $session->started_at)
            ->when(
                $session->closed_at !== null,
                fn ($query) => $query->where('created_at', '<=', $session->closed_at),
            )
            ->orderBy('id')
            ->first();

        if ($transaction === null) {
            return 0.0;
        }

        return round((float) $transaction->amount, 2);
    }

    /**
     * @return list<int>
     */
    private function paymentAccountIds(BusinessSession $session): array
    {
        $branchId = $session->branch_id;
        $cashBankParentId = SystemAccountService::id(SystemAccountKey::CashAndBank, $branchId);

        $query = ChartOfAccount::query()
            ->where('type', AccountType::Asset)
            ->where('parent_id', $cashBankParentId);

        if ($branchId === null) {
            $query->whereNull('source_type')->whereNull('source_id');
        } else {
            $query->where('source_type', Branch::class)->where('source_id', $branchId);
        }

        return $query->pluck('id')->all();
    }

    /**
     * @return Collection<int, int>
     */
    private function contraTransactionIds(Collection $transactions): Collection
    {
        $voucherIds = $transactions
            ->where('source_type', Voucher::class)
            ->pluck('source_id');

        $contraVoucherIds = Voucher::query()
            ->whereIn('id', $voucherIds)
            ->where('type', VoucherType::Contra)
            ->pluck('id');

        return $transactions
            ->where('source_type', Voucher::class)
            ->whereIn('source_id', $contraVoucherIds)
            ->pluck('id');
    }

    private function formatDuration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $mins);
        }

        return sprintf('%dm', $mins);
    }
}
