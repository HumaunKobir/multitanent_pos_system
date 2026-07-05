<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ProductLogType;
use App\Enums\PurchaseType;
use App\Enums\SaleType;
use App\Enums\VoucherType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Damage;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\ProductExchange;
use App\Models\ProductInOutLog;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\SpecialDiscount;
use App\Models\StockDistribution;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReportService
{
    public function __construct(private InventoryCostService $costService) {}

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

        CustomerPayment::query()
            ->where('customer_id', $customerId)
            ->when($this->branchId(), fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (CustomerPayment $payment) use ($entries) {
                $entries->push([
                    'sort_key' => $payment->date->format('Y-m-d').'-3-'.$payment->id,
                    'date' => $payment->date->format('Y-m-d'),
                    'type' => 'Due Collection',
                    'reference' => $payment->invoice_number,
                    'description' => $payment->comment ?: 'Customer due collection',
                    'debit' => 0.0,
                    'credit' => round((float) $payment->amount, 2),
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

        return $this->scopeLedgerForBranch(Ledger::query())
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

        return $this->scopeLedgerForBranch(Ledger::query())
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
        return $this->scopeLedgerForBranch(Ledger::query())
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
     * @return array{
     *     mode: string,
     *     product: array<string, mixed>|null,
     *     opening_stock: float,
     *     entries: list<array<string, mixed>>,
     *     totals: array{in: float, out: float, balance: float},
     *     current_stock: float
     * }
     */
    public function stockLedger(?int $productId, ?int $branchId, ?string $dateFrom, ?string $dateTo): array
    {
        if ($productId === null) {
            return $this->buildStockLedgerOverview($branchId, $dateFrom, $dateTo);
        }

        $effectiveBranchId = $this->branchId() ?? $branchId;

        return $this->buildSingleProductStockLedger($productId, $effectiveBranchId, $dateFrom, $dateTo);
    }

    /**
     * @return array{
     *     mode: string,
     *     product: null,
     *     opening_stock: float,
     *     entries: list<array<string, mixed>>,
     *     totals: array{in: float, out: float, balance: float},
     *     current_stock: float
     * }
     */
    private function buildStockLedgerOverview(?int $branchId, ?string $dateFrom, ?string $dateTo): array
    {
        $scopeBranchId = $this->branchId() ?? $branchId;

        $logs = $this->stockLedgerLogQuery($scopeBranchId, null, $dateFrom, $dateTo)
            ->with(['product:id,name,code', 'branch:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $totalIn = 0.0;
        $totalOut = 0.0;

        $entries = $logs->map(function (ProductInOutLog $log) use (&$totalIn, &$totalOut) {
            $quantity = (float) $log->quantity;
            $isIn = $this->isStockInMovement($log->type);

            if ($isIn) {
                $totalIn += $quantity;
            } else {
                $totalOut += $quantity;
            }

            return [
                'date' => $log->created_at->format('Y-m-d'),
                'branch' => $log->branch?->name ?? 'Main Branch',
                'product' => $log->product?->name ?? '—',
                'product_code' => $log->product?->code,
                'type' => $this->productLogLabel($log->type),
                'reference' => $log->remark ?? '—',
                'in' => $isIn ? $quantity : 0.0,
                'out' => $isIn ? 0.0 : $quantity,
            ];
        })->all();

        return [
            'mode' => 'overview',
            'product' => null,
            'opening_stock' => 0,
            'entries' => $entries,
            'totals' => [
                'in' => round($totalIn, 2),
                'out' => round($totalOut, 2),
                'balance' => round($totalIn - $totalOut, 2),
            ],
            'current_stock' => 0,
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     product: array<string, mixed>|null,
     *     opening_stock: float,
     *     entries: list<array<string, mixed>>,
     *     totals: array{in: float, out: float, balance: float},
     *     current_stock: float
     * }
     */
    private function buildSingleProductStockLedger(int $productId, ?int $branchId, ?string $dateFrom, ?string $dateTo): array
    {
        $product = Product::query()
            ->ownBranch()
            ->find($productId);

        if ($product === null) {
            return [
                'mode' => 'ledger',
                'product' => null,
                'opening_stock' => 0,
                'entries' => [],
                'totals' => ['in' => 0, 'out' => 0, 'balance' => 0],
                'current_stock' => 0,
            ];
        }

        $openingStock = $dateFrom
            ? ($branchId !== null
                ? $this->productStockBalanceBefore($productId, $branchId, $dateFrom)
                : $this->productStockBalanceBeforeAllBranches($productId, $dateFrom))
            : 0.0;

        $logs = $this->stockLedgerLogQuery($branchId, $productId, $dateFrom, $dateTo)
            ->when($branchId === null, fn (Builder $q) => $q->with('branch:id,name'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $balance = $openingStock;
        $totalIn = 0.0;
        $totalOut = 0.0;

        $entries = $logs->map(function (ProductInOutLog $log) use (&$balance, &$totalIn, &$totalOut, $branchId) {
            $quantity = (float) $log->quantity;
            $isIn = $this->isStockInMovement($log->type);

            if ($isIn) {
                $totalIn += $quantity;
                $balance += $quantity;
            } else {
                $totalOut += $quantity;
                $balance -= $quantity;
            }

            $entry = [
                'date' => $log->created_at->format('Y-m-d'),
                'type' => $this->productLogLabel($log->type),
                'reference' => $log->remark ?? '—',
                'in' => $isIn ? $quantity : 0.0,
                'out' => $isIn ? 0.0 : $quantity,
                'balance' => round($balance, 2),
            ];

            if ($branchId === null) {
                $entry['branch'] = $log->branch?->name ?? 'Main Branch';
            }

            return $entry;
        })->all();

        $currentStock = $this->productCurrentStock($productId, $branchId);

        return [
            'mode' => 'ledger',
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'current_stock' => round($currentStock, 2),
            ],
            'opening_stock' => round($openingStock, 2),
            'entries' => $entries,
            'totals' => [
                'in' => round($totalIn, 2),
                'out' => round($totalOut, 2),
                'balance' => round($balance, 2),
            ],
            'current_stock' => round($currentStock, 2),
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function branchOptions(): array
    {
        return Branch::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'label' => $branch->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, branch_id: int|null}>
     */
    public function userOptions(?int $branchId = null): array
    {
        return User::query()
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'label' => $user->name,
                'branch_id' => $user->branch_id,
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
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     discount_summary: list<array<string, mixed>>,
     *     top_discount: array<string, mixed>|null
     * }
     */
    public function salesSummary(
        ?string $dateFrom,
        ?string $dateTo,
        ?int $productId = null,
        ?int $filterBranchId = null,
        string $sort = 'desc',
        ?string $discountFilter = null,
    ): array {
        $effectiveBranchId = $this->resolveReportBranchFilter($filterBranchId);
        $sortDirection = $sort === 'asc' ? 'asc' : 'desc';
        $productIds = $this->resolveReportProductIds($productId, $filterBranchId);

        $lines = SellProduct::query()
            ->select('sell_products.*')
            ->join('sells', 'sell_products.sell_id', '=', 'sells.id')
            ->where('sells.type', SaleType::Sale)
            ->when($effectiveBranchId, fn (Builder $q, int $id) => $q->where('sells.branch_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('sells.date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('sells.date', '<=', $d))
            ->when($productIds, fn (Builder $q, array $ids) => $q->whereIn('sell_products.product_id', $ids))
            ->when(
                $discountFilter && str_starts_with($discountFilter, 'promotion:'),
                fn (Builder $q) => $q->where('sell_products.promotion_id', (int) substr($discountFilter, 10)),
            )
            ->when(
                $discountFilter && str_starts_with($discountFilter, 'special:'),
                fn (Builder $q) => $q
                    ->whereNull('sell_products.promotion_id')
                    ->where('sell_products.discount', '<=', 0)
                    ->whereHas('sell', fn (Builder $sq) => $sq->where('special_discount_id', (int) substr($discountFilter, 8))),
            )
            ->when(
                $discountFilter === 'line',
                fn (Builder $q) => $q
                    ->whereNull('sell_products.promotion_id')
                    ->where('sell_products.discount', '>', 0),
            )
            ->when(
                $discountFilter === 'invoice',
                fn (Builder $q) => $q
                    ->whereNull('sell_products.promotion_id')
                    ->where('sell_products.discount', '<=', 0)
                    ->whereHas('sell', fn (Builder $sq) => $sq
                        ->whereNull('special_discount_id')
                        ->where('discount', '>', 0)
                        ->where('coin_discount_amount', '<=', 0)),
            )
            ->when(
                $discountFilter === 'coin',
                fn (Builder $q) => $q
                    ->whereNull('sell_products.promotion_id')
                    ->where('sell_products.discount', '<=', 0)
                    ->whereHas('sell', fn (Builder $sq) => $sq
                        ->whereNull('special_discount_id')
                        ->where('discount', '<=', 0)
                        ->where('coin_discount_amount', '>', 0)),
            )
            ->when(
                $discountFilter === 'none',
                fn (Builder $q) => $q
                    ->whereNull('sell_products.promotion_id')
                    ->where('sell_products.discount', '<=', 0)
                    ->whereHas('sell', fn (Builder $sq) => $sq
                        ->whereNull('special_discount_id')
                        ->where('discount', '<=', 0)
                        ->where('coin_discount_amount', '<=', 0)),
            )
            ->with([
                'sell:id,date,customer_id,special_discount_id,discount,coin_discount_amount',
                'sell.customer:id,name,phone',
                'sell.specialDiscount:id,name',
                'promotion:id,name,starts_at,ends_at',
                'product:id,name,code',
            ])
            ->orderByRaw('(sell_products.quantity + COALESCE(sell_products.free_quantity, 0)) '.$sortDirection)
            ->orderByDesc('sells.date')
            ->orderByDesc('sell_products.id')
            ->limit(500)
            ->get();

        $rows = $lines
            ->map(function (SellProduct $line) {
                $discount = $this->resolveSalesSummaryLineDiscount($line);

                return [
                    'id' => $line->id,
                    'date' => $line->sell->date->format('Y-m-d'),
                    'invoice' => $line->sell->invoice_number,
                    'customer_name' => $line->sell->customer?->name ?? 'Walk-in',
                    'customer_phone' => $line->sell->customer?->phone ?? '—',
                    'product' => $line->product?->name ?? '—',
                    'product_code' => $line->product?->code ?? '—',
                    'quantity' => (float) $line->quantity,
                    'free_quantity' => (float) $line->free_quantity,
                    'total_quantity' => round((float) $line->quantity + (float) $line->free_quantity, 2),
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => round(((float) $line->quantity * (float) $line->unit_price) - (float) $line->discount, 2),
                    'discount_key' => $discount['key'],
                    'discount_type' => $discount['type'],
                    'discount_label' => $discount['label'],
                    'discount_period' => $discount['period'],
                    'discount_amount' => $discount['amount'],
                ];
            })
            ->all();

        $discountSummary = collect($rows)
            ->groupBy('discount_key')
            ->map(function ($group, string $key) {
                $first = $group->first();

                return [
                    'key' => $key,
                    'type' => $first['discount_type'],
                    'label' => $first['discount_label'],
                    'period' => $first['discount_period'],
                    'line_count' => $group->count(),
                    'total_quantity' => round($group->sum(fn (array $row) => (float) $row['total_quantity']), 2),
                    'total_amount' => round($group->sum(fn (array $row) => (float) $row['line_total']), 2),
                    'total_discount' => round($group->sum(fn (array $row) => (float) $row['discount_amount']), 2),
                ];
            })
            ->sortByDesc('total_quantity')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'discount_summary' => $discountSummary,
            'top_discount' => $discountSummary[0] ?? null,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function salesSummaryDiscountOptions(
        ?string $dateFrom,
        ?string $dateTo,
        ?int $filterBranchId = null,
    ): array {
        $effectiveBranchId = $this->resolveReportBranchFilter($filterBranchId);

        $promotionIds = SellProduct::query()
            ->select('sell_products.promotion_id')
            ->join('sells', 'sell_products.sell_id', '=', 'sells.id')
            ->where('sells.type', SaleType::Sale)
            ->whereNotNull('sell_products.promotion_id')
            ->when($effectiveBranchId, fn (Builder $q, int $id) => $q->where('sells.branch_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('sells.date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('sells.date', '<=', $d))
            ->distinct()
            ->pluck('promotion_id');

        $specialDiscountIds = Sell::query()
            ->sale()
            ->whereNotNull('special_discount_id')
            ->when($effectiveBranchId, fn (Builder $q, int $id) => $q->where('branch_id', $id))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
            ->distinct()
            ->pluck('special_discount_id');

        $options = [
            ['value' => 'all', 'label' => 'All discounts'],
            ['value' => 'none', 'label' => 'Regular (no discount)'],
            ['value' => 'line', 'label' => 'Line discount'],
            ['value' => 'invoice', 'label' => 'Invoice discount'],
            ['value' => 'coin', 'label' => 'Coin discount'],
        ];

        Promotion::query()
            ->whereIn('id', $promotionIds)
            ->orderBy('name')
            ->get(['id', 'name', 'starts_at', 'ends_at'])
            ->each(function (Promotion $promotion) use (&$options) {
                $period = $this->formatDiscountPeriod($promotion->starts_at, $promotion->ends_at);
                $label = $period
                    ? "{$promotion->name} ({$period})"
                    : $promotion->name;

                $options[] = [
                    'value' => 'promotion:'.$promotion->id,
                    'label' => $label,
                ];
            });

        SpecialDiscount::query()
            ->whereIn('id', $specialDiscountIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (SpecialDiscount $discount) use (&$options) {
                $options[] = [
                    'value' => 'special:'.$discount->id,
                    'label' => $discount->name,
                ];
            });

        return $options;
    }

    /**
     * @return array{key: string, type: string, label: string, period: ?string, amount: float}
     */
    private function resolveSalesSummaryLineDiscount(SellProduct $line): array
    {
        $sell = $line->sell;

        if ($line->promotion_id !== null) {
            $promotion = $line->promotion;

            return [
                'key' => 'promotion:'.$line->promotion_id,
                'type' => 'Promotion',
                'label' => $promotion?->name ?? 'Promotion',
                'period' => $this->formatDiscountPeriod($promotion?->starts_at, $promotion?->ends_at),
                'amount' => round((float) $line->promotion_discount, 2),
            ];
        }

        if ((float) $line->discount > 0) {
            return [
                'key' => 'line',
                'type' => 'Line Discount',
                'label' => 'Line discount',
                'period' => null,
                'amount' => round((float) $line->discount, 2),
            ];
        }

        if ($sell->special_discount_id !== null) {
            return [
                'key' => 'special:'.$sell->special_discount_id,
                'type' => 'Special Discount',
                'label' => $sell->specialDiscount?->name ?? 'Special discount',
                'period' => null,
                'amount' => round((float) $sell->special_discount_amount, 2),
            ];
        }

        if ((float) $sell->discount > 0) {
            return [
                'key' => 'invoice',
                'type' => 'Invoice Discount',
                'label' => 'Invoice discount',
                'period' => null,
                'amount' => round((float) $sell->discount, 2),
            ];
        }

        if ((float) $sell->coin_discount_amount > 0) {
            return [
                'key' => 'coin',
                'type' => 'Coin Discount',
                'label' => 'Coin discount',
                'period' => null,
                'amount' => round((float) $sell->coin_discount_amount, 2),
            ];
        }

        return [
            'key' => 'none',
            'type' => 'Regular',
            'label' => 'Regular',
            'period' => null,
            'amount' => 0.0,
        ];
    }

    private function formatDiscountPeriod(?\DateTimeInterface $startsAt, ?\DateTimeInterface $endsAt): ?string
    {
        $start = $startsAt ? Carbon::parse($startsAt)->format('Y-m-d') : null;
        $end = $endsAt ? Carbon::parse($endsAt)->format('Y-m-d') : null;

        if ($start && $end) {
            return "{$start} – {$end}";
        }

        if ($start) {
            return "From {$start}";
        }

        if ($end) {
            return "Until {$end}";
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function dailySummary(string $date, ?int $filterBranchId = null, ?int $filterUserId = null): array
    {
        $effectiveBranchId = $this->resolveReportBranchFilter($filterBranchId);
        $effectiveUserId = $this->resolveReportUserFilter($filterUserId);

        $salesQuery = Sell::query()->sale()->whereDate('date', $date);
        $purchasesQuery = Purchase::query()->where('purchase_type', PurchaseType::Purchase)->whereDate('date', $date);
        $paymentsQuery = SupplierPayment::query()->whereDate('date', $date);
        $collectionsQuery = CustomerPayment::query()->whereDate('date', $date);
        $returnsQuery = SaleReturn::query()->whereDate('date', $date);
        $purchaseReturnsQuery = PurchaseReturn::query()->whereDate('date', $date);
        $exchangesQuery = ProductExchange::query()->whereDate('date', $date);
        $damagesQuery = Damage::query()->whereDate('date', $date);
        $vouchersQuery = Voucher::query()->whereDate('date', $date);

        if ($effectiveBranchId !== null) {
            $salesQuery->where('branch_id', $effectiveBranchId);
            $purchasesQuery->where('branch_id', $effectiveBranchId);
            $paymentsQuery->where('branch_id', $effectiveBranchId);
            $collectionsQuery->where('branch_id', $effectiveBranchId);
            $returnsQuery->where('branch_id', $effectiveBranchId);
            $purchaseReturnsQuery->where('branch_id', $effectiveBranchId);
            $exchangesQuery->where('branch_id', $effectiveBranchId);
            $damagesQuery->where('branch_id', $effectiveBranchId);
            $vouchersQuery->where('branch_id', $effectiveBranchId);
        }

        $this->applyDailySummaryUserFilter(
            $salesQuery,
            $purchasesQuery,
            $paymentsQuery,
            $collectionsQuery,
            $returnsQuery,
            $purchaseReturnsQuery,
            $exchangesQuery,
            $damagesQuery,
            $vouchersQuery,
            $effectiveUserId,
            $filterUserId,
        );

        $sales = $salesQuery->with(['products:id,sell_id,discount'])->get();
        $purchases = $purchasesQuery->get();
        $returns = $returnsQuery->get();
        $purchaseReturns = $purchaseReturnsQuery->get();
        $exchanges = $exchangesQuery->get();
        $damages = $damagesQuery->with('products')->get();
        $vouchers = $vouchersQuery->get(['type', 'total_amount']);

        $salesNet = $sales->sum(fn (Sell $s) => $s->net_amount);
        $salesPaid = $sales->sum(fn (Sell $s) => (float) $s->paid_amount);
        $purchaseNet = $purchases->sum(fn (Purchase $p) => $p->net_amount);
        $purchasePaid = $purchases->sum(fn (Purchase $p) => (float) $p->paid_amount);
        $expenseVouchers = $vouchers->where('type', VoucherType::Expense);

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
            'customer_collections' => [
                'count' => $collectionsQuery->count(),
                'amount' => round((float) $collectionsQuery->sum('amount'), 2),
            ],
            'expenses' => [
                'count' => $expenseVouchers->count(),
                'amount' => round((float) $expenseVouchers->sum('total_amount'), 2),
            ],
            'sale_returns' => [
                'count' => $returns->count(),
                'amount' => round($returns->sum(fn (SaleReturn $return) => $return->net_amount), 2),
                'paid' => round($returns->sum(fn (SaleReturn $return) => (float) $return->paid_amount), 2),
                'due' => round($returns->sum(fn (SaleReturn $return) => max(0, $return->net_amount - (float) $return->paid_amount)), 2),
            ],
            'purchase_returns' => [
                'count' => $purchaseReturns->count(),
                'amount' => round($purchaseReturns->sum(fn (PurchaseReturn $return) => $return->net_amount), 2),
                'paid' => round($purchaseReturns->sum(fn (PurchaseReturn $return) => (float) $return->paid_amount), 2),
                'due' => round($purchaseReturns->sum(fn (PurchaseReturn $return) => (float) $return->due_amount), 2),
            ],
            'product_exchanges' => [
                'count' => $exchanges->count(),
                'amount' => round($exchanges->sum(fn (ProductExchange $exchange) => (float) $exchange->net_amount), 2),
                'paid' => round($exchanges->sum(fn (ProductExchange $exchange) => (float) $exchange->paid_amount), 2),
                'difference' => round($exchanges->sum(fn (ProductExchange $exchange) => (float) $exchange->price_difference), 2),
            ],
            'damages' => [
                'count' => $damages->count(),
                'amount' => round($damages->sum(fn (Damage $damage) => $this->costService->costForDamage($damage)), 2),
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
                'count' => $this->scopeTransactionForBranch(Transaction::query())
                    ->whereDate('date', $date)
                    ->count(),
            ],
            'staff_breakdown' => $this->shouldShowDailyStaffBreakdown($filterBranchId, $filterUserId)
                ? $this->dailyStaffBreakdown($date, $effectiveBranchId, $effectiveUserId)
                : [],
        ];
    }

    private function shouldShowDailyStaffBreakdown(?int $filterBranchId, ?int $filterUserId): bool
    {
        if (! $this->canFilterByBranch()) {
            return false;
        }

        return $filterBranchId !== null || $filterUserId !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dailyStaffBreakdown(string $date, ?int $branchId, ?int $userId): array
    {
        $salesQuery = Sell::query()
            ->sale()
            ->whereDate('date', $date)
            ->with(['branch:id,name', 'user:id,name', 'products:id,sell_id,discount']);

        $purchasesQuery = Purchase::query()
            ->where('purchase_type', PurchaseType::Purchase)
            ->whereDate('date', $date)
            ->with(['user:id,name']);

        $paymentsQuery = SupplierPayment::query()->whereDate('date', $date);
        $collectionsQuery = CustomerPayment::query()->whereDate('date', $date);
        $saleReturnsQuery = SaleReturn::query()->whereDate('date', $date);
        $exchangesQuery = ProductExchange::query()->whereDate('date', $date);
        $purchaseReturnsQuery = PurchaseReturn::query()->whereDate('date', $date);
        $damagesQuery = Damage::query()->whereDate('date', $date)->with('products');

        if ($branchId !== null) {
            $salesQuery->where('branch_id', $branchId);
            $purchasesQuery->where('branch_id', $branchId);
            $paymentsQuery->where('branch_id', $branchId);
            $collectionsQuery->where('branch_id', $branchId);
            $saleReturnsQuery->where('branch_id', $branchId);
            $exchangesQuery->where('branch_id', $branchId);
            $purchaseReturnsQuery->where('branch_id', $branchId);
            $damagesQuery->where('branch_id', $branchId);
        }

        if ($userId !== null) {
            $salesQuery->where('user_id', $userId);
            $purchasesQuery->where('user_id', $userId);
            $paymentsQuery->where('created_by', $userId);
            $collectionsQuery->where('created_by', $userId);
            $saleReturnsQuery->where('user_id', $userId);
            $exchangesQuery->where('user_id', $userId);
            $purchaseReturnsQuery->where('user_id', $userId);
            $damagesQuery->where('user_id', $userId);
        }

        $salesByStaff = $salesQuery->get()->groupBy(fn (Sell $sell) => "{$sell->branch_id}-{$sell->user_id}");
        $purchasesByStaff = $purchasesQuery->get()->groupBy(fn (Purchase $purchase) => "{$purchase->branch_id}-{$purchase->user_id}");
        $paymentsByStaff = $paymentsQuery->get()->groupBy(fn (SupplierPayment $payment) => "{$payment->branch_id}-{$payment->created_by}");
        $collectionsByStaff = $collectionsQuery->get()->groupBy(fn (CustomerPayment $payment) => "{$payment->branch_id}-{$payment->created_by}");
        $saleReturnsByStaff = $saleReturnsQuery->get()->groupBy(fn (SaleReturn $return) => "{$return->branch_id}-{$return->user_id}");
        $exchangesByStaff = $exchangesQuery->get()->groupBy(fn (ProductExchange $exchange) => "{$exchange->branch_id}-{$exchange->user_id}");
        $purchaseReturnsByStaff = $purchaseReturnsQuery->get()->groupBy(fn (PurchaseReturn $return) => "{$return->branch_id}-{$return->user_id}");
        $damagesByStaff = $damagesQuery->get()->groupBy(fn (Damage $damage) => "{$damage->branch_id}-{$damage->user_id}");
        $staffKeys = $salesByStaff->keys()
            ->merge($purchasesByStaff->keys())
            ->merge($paymentsByStaff->keys())
            ->merge($collectionsByStaff->keys())
            ->merge($saleReturnsByStaff->keys())
            ->merge($exchangesByStaff->keys())
            ->merge($purchaseReturnsByStaff->keys())
            ->merge($damagesByStaff->keys())
            ->unique();

        $branchIds = $staffKeys
            ->map(fn (string $key) => (int) explode('-', $key, 2)[0])
            ->filter()
            ->unique()
            ->values();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $userNames = User::query()
            ->whereIn('id', $staffKeys
                ->map(fn (string $key) => (int) explode('-', $key, 2)[1])
                ->filter()
                ->unique()
                ->values())
            ->pluck('name', 'id');

        return $staffKeys
            ->map(function (string $key) use ($salesByStaff, $purchasesByStaff, $paymentsByStaff, $collectionsByStaff, $saleReturnsByStaff, $exchangesByStaff, $purchaseReturnsByStaff, $damagesByStaff, $branchNames, $userNames) {
                $sales = $salesByStaff->get($key, collect());
                $purchases = $purchasesByStaff->get($key, collect());
                $payments = $paymentsByStaff->get($key, collect());
                $collections = $collectionsByStaff->get($key, collect());
                $saleReturns = $saleReturnsByStaff->get($key, collect());
                $exchanges = $exchangesByStaff->get($key, collect());
                $purchaseReturns = $purchaseReturnsByStaff->get($key, collect());
                $damages = $damagesByStaff->get($key, collect());
                $sample = $sales->first() ?? $purchases->first() ?? $payments->first() ?? $collections->first()
                    ?? $saleReturns->first() ?? $exchanges->first() ?? $purchaseReturns->first() ?? $damages->first();

                if ($sample === null) {
                    return null;
                }

                [$branchId, $staffUserId] = array_pad(explode('-', $key, 2), 2, null);
                $staffUserId = $staffUserId !== null && $staffUserId !== '' ? (int) $staffUserId : null;

                $salesGross = $sales->sum(fn (Sell $sell) => $sell->net_amount);
                $salesPaid = $sales->sum(fn (Sell $sell) => (float) $sell->paid_amount);
                $purchaseGross = $purchases->sum(fn (Purchase $purchase) => $purchase->net_amount);
                $purchasePaid = $purchases->sum(fn (Purchase $purchase) => (float) $purchase->paid_amount);
                $user = $sample instanceof Sell
                    ? $sample->user
                    : ($sample instanceof Purchase ? $sample->user : null);

                return [
                    'branch_id' => (int) $branchId,
                    'branch_name' => $sample instanceof Sell
                        ? ($sample->branch?->name ?? '—')
                        : ($branchNames[(int) $branchId] ?? '—'),
                    'user_id' => $staffUserId ?? $sample->user_id ?? $sample->created_by ?? null,
                    'user_name' => $user?->name ?? ($staffUserId !== null ? ($userNames[$staffUserId] ?? '—') : '—'),
                    'sales' => [
                        'count' => $sales->count(),
                        'gross' => round($salesGross, 2),
                        'paid' => round($salesPaid, 2),
                        'due' => round(max(0, $salesGross - $salesPaid), 2),
                    ],
                    'purchases' => [
                        'count' => $purchases->count(),
                        'gross' => round($purchaseGross, 2),
                        'paid' => round($purchasePaid, 2),
                        'due' => round(max(0, $purchaseGross - $purchasePaid), 2),
                    ],
                    'supplier_payments' => [
                        'count' => $payments->count(),
                        'amount' => round((float) $payments->sum('amount'), 2),
                    ],
                    'customer_collections' => [
                        'count' => $collections->count(),
                        'amount' => round((float) $collections->sum('amount'), 2),
                    ],
                    'sale_returns' => [
                        'count' => $saleReturns->count(),
                        'amount' => round($saleReturns->sum(fn (SaleReturn $return) => $return->net_amount), 2),
                    ],
                    'product_exchanges' => [
                        'count' => $exchanges->count(),
                        'amount' => round($exchanges->sum(fn (ProductExchange $exchange) => (float) $exchange->net_amount), 2),
                    ],
                    'purchase_returns' => [
                        'count' => $purchaseReturns->count(),
                        'amount' => round($purchaseReturns->sum(fn (PurchaseReturn $return) => $return->net_amount), 2),
                    ],
                    'damages' => [
                        'count' => $damages->count(),
                        'amount' => round($damages->sum(fn (Damage $damage) => $this->costService->costForDamage($damage)), 2),
                    ],
                    'sales_items' => $sales
                        ->sortBy('id')
                        ->values()
                        ->map(fn (Sell $sell) => $this->mapSellBreakdownItem($sell))
                        ->all(),
                    'sale_returns_items' => $saleReturns
                        ->sortBy('id')
                        ->values()
                        ->map(fn (SaleReturn $return) => $this->mapSaleReturnBreakdownItem($return))
                        ->all(),
                    'product_exchanges_items' => $exchanges
                        ->sortBy('id')
                        ->values()
                        ->map(fn (ProductExchange $exchange) => $this->mapProductExchangeBreakdownItem($exchange))
                        ->all(),
                    'purchases_items' => $purchases
                        ->sortBy('id')
                        ->values()
                        ->map(fn (Purchase $purchase) => $this->mapPurchaseBreakdownItem($purchase))
                        ->all(),
                    'purchase_returns_items' => $purchaseReturns
                        ->sortBy('id')
                        ->values()
                        ->map(fn (PurchaseReturn $return) => $this->mapPurchaseReturnBreakdownItem($return))
                        ->all(),
                    'damages_items' => $damages
                        ->sortBy('id')
                        ->values()
                        ->map(fn (Damage $damage) => $this->mapDamageBreakdownItem($damage))
                        ->all(),
                ];
            })
            ->filter(function (?array $row): bool {
                if ($row === null) {
                    return false;
                }

                return ($row['sales']['count'] ?? 0) > 0
                    || ($row['purchases']['count'] ?? 0) > 0
                    || ($row['supplier_payments']['count'] ?? 0) > 0
                    || ($row['customer_collections']['count'] ?? 0) > 0
                    || ($row['sale_returns']['count'] ?? 0) > 0
                    || ($row['product_exchanges']['count'] ?? 0) > 0
                    || ($row['purchase_returns']['count'] ?? 0) > 0
                    || ($row['damages']['count'] ?? 0) > 0;
            })
            ->sortBy([
                ['branch_name', 'asc'],
                ['user_name', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function applyDailySummaryUserFilter(
        Builder $salesQuery,
        Builder $purchasesQuery,
        Builder $paymentsQuery,
        Builder $collectionsQuery,
        Builder $returnsQuery,
        Builder $purchaseReturnsQuery,
        Builder $exchangesQuery,
        Builder $damagesQuery,
        Builder $vouchersQuery,
        ?int $userId,
        ?int $paymentsUserId = null,
    ): void {
        if ($userId !== null) {
            $salesQuery->where('user_id', $userId);
            $purchasesQuery->where('user_id', $userId);
            $returnsQuery->where('user_id', $userId);
            $purchaseReturnsQuery->where('user_id', $userId);
            $exchangesQuery->where('user_id', $userId);
            $damagesQuery->where('user_id', $userId);
            $vouchersQuery->where('created_by', $userId);
        }

        if ($paymentsUserId !== null) {
            $paymentsQuery->where('created_by', $paymentsUserId);
            $collectionsQuery->where('created_by', $paymentsUserId);
        }
    }

    /**
     * @return array{id: int, reference: string, gross: float, paid: float, due: float}
     */
    private function mapSellBreakdownItem(Sell $sell): array
    {
        $gross = $sell->net_amount;
        $paid = (float) $sell->paid_amount;

        return [
            'id' => $sell->id,
            'reference' => $sell->invoice_number,
            'gross' => round($gross, 2),
            'paid' => round($paid, 2),
            'due' => round(max(0, $gross - $paid), 2),
        ];
    }

    /**
     * @return array{id: int, reference: string, gross: float, paid: float, due: float}
     */
    private function mapPurchaseBreakdownItem(Purchase $purchase): array
    {
        $gross = $purchase->net_amount;
        $paid = (float) $purchase->paid_amount;

        return [
            'id' => $purchase->id,
            'reference' => $purchase->invoice_number,
            'gross' => round($gross, 2),
            'paid' => round($paid, 2),
            'due' => round(max(0, $gross - $paid), 2),
        ];
    }

    /**
     * @return array{id: int, reference: string, gross: float, paid: float, due: float}
     */
    private function mapSaleReturnBreakdownItem(SaleReturn $return): array
    {
        $gross = $return->net_amount;
        $paid = (float) $return->paid_amount;

        return [
            'id' => $return->id,
            'reference' => $return->invoice_number,
            'gross' => round($gross, 2),
            'paid' => round($paid, 2),
            'due' => round(max(0, $gross - $paid), 2),
        ];
    }

    /**
     * @return array{id: int, reference: string, gross: float, paid: float, due: float}
     */
    private function mapProductExchangeBreakdownItem(ProductExchange $exchange): array
    {
        $gross = (float) $exchange->net_amount;
        $paid = (float) $exchange->paid_amount;

        return [
            'id' => $exchange->id,
            'reference' => $exchange->invoice_number,
            'gross' => round($gross, 2),
            'paid' => round($paid, 2),
            'due' => round(max(0, $gross - $paid), 2),
        ];
    }

    /**
     * @return array{id: int, reference: string, gross: float, paid: float, due: float}
     */
    private function mapPurchaseReturnBreakdownItem(PurchaseReturn $return): array
    {
        $gross = $return->net_amount;
        $paid = (float) $return->paid_amount;

        return [
            'id' => $return->id,
            'reference' => $return->invoice_number,
            'gross' => round($gross, 2),
            'paid' => round($paid, 2),
            'due' => round((float) $return->due_amount, 2),
        ];
    }

    /**
     * @return array{id: int, reference: string, gross: float, paid: float, due: float}
     */
    private function mapDamageBreakdownItem(Damage $damage): array
    {
        $amount = $this->costService->costForDamage($damage);

        return [
            'id' => $damage->id,
            'reference' => $damage->invoice_number,
            'gross' => round($amount, 2),
            'paid' => 0,
            'due' => 0,
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
        $account = ChartOfAccount::query()->forPanel()->find($accountId);

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

        $ledgers = $this->scopeLedgerForBranch(Ledger::query())
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
            $transactionIds = $this->scopeLedgerForBranch(Ledger::query())
                ->where('account_id', $accountId)
                ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('date', '>=', $d))
                ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('date', '<=', $d))
                ->pluck('transaction_id')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $this->scopeTransactionForBranch(Transaction::query())
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
                $lines = $this->scopeLedgerForBranch(Ledger::query())
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
            ->forPanel()
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
            ->forPanel()
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
            ->paymentAccount()
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
        $ledger = $this->scopeLedgerForBranch(Ledger::query())
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
            ->ownBranch()
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
     * @return array{id: int, label: string}|null
     */
    public function selectedProductOption(?int $productId, ?int $filterBranchId = null): ?array
    {
        if ($productId === null) {
            return null;
        }

        $effectiveBranchId = $this->resolveReportBranchFilter($filterBranchId);

        $product = Product::query()
            ->when($effectiveBranchId !== null, fn (Builder $q) => $q->where('branch_id', $effectiveBranchId))
            ->whereKey($productId)
            ->first(['id', 'name', 'code']);

        if ($product === null) {
            $product = Product::query()
                ->whereKey($productId)
                ->first(['id', 'name', 'code']);
        }

        if ($product === null) {
            return null;
        }

        return [
            'id' => $product->id,
            'label' => $this->productSearchLabel($product),
        ];
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function searchProductOptions(
        ?string $search = null,
        ?int $selectedId = null,
        ?int $filterBranchId = null,
    ): array {
        $effectiveBranchId = $this->resolveReportBranchFilter($filterBranchId);

        $products = Product::query()
            ->when($effectiveBranchId !== null, fn (Builder $q) => $q->where('branch_id', $effectiveBranchId))
            ->when($search, fn (Builder $q, string $term) => $q->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit($effectiveBranchId !== null ? 20 : 120)
            ->get(['id', 'name', 'code', 'product_group_id']);

        $products = $this->deduplicateReportProducts($products);

        if ($selectedId !== null && ! $products->contains('id', $selectedId)) {
            $selected = Product::query()
                ->when($effectiveBranchId !== null, fn (Builder $q) => $q->where('branch_id', $effectiveBranchId))
                ->whereKey($selectedId)
                ->first(['id', 'name', 'code', 'product_group_id']);

            if ($selected !== null) {
                $products->prepend($selected);
                $products = $this->deduplicateReportProducts($products);
            }
        }

        return $products
            ->take(20)
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $this->productSearchLabel($product),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>|null
     */
    public function resolveReportProductIds(?int $productId, ?int $filterBranchId = null): ?array
    {
        if ($productId === null) {
            return null;
        }

        $effectiveBranchId = $this->resolveReportBranchFilter($filterBranchId);

        $product = Product::query()
            ->when($effectiveBranchId !== null, fn (Builder $q) => $q->where('branch_id', $effectiveBranchId))
            ->whereKey($productId)
            ->first(['id', 'product_group_id', 'name', 'code']);

        if ($product === null) {
            return null;
        }

        if ($effectiveBranchId !== null) {
            return [$product->id];
        }

        if ($product->product_group_id !== null) {
            return Product::query()
                ->where('product_group_id', $product->product_group_id)
                ->pluck('id')
                ->map(fn (int|string $id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return Product::query()
            ->where('name', $product->name)
            ->where('code', $product->code)
            ->pluck('id')
            ->map(fn (int|string $id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function deduplicateReportProducts(Collection $products): Collection
    {
        $seenGroups = [];
        $seenKeys = [];
        $deduped = collect();

        foreach ($products as $product) {
            if ($product->product_group_id !== null) {
                if (isset($seenGroups[$product->product_group_id])) {
                    continue;
                }

                $seenGroups[$product->product_group_id] = true;
                $deduped->push($product);

                continue;
            }

            $key = mb_strtolower(trim($product->name)).'|'.mb_strtolower(trim((string) ($product->code ?? '')));

            if (isset($seenKeys[$key])) {
                continue;
            }

            $seenKeys[$key] = true;
            $deduped->push($product);
        }

        return $deduped;
    }

    private function productSearchLabel(Product $product): string
    {
        return $product->code
            ? "{$product->name} ({$product->code})"
            : $product->name;
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
            ->paymentAccount()
            ->pluck('id')
            ->all();
    }

    public function canFilterByBranch(): bool
    {
        $branchId = $this->branchId();

        return $branchId === null || Branch::isMainBranch($branchId);
    }

    private function resolveReportBranchFilter(?int $filterBranchId = null): ?int
    {
        if ($this->canFilterByBranch()) {
            return $filterBranchId;
        }

        return $this->branchId();
    }

    private function resolveReportUserFilter(?int $filterUserId = null): ?int
    {
        if ($this->canFilterByBranch()) {
            return $filterUserId;
        }

        return null;
    }

    private function branchId(): ?int
    {
        return Auth::user()?->branch_id;
    }

    /**
     * Branch users only see ledger rows tied to their branch vouchers or inventory documents.
     * Super admins (no branch) see all branches.
     */
    private function scopeLedgerForBranch(Builder $query): Builder
    {
        $branchId = $this->branchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($branchId) {
            $q->where(function (Builder $inner) use ($branchId) {
                $inner->where('source_type', Voucher::class)
                    ->whereIn(
                        'source_id',
                        Voucher::query()->where('branch_id', $branchId)->select('id'),
                    );
            });

            foreach ($this->branchScopedSourceMap() as $sourceType => $modelClass) {
                $q->orWhere(function (Builder $inner) use ($branchId, $sourceType, $modelClass) {
                    $inner->where('source_type', $sourceType)
                        ->whereIn(
                            'source_id',
                            $modelClass::query()->where('branch_id', $branchId)->select('id'),
                        );
                });
            }

            $q->orWhere(function (Builder $inner) use ($branchId) {
                $inner->where('source_type', StockDistribution::class)
                    ->whereIn(
                        'source_id',
                        StockDistribution::query()->where('to_branch_id', $branchId)->select('id'),
                    );
            });
        });
    }

    /**
     * Branch users only see accounting transactions from their branch vouchers or inventory documents.
     */
    private function scopeTransactionForBranch(Builder $query): Builder
    {
        $branchId = $this->branchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($branchId) {
            $q->where(function (Builder $inner) use ($branchId) {
                $inner->where('source_type', Voucher::class)
                    ->whereIn(
                        'source_id',
                        Voucher::query()->where('branch_id', $branchId)->select('id'),
                    );
            });

            foreach ($this->branchScopedSourceMap() as $sourceType => $modelClass) {
                $q->orWhere(function (Builder $inner) use ($branchId, $sourceType, $modelClass) {
                    $inner->where('source_type', $sourceType)
                        ->whereIn(
                            'source_id',
                            $modelClass::query()->where('branch_id', $branchId)->select('id'),
                        );
                });
            }

            $q->orWhere(function (Builder $inner) use ($branchId) {
                $inner->where('source_type', StockDistribution::class)
                    ->whereIn(
                        'source_id',
                        StockDistribution::query()->where('to_branch_id', $branchId)->select('id'),
                    );
            });
        });
    }

    /**
     * @return array<class-string, class-string<Model>>
     */
    private function branchScopedSourceMap(): array
    {
        return [
            Purchase::class => Purchase::class,
            Sell::class => Sell::class,
            SaleReturn::class => SaleReturn::class,
            Damage::class => Damage::class,
            StockDistribution::class => StockDistribution::class,
            SupplierPayment::class => SupplierPayment::class,
            CustomerPayment::class => CustomerPayment::class,
            ProductExchange::class => ProductExchange::class,
            Supplier::class => Supplier::class,
            Customer::class => Customer::class,
        ];
    }

    private function stockLedgerLogQuery(?int $branchId, ?int $productId, ?string $dateFrom, ?string $dateTo): Builder
    {
        $allowedTypes = $this->stockLedgerAllowedTypes();

        return ProductInOutLog::query()
            ->when($branchId !== null, fn (Builder $q) => $this->scopeProductInOutLogForBranch($q, $branchId))
            ->when($productId !== null, fn (Builder $q) => $q->where('product_id', $productId))
            ->when($dateFrom, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
            ->when($dateTo, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))
            ->whereIn('type', array_map(fn ($t) => $t->value, $allowedTypes));
    }

    /** @return list<ProductLogType> */
    private function stockLedgerAllowedTypes(): array
    {
        if ($this->branchId() === null) {
            return [
                ProductLogType::Purchase,
                ProductLogType::InitialStock,
                ProductLogType::Purchase_Return,
                ProductLogType::Distribution_Out,
            ];
        }

        return [
            ProductLogType::Distribution_In,
            ProductLogType::Sale,
            ProductLogType::Sale_Return,
            ProductLogType::Damage,
            ProductLogType::Exchange,
        ];
    }

    private function productCurrentStock(int $productId, ?int $branchId): float
    {
        $query = Batch::query()->where('product_id', $productId);

        if ($branchId === null) {
            return (float) $query->sum('available');
        }

        return (float) $this->scopeBatchForBranch($query, $branchId)->sum('available');
    }

    private function scopeBatchForBranch(Builder $query, int $branchId): Builder
    {
        if (Branch::isMainBranch($branchId)) {
            return $query->where(function (Builder $q) {
                $q->where('branch_id', Branch::MAIN_BRANCH_ID)
                    ->orWhereNull('branch_id');
            });
        }

        return $query->where(function (Builder $q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereNull('branch_id');
        });
    }

    private function productStockBalanceBeforeAllBranches(int $productId, string $dateFrom): float
    {
        $balance = 0.0;
        $allowedTypes = array_map(fn ($t) => $t->value, $this->stockLedgerAllowedTypes());

        ProductInOutLog::query()
            ->where('product_id', $productId)
            ->whereDate('created_at', '<', $dateFrom)
            ->whereIn('type', $allowedTypes)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['type', 'quantity'])
            ->each(function (ProductInOutLog $log) use (&$balance) {
                $quantity = (float) $log->quantity;

                if ($this->isStockInMovement($log->type)) {
                    $balance += $quantity;
                } else {
                    $balance -= $quantity;
                }
            });

        return $balance;
    }

    private function productStockBalanceBefore(int $productId, int $branchId, string $dateFrom): float
    {
        $balance = 0.0;
        $allowedTypes = array_map(fn ($t) => $t->value, $this->stockLedgerAllowedTypes());

        $this->scopeProductInOutLogForBranch(ProductInOutLog::query(), $branchId)
            ->where('product_id', $productId)
            ->whereDate('created_at', '<', $dateFrom)
            ->whereIn('type', $allowedTypes)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['type', 'quantity'])
            ->each(function (ProductInOutLog $log) use (&$balance) {
                $quantity = (float) $log->quantity;

                if ($this->isStockInMovement($log->type)) {
                    $balance += $quantity;
                } else {
                    $balance -= $quantity;
                }
            });

        return $balance;
    }

    private function scopeProductInOutLogForBranch(Builder $query, int $branchId): Builder
    {
        if (Branch::isMainBranch($branchId)) {
            return $query->where(function (Builder $q) {
                $q->where('branch_id', Branch::MAIN_BRANCH_ID)
                    ->orWhereNull('branch_id');
            });
        }

        return $query->where('branch_id', $branchId);
    }

    private function isStockInMovement(int $type): bool
    {
        try {
            $movement = ProductLogType::from($type);
        } catch (\ValueError) {
            return false;
        }

        return match ($movement) {
            ProductLogType::Purchase,
            ProductLogType::Sale_Return,
            ProductLogType::InitialStock,
            ProductLogType::Distribution_In => true,
            ProductLogType::Sale,
            ProductLogType::Damage,
            ProductLogType::Purchase_Return,
            ProductLogType::Exchange,
            ProductLogType::Distribution_Out => false,
        };
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
