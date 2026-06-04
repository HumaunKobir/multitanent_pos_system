<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ProductLogType;
use App\Enums\PurchaseType;
use App\Enums\VoucherType;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Damage;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductInOutLog;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReportService
{
    /**
     * @return array{customer: array<string, mixed>|null, entries: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function customerLedger(int $customerId, ?string $dateFrom, ?string $dateTo): array
    {
        $customer = Customer::query()
            ->when($this->branchId(), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->find($customerId);

        if ($customer === null) {
            return ['customer' => null, 'entries' => [], 'totals' => ['debit' => 0, 'credit' => 0, 'balance' => 0]];
        }

        $entries = collect();

        Sell::query()
            ->sale()
            ->where('customer_id', $customerId)
            ->when($this->branchId(), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (Sell $sell) use ($entries) {
                $net = $sell->net_amount;
                $paid = (float) $sell->paid_amount;
                $due = max(0, $net - $paid);

                $entries->push([
                    'sort_key' => $sell->date->format('Y-m-d').'-1-'.$sell->id,
                    'date' => $sell->date->format('Y-m-d'),
                    'type' => 'Sale',
                    'reference' => $sell->invoice_number,
                    'description' => $sell->comment ?: 'Sale invoice',
                    'debit' => round($due, 2),
                    'credit' => round($paid, 2),
                ]);
            });

        SaleReturn::query()
            ->where('customer_id', $customerId)
            ->when($this->branchId(), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (SaleReturn $return) use ($entries) {
                $gross = (float) $return->gross_amount;
                $paid = (float) $return->paid_amount;

                $entries->push([
                    'sort_key' => $return->date->format('Y-m-d').'-2-'.$return->id,
                    'date' => $return->date->format('Y-m-d'),
                    'type' => 'Sale Return',
                    'reference' => $return->invoice_number,
                    'description' => $return->comment ?: 'Sale return',
                    'debit' => round($paid, 2),
                    'credit' => round($gross, 2),
                ]);
            });

        $sorted = $entries->sortBy('sort_key')->values();
        $balance = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $rows = $sorted->map(function (array $row) use (&$balance, &$totalDebit, &$totalCredit) {
            $debit = (float) $row['debit'];
            $credit = (float) $row['credit'];
            $balance += $debit - $credit;
            $totalDebit += $debit;
            $totalCredit += $credit;

            return [
                'date' => $row['date'],
                'type' => $row['type'],
                'reference' => $row['reference'],
                'description' => $row['description'],
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($balance, 2),
            ];
        })->all();

        return [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'balance' => (float) $customer->balance,
            ],
            'entries' => $rows,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'balance' => round($balance, 2),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cashFlow(?int $accountId, ?string $dateFrom, ?string $dateTo): array
    {
        $accountIds = $this->cashAccountIds($accountId);

        if ($accountIds === []) {
            return [];
        }

        return Ledger::query()
            ->whereIn('account_id', $accountIds)
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->with(['account:id,code,name'])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->map(fn (Ledger $ledger) => [
                'date' => $ledger->date->format('Y-m-d'),
                'account' => $ledger->account
                    ? "{$ledger->account->code} — {$ledger->account->name}"
                    : '—',
                'description' => $ledger->description ?? '—',
                'debit' => (float) $ledger->debit,
                'credit' => (float) $ledger->credit,
                'balance' => (float) $ledger->closing_balance,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cashFlowSummary(?int $accountId, ?string $dateFrom, ?string $dateTo): array
    {
        $accountIds = $this->cashAccountIds($accountId);

        if ($accountIds === []) {
            return [];
        }

        return Ledger::query()
            ->whereIn('account_id', $accountIds)
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->selectRaw('date, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->date)->format('Y-m-d'),
                'debit' => round((float) $row->total_debit, 2),
                'credit' => round((float) $row->total_credit, 2),
                'net' => round((float) $row->total_debit - (float) $row->total_credit, 2),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dailyTransactions(?string $dateFrom, ?string $dateTo): array
    {
        return Ledger::query()
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->with(['account:id,code,name', 'transaction:id,description,source_type,source_id'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Ledger $ledger) => [
                'date' => $ledger->date->format('Y-m-d'),
                'account' => $ledger->account
                    ? "{$ledger->account->code} — {$ledger->account->name}"
                    : '—',
                'description' => $ledger->description ?? $ledger->transaction?->description ?? '—',
                'debit' => (float) $ledger->debit,
                'credit' => (float) $ledger->credit,
                'reference' => $ledger->transaction
                    ? class_basename($ledger->transaction->source_type).' #'.$ledger->transaction->source_id
                    : '—',
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dateWiseStock(?int $productId, ?string $dateFrom, ?string $dateTo): array
    {
        return ProductInOutLog::query()
            ->when($this->branchId(), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($productId, fn (Builder $q, int $id) => $q->where('product_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('created_at', '<=', $d))
            ->with(['product:id,name'])
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (ProductInOutLog $log) => [
                'date' => $log->created_at->format('Y-m-d'),
                'time' => $log->created_at->format('H:i'),
                'product' => $log->product?->name ?? '—',
                'sku' => '—',
                'type' => $this->productLogLabel($log->type),
                'quantity' => (int) $log->quantity,
                'stock' => (int) $log->stock,
                'remark' => $log->remark ?? '—',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function dailySummary(string $date): array
    {
        $branchId = $this->branchId();

        $salesQuery = Sell::query()->sale()->whereDate('date', $date);
        $purchasesQuery = Purchase::query()->where('purchase_type', PurchaseType::Purchase)->whereDate('date', $date);
        $paymentsQuery = SupplierPayment::query()->whereDate('date', $date);
        $returnsQuery = SaleReturn::query()->whereDate('date', $date);
        $damagesQuery = Damage::query()->whereDate('date', $date);
        $vouchersQuery = Voucher::query()->whereDate('date', $date);

        if ($branchId !== null) {
            $salesQuery->where('branch_id', $branchId);
            $purchasesQuery->where('branch_id', $branchId);
            $paymentsQuery->where('branch_id', $branchId);
            $returnsQuery->where('branch_id', $branchId);
            $damagesQuery->where('branch_id', $branchId);
            $vouchersQuery->where('branch_id', $branchId);
        }

        $sales = $salesQuery->get(['gross_amount', 'discount', 'vat', 'paid_amount']);
        $purchases = $purchasesQuery->get(['gross_amount', 'discount', 'vat', 'paid_amount']);
        $vouchers = $vouchersQuery->get(['type', 'total_amount']);

        $salesNet = $sales->sum(fn (Sell $s) => $s->net_amount);
        $salesPaid = $sales->sum(fn (Sell $s) => (float) $s->paid_amount);
        $purchaseNet = $purchases->sum(fn (Purchase $p) => $p->net_amount);
        $purchasePaid = $purchases->sum(fn (Purchase $p) => (float) $p->paid_amount);

        return [
            'date' => $date,
            'sales' => [
                'count' => $sales->count(),
                'gross' => round($salesNet, 2),
                'paid' => round($salesPaid, 2),
                'due' => round(max(0, $salesNet - $salesPaid), 2),
            ],
            'purchases' => [
                'count' => $purchases->count(),
                'gross' => round($purchaseNet, 2),
                'paid' => round($purchasePaid, 2),
                'due' => round(max(0, $purchaseNet - $purchasePaid), 2),
            ],
            'supplier_payments' => [
                'count' => $paymentsQuery->count(),
                'amount' => round((float) $paymentsQuery->sum('amount'), 2),
            ],
            'sale_returns' => [
                'count' => $returnsQuery->count(),
                'amount' => round((float) $returnsQuery->sum('gross_amount'), 2),
            ],
            'damages' => [
                'count' => $damagesQuery->count(),
            ],
            'vouchers' => [
                'income' => [
                    'count' => $vouchers->where('type', VoucherType::Income)->count(),
                    'amount' => round((float) $vouchers->where('type', VoucherType::Income)->sum('total_amount'), 2),
                ],
                'expense' => [
                    'count' => $vouchers->where('type', VoucherType::Expense)->count(),
                    'amount' => round((float) $vouchers->where('type', VoucherType::Expense)->sum('total_amount'), 2),
                ],
                'journal' => [
                    'count' => $vouchers->where('type', VoucherType::Journal)->count(),
                    'amount' => round((float) $vouchers->where('type', VoucherType::Journal)->sum('total_amount'), 2),
                ],
                'contra' => [
                    'count' => $vouchers->where('type', VoucherType::Contra)->count(),
                    'amount' => round((float) $vouchers->where('type', VoucherType::Contra)->sum('total_amount'), 2),
                ],
            ],
            'transactions' => [
                'count' => Transaction::query()->whereDate('date', $date)->count(),
            ],
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function customerOptions(): array
    {
        return Customer::query()
            ->when($this->branchId(), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'label' => "{$c->name} ({$c->phone})",
            ])
            ->all();
    }

    /**
     * @return array{account: array<string, mixed>|null, opening_balance: float, entries: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function accountLedger(int $accountId, ?string $dateFrom, ?string $dateTo): array
    {
        $account = ChartOfAccount::query()->find($accountId);

        if ($account === null) {
            return [
                'account' => null,
                'opening_balance' => 0,
                'entries' => [],
                'totals' => ['debit' => 0, 'credit' => 0, 'balance' => 0],
            ];
        }

        $openingBalance = $dateFrom
            ? $this->accountBalanceAsOf($accountId, Carbon::parse($dateFrom)->subDay()->format('Y-m-d'))
            : 0.0;

        $ledgers = Ledger::query()
            ->where('account_id', $accountId)
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $balance = $openingBalance;

        $entries = $ledgers->map(function (Ledger $ledger) use (&$balance, &$totalDebit, &$totalCredit) {
            $debit = (float) $ledger->debit;
            $credit = (float) $ledger->credit;
            $balance = (float) $ledger->closing_balance;
            $totalDebit += $debit;
            $totalCredit += $credit;

            return [
                'date' => $ledger->date->format('Y-m-d'),
                'description' => $ledger->description ?? '—',
                'reference' => $ledger->transaction_id
                    ? 'TXN-'.str_pad((string) $ledger->transaction_id, 6, '0', STR_PAD_LEFT)
                    : '—',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        })->all();

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type->label(),
                'current_balance' => (float) $account->current_balance,
            ],
            'opening_balance' => round($openingBalance, 2),
            'entries' => $entries,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'balance' => round($balance, 2),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accountTransactions(?int $accountId, ?string $dateFrom, ?string $dateTo): array
    {
        $transactionIds = null;

        if ($accountId !== null) {
            $transactionIds = Ledger::query()
                ->where('account_id', $accountId)
                ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
                ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
                ->pluck('transaction_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return Transaction::query()
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->when($accountId !== null, function (Builder $q) use ($accountId, $transactionIds) {
                $q->where(function (Builder $inner) use ($accountId, $transactionIds) {
                    $inner->where('debit_account_id', $accountId)
                        ->orWhere('credit_account_id', $accountId);

                    if ($transactionIds !== []) {
                        $inner->orWhereIn('id', $transactionIds);
                    }
                });
            })
            ->with([
                'debitAccount:id,code,name',
                'creditAccount:id,code,name',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (Transaction $transaction) {
                $lines = Ledger::query()
                    ->where('transaction_id', $transaction->id)
                    ->with('account:id,code,name')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Ledger $line) => [
                        'account' => $line->account
                            ? "{$line->account->code} — {$line->account->name}"
                            : '—',
                        'debit' => (float) $line->debit,
                        'credit' => (float) $line->credit,
                        'description' => $line->description ?? '—',
                    ])
                    ->all();

                return [
                    'id' => $transaction->id,
                    'date' => $transaction->date->format('Y-m-d'),
                    'description' => $transaction->description ?? '—',
                    'amount' => (float) $transaction->amount,
                    'debit_account' => $transaction->debitAccount
                        ? "{$transaction->debitAccount->code} — {$transaction->debitAccount->name}"
                        : ($lines !== [] ? 'Journal' : '—'),
                    'credit_account' => $transaction->creditAccount
                        ? "{$transaction->creditAccount->code} — {$transaction->creditAccount->name}"
                        : ($lines !== [] ? 'Journal' : '—'),
                    'source' => $transaction->source_type
                        ? class_basename($transaction->source_type).' #'.$transaction->source_id
                        : '—',
                    'lines' => $lines,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceSheet(string $asOfDate): array
    {
        $accounts = ChartOfAccount::query()
            ->whereNotNull('parent_id')
            ->whereIn('type', [AccountType::Asset, AccountType::Liability, AccountType::Equity])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $sections = [];

        foreach ([AccountType::Asset, AccountType::Liability, AccountType::Equity] as $type) {
            $lines = [];
            $total = 0.0;

            foreach ($accounts->where('type', $type) as $account) {
                $balance = $this->accountBalanceAsOf($account->id, $asOfDate);

                if (abs($balance) < 0.005) {
                    continue;
                }

                $lines[] = [
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => round($balance, 2),
                ];
                $total += $balance;
            }

            $sections[] = [
                'type' => $type->label(),
                'slug' => $type->slug(),
                'lines' => $lines,
                'total' => round($total, 2),
            ];
        }

        $totalAssets = collect($sections)->firstWhere('slug', 'asset')['total'] ?? 0.0;
        $totalLiabilities = collect($sections)->firstWhere('slug', 'liability')['total'] ?? 0.0;
        $totalEquity = collect($sections)->firstWhere('slug', 'equity')['total'] ?? 0.0;
        $liabilitiesPlusEquity = round($totalLiabilities + $totalEquity, 2);

        return [
            'as_of' => $asOfDate,
            'sections' => $sections,
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'liabilities_plus_equity' => $liabilitiesPlusEquity,
            'is_balanced' => abs($totalAssets - $liabilitiesPlusEquity) < 0.02,
        ];
    }

    /**
     * @return list<array{id: int, label: string, type: string}>
     */
    public function accountOptions(): array
    {
        return ChartOfAccount::query()
            ->whereNotNull('parent_id')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (ChartOfAccount $a) => [
                'id' => $a->id,
                'label' => "{$a->code} — {$a->name}",
                'type' => $a->type->label(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function cashAccountOptions(): array
    {
        return ChartOfAccount::query()
            ->where('type', AccountType::Asset)
            ->whereNotNull('parent_id')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (ChartOfAccount $a) => [
                'id' => $a->id,
                'label' => "{$a->code} — {$a->name}",
            ])
            ->all();
    }

    private function accountBalanceAsOf(int $accountId, string $asOfDate): float
    {
        $ledger = Ledger::query()
            ->where('account_id', $accountId)
            ->whereDate('date', '<=', $asOfDate)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        return $ledger ? (float) $ledger->closing_balance : 0.0;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function productOptions(): array
    {
        return Product::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => $p->name,
            ])
            ->all();
    }

    /**
     * @return list<int>
     */
    private function cashAccountIds(?int $accountId): array
    {
        if ($accountId !== null) {
            return [$accountId];
        }

        return ChartOfAccount::query()
            ->where('type', AccountType::Asset)
            ->whereNotNull('parent_id')
            ->pluck('id')
            ->all();
    }

    private function branchId(): ?int
    {
        return Auth::user()?->branch_id;
    }

    private function productLogLabel(int $type): string
    {
        try {
            $enum = ProductLogType::from($type);

            return str_replace('_', ' ', $enum->name);
        } catch (\ValueError) {
            return 'Unknown';
        }
    }
}
