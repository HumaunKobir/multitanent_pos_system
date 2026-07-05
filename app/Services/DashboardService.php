<?php

namespace App\Services;

use App\Enums\DashboardSalesPeriod;
use App\Enums\PurchaseType;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\ConfigDictionary;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Support\StorageUrl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    private const string NET_AMOUNT_SQL = '(gross_amount + vat - discount)';

    /**
     * @return array<string, mixed>
     */
    public function sellReport(
        DashboardSalesPeriod $period,
        ?int $branchId = null,
        ?string $customDateFrom = null,
        ?string $customDateTo = null,
    ): array {
        if ($period === DashboardSalesPeriod::CustomRange) {
            if (blank($customDateFrom) || blank($customDateTo)) {
                $emptySummary = ['count' => 0, 'gross' => 0.0, 'paid' => 0.0, 'due' => 0.0];

                return [
                    'period' => $period->value,
                    'label' => $period->label(),
                    'date_from' => null,
                    'date_to' => null,
                    'summary' => $emptySummary,
                    'collection' => $this->collectionMetrics($emptySummary),
                    'branch_breakdown' => [],
                    'periods' => DashboardSalesPeriod::options(),
                ];
            }

            $from = Carbon::parse($customDateFrom)->startOfDay();
            $to = Carbon::parse($customDateTo)->startOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
        } else {
            $range = $period->dateRange();
            $from = $range['from'];
            $to = $range['to'];
        }

        $salesQuery = Sell::query()->sale()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

        if ($branchId !== null) {
            $salesQuery->where('branch_id', $branchId);
        } else {
            $salesQuery = $this->excludeMainBranchSales($salesQuery);
        }

        $summary = $this->aggregateSales($salesQuery);

        return [
            'period' => $period->value,
            'label' => $period->label(),
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'summary' => $summary,
            'collection' => $this->collectionMetrics($summary),
            'branch_breakdown' => $branchId === null
                ? $this->branchSalesBreakdownForPeriod($from, $to)
                : [],
            'periods' => DashboardSalesPeriod::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminOverview(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $trendStart = $today->copy()->subDays(29);

        $todaySales = $this->aggregateSales(
            $this->excludeMainBranchSales(
                Sell::query()->sale()->whereDate('date', $today),
            ),
        );

        $monthSales = $this->aggregateSales(
            $this->excludeMainBranchSales(
                Sell::query()->sale()->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
            ),
        );

        $todayExpenses = $this->aggregateExpenses(
            $this->excludeMainBranch(
                Voucher::query()->where('type', VoucherType::Expense)->whereDate('date', $today),
            ),
        );

        $monthExpenses = $this->aggregateExpenses(
            $this->excludeMainBranch(
                Voucher::query()->where('type', VoucherType::Expense)->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
            ),
        );

        return [
            'today' => $today->format('Y-m-d'),
            'kpis' => [
                'today_sales' => $todaySales,
                'month_sales' => $monthSales,
                'today_expenses' => $todayExpenses,
                'month_expenses' => $monthExpenses,
                'active_branches' => Branch::query()->operating()->active()->count(),
                'total_branches' => Branch::query()->operating()->count(),
            ],
            'branch_sales' => $this->branchSalesBreakdown($today, $monthStart),
            'sales_trend' => $this->salesTrend($trendStart, $today),
            'collection' => $this->collectionMetrics($monthSales),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function welcomeOverview(User $user): array
    {
        $branch = $user->branch;

        if ($user->usesBranchPanel()) {
            $branchName = $branch?->name ?? 'Branch';
            $logoPath = $branch?->logo;
        } else {
            $branchName = $branch?->name ?? config('app.name');
            $logoPath = $branch?->logo ?? ConfigDictionary::get('logo');
        }

        return [
            'today' => Carbon::today()->format('Y-m-d'),
            'limitedAccess' => true,
            'userName' => $user->name,
            'branchName' => $branchName,
            'branchLogoUrl' => StorageUrl::public($logoPath),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function branchOverview(
        User $user,
        DashboardSalesPeriod $period = DashboardSalesPeriod::CurrentMonth,
        ?string $customDateFrom = null,
        ?string $customDateTo = null,
    ): array {
        $branchId = $user->branch_id;

        if ($branchId === null) {
            return [
                'today' => Carbon::today()->format('Y-m-d'),
                'branch_name' => '',
                'sections' => [],
            ];
        }

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $trendStart = $today->copy()->subDays(29);
        $sections = [];

        if ($user->can('inventory.sell.view')) {
            $todaySales = $this->aggregateSales(
                Sell::query()->sale()->where('branch_id', $branchId)->whereDate('date', $today),
            );
            $monthSales = $this->aggregateSales(
                Sell::query()->sale()->where('branch_id', $branchId)->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
            );

            $sections['sales'] = [
                'today' => $todaySales,
                'month' => $monthSales,
                'trend' => $this->salesTrend($trendStart, $today, $branchId),
                'collection' => $this->collectionMetrics($monthSales),
                'report' => $this->sellReport($period, $branchId, $customDateFrom, $customDateTo),
            ];
        }

        if ($user->can('inventory.purchase.view')) {
            $sections['purchases'] = [
                'today' => $this->aggregatePurchases(
                    Purchase::query()->where('purchase_type', PurchaseType::Purchase)->where('branch_id', $branchId)->whereDate('date', $today),
                ),
                'month' => $this->aggregatePurchases(
                    Purchase::query()->where('purchase_type', PurchaseType::Purchase)->where('branch_id', $branchId)->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
                ),
            ];
        }

        if ($user->can('inventory.sale-return.view')) {
            $todayReturns = SaleReturn::query()->where('branch_id', $branchId)->whereDate('date', $today);
            $sections['sale_returns'] = [
                'today_count' => (clone $todayReturns)->count(),
                'today_amount' => round((float) (clone $todayReturns)->sum('gross_amount'), 2),
            ];
        }

        if ($user->can('party.supplier-payment.view')) {
            $sections['supplier_payments'] = [
                'month_amount' => round((float) SupplierPayment::query()
                    ->where('branch_id', $branchId)
                    ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])
                    ->sum('amount'), 2),
            ];
        }

        if ($user->can('party.customer.view')) {
            $customers = Customer::query()->where('branch_id', $branchId);
            $sections['customers'] = [
                'count' => (clone $customers)->count(),
                'total_due' => round((float) (clone $customers)->where('balance', '>', 0)->sum('balance'), 2),
            ];
        }

        if ($user->can('party.customer-due-collection.view')) {
            $sections['customer_collections'] = [
                'month_amount' => round((float) CustomerPayment::query()
                    ->where('branch_id', $branchId)
                    ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()])
                    ->sum('amount'), 2),
            ];
        }

        if ($user->can('accounts.view')) {
            $sections['expenses'] = [
                'today' => $this->aggregateExpenses(
                    Voucher::query()
                        ->where('type', VoucherType::Expense)
                        ->where('branch_id', $branchId)
                        ->whereDate('date', $today),
                ),
                'month' => $this->aggregateExpenses(
                    Voucher::query()
                        ->where('type', VoucherType::Expense)
                        ->where('branch_id', $branchId)
                        ->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
                ),
            ];
        }

        if ($user->can('report.daily-summary.view')) {
            $sections['reports'] = [
                'daily_summary_url' => '/report/daily-summary',
            ];
        }

        return [
            'today' => $today->format('Y-m-d'),
            'branch_name' => $user->branch?->name ?? '',
            'sections' => $sections,
        ];
    }

    /**
     * @return array{count: int, gross: float, paid: float, due: float}
     */
    private function aggregateSales(Builder $query): array
    {
        $netSql = self::NET_AMOUNT_SQL;
        $row = (clone $query)->selectRaw("
            COUNT(*) as invoice_count,
            COALESCE(SUM({$netSql}), 0) as gross_total,
            COALESCE(SUM(paid_amount), 0) as paid_total
        ")->first();

        $gross = round((float) $row->gross_total, 2);
        $paid = round((float) $row->paid_total, 2);

        return [
            'count' => (int) $row->invoice_count,
            'gross' => $gross,
            'paid' => $paid,
            'due' => round(max(0, $gross - $paid), 2),
        ];
    }

    /**
     * @return array{count: int, amount: float}
     */
    private function aggregateExpenses(Builder $query): array
    {
        $row = (clone $query)->selectRaw('
            COUNT(*) as voucher_count,
            COALESCE(SUM(total_amount), 0) as amount_total
        ')->first();

        return [
            'count' => (int) $row->voucher_count,
            'amount' => round((float) $row->amount_total, 2),
        ];
    }

    /**
     * @return array{count: int, gross: float, paid: float, due: float}
     */
    private function aggregatePurchases(Builder $query): array
    {
        $netSql = self::NET_AMOUNT_SQL;
        $row = (clone $query)->selectRaw("
            COUNT(*) as order_count,
            COALESCE(SUM({$netSql}), 0) as gross_total,
            COALESCE(SUM(paid_amount), 0) as paid_total
        ")->first();

        $gross = round((float) $row->gross_total, 2);
        $paid = round((float) $row->paid_total, 2);

        return [
            'count' => (int) $row->order_count,
            'gross' => $gross,
            'paid' => $paid,
            'due' => round(max(0, $gross - $paid), 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function branchSalesBreakdown(Carbon $today, Carbon $monthStart): array
    {
        $netSql = self::NET_AMOUNT_SQL;
        $branches = Branch::query()->operating()->orderBy('name')->get(['id', 'name']);

        $todayByBranch = $this->excludeMainBranchSales(
            Sell::query()->sale()->whereDate('date', $today),
        )
            ->selectRaw("branch_id, COALESCE(SUM({$netSql}), 0) as gross_total, COALESCE(SUM(paid_amount), 0) as paid_total")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $monthByBranch = $this->excludeMainBranchSales(
            Sell::query()->sale()->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
        )
            ->selectRaw("branch_id, COALESCE(SUM({$netSql}), 0) as gross_total, COALESCE(SUM(paid_amount), 0) as paid_total")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        return $branches->map(function (Branch $branch) use ($todayByBranch, $monthByBranch) {
            $todayRow = $todayByBranch->get($branch->id);
            $monthRow = $monthByBranch->get($branch->id);
            $monthGross = round((float) ($monthRow->gross_total ?? 0), 2);
            $monthPaid = round((float) ($monthRow->paid_total ?? 0), 2);
            $todayGross = round((float) ($todayRow->gross_total ?? 0), 2);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'today_gross' => $todayGross,
                'month_gross' => $monthGross,
                'month_paid' => $monthPaid,
                'month_due' => round(max(0, $monthGross - $monthPaid), 2),
                'collection_rate' => $monthGross > 0 ? round($monthPaid / $monthGross * 100, 1) : 0.0,
            ];
        })
            ->filter(fn (array $row): bool => $row['today_gross'] > 0 || $row['month_gross'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function branchSalesBreakdownForPeriod(Carbon $from, Carbon $to): array
    {
        $netSql = self::NET_AMOUNT_SQL;
        $branches = Branch::query()->operating()->orderBy('name')->get(['id', 'name']);

        $byBranch = $this->excludeMainBranchSales(
            Sell::query()->sale()->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
        )
            ->selectRaw("branch_id, COUNT(*) as invoice_count, COALESCE(SUM({$netSql}), 0) as gross_total, COALESCE(SUM(paid_amount), 0) as paid_total")
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        return $branches->map(function (Branch $branch) use ($byBranch) {
            $row = $byBranch->get($branch->id);
            $gross = round((float) ($row->gross_total ?? 0), 2);
            $paid = round((float) ($row->paid_total ?? 0), 2);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'invoice_count' => (int) ($row->invoice_count ?? 0),
                'gross' => $gross,
                'paid' => $paid,
                'due' => round(max(0, $gross - $paid), 2),
                'collection_rate' => $gross > 0 ? round($paid / $gross * 100, 1) : 0.0,
            ];
        })
            ->filter(fn (array $row): bool => $row['gross'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array{date: string, gross: float, paid: float, count: int}>
     */
    private function salesTrend(Carbon $from, Carbon $to, ?int $branchId = null): array
    {
        $netSql = self::NET_AMOUNT_SQL;

        $rows = Sell::query()->sale()
            ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
            ->when($branchId === null, fn (Builder $q) => $q->where('branch_id', '!=', Branch::MAIN_BRANCH_ID))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("date, COUNT(*) as invoice_count, COALESCE(SUM({$netSql}), 0) as gross_total, COALESCE(SUM(paid_amount), 0) as paid_total")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->format('Y-m-d'));

        $result = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $row = $rows->get($key);

            $result[] = [
                'date' => $key,
                'gross' => round((float) ($row->gross_total ?? 0), 2),
                'paid' => round((float) ($row->paid_total ?? 0), 2),
                'count' => (int) ($row->invoice_count ?? 0),
            ];

            $cursor->addDay();
        }

        return $result;
    }

    private function excludeMainBranchSales(Builder $query): Builder
    {
        return $query->where('branch_id', '!=', Branch::MAIN_BRANCH_ID);
    }

    private function excludeMainBranch(Builder $query): Builder
    {
        return $query->where('branch_id', '!=', Branch::MAIN_BRANCH_ID);
    }

    /**
     * @param  array{gross: float, paid: float, due: float}  $sales
     * @return array{gross: float, paid: float, due: float, rate: float}
     */
    private function collectionMetrics(array $sales): array
    {
        $gross = $sales['gross'];
        $paid = $sales['paid'];

        return [
            'gross' => $gross,
            'paid' => $paid,
            'due' => $sales['due'],
            'rate' => $gross > 0 ? round($paid / $gross * 100, 1) : 0.0,
        ];
    }
}
