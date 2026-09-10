<?php

namespace App\Services;

use App\Enums\DashboardSalesPeriod;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\ConfigDictionary;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Purchase;
use App\Models\SaleReturn;
use App\Models\Sell;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Voucher;
use App\Support\BusinessSettings;
use App\Support\StorageUrl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class DashboardService
{
    private const string NET_AMOUNT_SQL = '(gross_amount + vat - discount)';

    public function __construct(
        private SellExchangeOverlayService $exchangeOverlay,
    ) {}

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
                $emptySummary = ['count' => 0, 'gross' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'refund_due' => 0.0];

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
        return $this->saasOverview();
    }

    /**
     * SuperAdmin SaaS platform overview (subscription clients & billing).
     *
     * @return array<string, mixed>
     */
    public function saasOverview(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $clientBranches = Branch::query()
            ->operating()
            ->with(['subscriptionPayments' => fn ($q) => $q->latest('id')->limit(1)])
            ->orderBy('name')
            ->get();

        $subscriptionService = app(BranchSubscriptionService::class);
        $rows = $clientBranches->map(function (Branch $branch) use ($subscriptionService) {
            $sub = $subscriptionService->getSubscriptionSummary($branch);
            $latest = $branch->subscriptionPayments->first();

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'subscription' => $sub,
                'latest_payment_status' => $latest?->status,
                'fee' => (float) ($branch->subscription_fee ?? $sub['fee'] ?? 0),
            ];
        });

        $pendingApprovals = $rows->filter(
            fn (array $row) => ($row['subscription']['has_pending_payment'] ?? false)
                || ($row['latest_payment_status'] ?? '') === 'pending'
        )->count();

        $activeClients = $rows->filter(fn (array $row) => ($row['subscription']['computed_status'] ?? '') === 'active')->count();
        $expiringSoon = $rows->filter(fn (array $row) => ($row['subscription']['computed_status'] ?? '') === 'expiring_soon')->count();
        $overdueClients = $rows->filter(fn (array $row) => (bool) ($row['subscription']['is_overdue'] ?? false))->count();
        $suspendedClients = $rows->filter(fn (array $row) => (bool) ($row['subscription']['is_suspended'] ?? false))->count();
        $lifetimeClients = $rows->filter(fn (array $row) => ($row['subscription']['status'] ?? '') === 'lifetime')->count();
        $totalOverdueDue = round($rows->sum(fn (array $row) => (float) ($row['subscription']['total_overdue_fee'] ?? 0)), 2);

        $monthCollected = (float) BranchSubscriptionPayment::query()
            ->where('status', 'approved')
            ->whereDate('paid_at', '>=', $monthStart->toDateString())
            ->whereDate('paid_at', '<=', $today->toDateString())
            ->sum('amount');

        $todayCollected = (float) BranchSubscriptionPayment::query()
            ->where('status', 'approved')
            ->whereDate('paid_at', $today->toDateString())
            ->sum('amount');

        $pendingAmount = (float) BranchSubscriptionPayment::query()
            ->where('status', 'pending')
            ->sum('amount');

        $recentPending = BranchSubscriptionPayment::query()
            ->with('branch:id,name')
            ->where('status', 'pending')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'branch_id', 'amount', 'payment_method', 'paid_at', 'created_at'])
            ->map(fn (BranchSubscriptionPayment $payment) => [
                'id' => $payment->id,
                'branch_name' => $payment->branch?->name ?? 'Branch #'.$payment->branch_id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'submitted' => $payment->created_at?->diffForHumans(),
            ])
            ->all();

        $statusBreakdown = [
            ['label' => 'Active', 'count' => $activeClients, 'tone' => 'emerald'],
            ['label' => 'Expiring soon', 'count' => $expiringSoon, 'tone' => 'amber'],
            ['label' => 'Overdue', 'count' => $overdueClients, 'tone' => 'rose'],
            ['label' => 'Suspended', 'count' => $suspendedClients, 'tone' => 'slate'],
            ['label' => 'Lifetime', 'count' => $lifetimeClients, 'tone' => 'blue'],
            ['label' => 'Pending approval', 'count' => $pendingApprovals, 'tone' => 'violet'],
        ];

        return [
            'today' => $today->format('Y-m-d'),
            'kpis' => [
                'total_clients' => $rows->count(),
                'active_clients' => $activeClients,
                'pending_approvals' => $pendingApprovals,
                'expiring_soon' => $expiringSoon,
                'overdue_clients' => $overdueClients,
                'suspended_clients' => $suspendedClients,
                'lifetime_clients' => $lifetimeClients,
                'total_overdue_due' => $totalOverdueDue,
                'today_collected' => round($todayCollected, 2),
                'month_collected' => round($monthCollected, 2),
                'pending_amount' => round($pendingAmount, 2),
                'multi_tenant_enabled' => BusinessSettings::getBool('multi_tenant_enabled', (bool) config('tenancy.enabled')),
                'system_name' => BusinessSettings::systemName(),
            ],
            'status_breakdown' => $statusBreakdown,
            'recent_pending' => $recentPending,
            'links' => [
                'clients' => '/branch-clients',
                'billing_report' => '/report/subscription-billing',
                'profit_loss' => '/report/profit-loss',
                'business_setup' => '/setting/business-setup',
            ],
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
                    Purchase::query()->purchaseOrInitialStock()->where('branch_id', $branchId)->whereDate('date', $today),
                ),
                'month' => $this->aggregatePurchases(
                    Purchase::query()->purchaseOrInitialStock()->where('branch_id', $branchId)->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
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
     * @return array{count: int, gross: float, paid: float, due: float, refund_due: float}
     */
    private function aggregateSales(Builder $query): array
    {
        $sales = $this->loadSalesForOverlay($query);

        return [
            'count' => $sales->count(),
            'gross' => $this->exchangeOverlay->sumEffectiveNet($sales),
            'paid' => $this->exchangeOverlay->sumEffectivePaid($sales),
            'due' => $this->exchangeOverlay->sumEffectiveDue($sales),
            'refund_due' => $this->exchangeOverlay->sumEffectiveRefundDue($sales),
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
        $branches = Branch::query()->operating()->orderBy('name')->get(['id', 'name']);

        $todaySales = $this->loadSalesForOverlay(
            $this->excludeMainBranchSales(
                Sell::query()->sale()->whereDate('date', $today),
            ),
        );

        $monthSales = $this->loadSalesForOverlay(
            $this->excludeMainBranchSales(
                Sell::query()->sale()->whereBetween('date', [$monthStart->toDateString(), $today->toDateString()]),
            ),
        );

        $todayByBranch = $todaySales->groupBy('branch_id');
        $monthByBranch = $monthSales->groupBy('branch_id');

        return $branches->map(function (Branch $branch) use ($todayByBranch, $monthByBranch) {
            $todayGroup = collect($todayByBranch->get($branch->id, []));
            $monthGroup = collect($monthByBranch->get($branch->id, []));
            $monthGross = $this->exchangeOverlay->sumEffectiveNet($monthGroup);
            $monthPaid = $this->exchangeOverlay->sumEffectivePaid($monthGroup);
            $todayGross = $this->exchangeOverlay->sumEffectiveNet($todayGroup);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'today_gross' => round($todayGross, 2),
                'month_gross' => round($monthGross, 2),
                'month_paid' => round($monthPaid, 2),
                'month_due' => $this->exchangeOverlay->sumEffectiveDue($monthGroup),
                'month_refund_due' => $this->exchangeOverlay->sumEffectiveRefundDue($monthGroup),
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
        $branches = Branch::query()->operating()->orderBy('name')->get(['id', 'name']);

        $sales = $this->loadSalesForOverlay(
            $this->excludeMainBranchSales(
                Sell::query()->sale()->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
            ),
        );

        $byBranch = $sales->groupBy('branch_id');

        return $branches->map(function (Branch $branch) use ($byBranch) {
            $group = collect($byBranch->get($branch->id, []));
            $gross = $this->exchangeOverlay->sumEffectiveNet($group);
            $paid = $this->exchangeOverlay->sumEffectivePaid($group);

            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'invoice_count' => $group->count(),
                'gross' => $gross,
                'paid' => $paid,
                'due' => $this->exchangeOverlay->sumEffectiveDue($group),
                'refund_due' => $this->exchangeOverlay->sumEffectiveRefundDue($group),
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
        $sales = $this->loadSalesForOverlay(
            Sell::query()->sale()
                ->when($branchId !== null, fn (Builder $q) => $q->where('branch_id', $branchId))
                ->when($branchId === null, fn (Builder $q) => $q->where('branch_id', '!=', Branch::MAIN_BRANCH_ID))
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
        );

        $rows = $sales->groupBy(fn (Sell $sell) => Carbon::parse($sell->date)->format('Y-m-d'));

        $result = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $daySales = collect($rows->get($key, []));

            $result[] = [
                'date' => $key,
                'gross' => $this->exchangeOverlay->sumEffectiveNet($daySales),
                'paid' => $this->exchangeOverlay->sumEffectivePaid($daySales),
                'count' => $daySales->count(),
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
     * @param  array{gross: float, paid: float, due: float, refund_due?: float}  $sales
     * @return array{gross: float, paid: float, due: float, refund_due: float, rate: float}
     */
    private function collectionMetrics(array $sales): array
    {
        $gross = $sales['gross'];
        $paid = $sales['paid'];

        return [
            'gross' => $gross,
            'paid' => $paid,
            'due' => $sales['due'],
            'refund_due' => $sales['refund_due'] ?? 0.0,
            'rate' => $gross > 0 ? round($paid / $gross * 100, 1) : 0.0,
        ];
    }

    /**
     * @return EloquentCollection<int, Sell>
     */
    private function loadSalesForOverlay(Builder $query): EloquentCollection
    {
        return (clone $query)->with([
            'products:id,sell_id,discount',
            'productExchange:id,sell_id,price_difference,paid_amount',
        ])->get([
            'id',
            'branch_id',
            'date',
            'gross_amount',
            'vat',
            'discount',
            'special_discount_amount',
            'promotion_discount_total',
            'coin_discount_amount',
            'round_off_amount',
            'paid_amount',
        ]);
    }
}
