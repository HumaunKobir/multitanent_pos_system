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
    /**
     * @return array<string, mixed>
     */
    public function buildReport(BusinessSession $session): array
    {
        $session->loadMissing(['startedBy', 'closedBy', 'branch', 'accountBalances']);

        $transactionScope = app(BusinessSessionTransactionScope::class);
        $transactionScope->syncSessionTransactions($session);

        $transactions = Transaction::query()
            ->where('business_session_id', $session->id)
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
        $pendingTransactions = $transactionRows->filter(fn (array $row) => $row['approval_status'] === 'Pending')->values()->all();
        $closingSummary = $this->buildClosingSummary($accountBalances, $incomeSummary, $expenseSummary);

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
            'warnings' => [
                'pending_transactions' => $pendingTransactions,
                'pending_count' => count($pendingTransactions),
                'balance_mismatch' => false,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $accountBalances
     */
    public function persistAccountClosingBalances(BusinessSession $session, array $accountBalances): void
    {
        foreach ($accountBalances as $row) {
            BusinessSessionAccountBalance::query()
                ->where('business_session_id', $session->id)
                ->where('account_id', $row['account_id'])
                ->update([
                    'total_debit' => $row['total_debit'],
                    'total_credit' => $row['total_credit'],
                    'total_received' => $row['total_received'],
                    'total_paid' => $row['total_paid'],
                    'total_transfer_in' => $row['total_transfer_in'],
                    'total_transfer_out' => $row['total_transfer_out'],
                    'closing_balance' => $row['closing_balance'],
                ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildAccountBalances(BusinessSession $session, Collection $ledgers, Collection $transactions): array
    {
        $openingByAccount = $session->accountBalances->keyBy('account_id');
        $paymentAccountIds = $this->paymentAccountIds($session);
        $contraTransactionIds = $this->contraTransactionIds($transactions);

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
                    'approval_status' => $transaction->approved_at ? 'Approved' : 'Pending',
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
