<?php

namespace App\Services;

use App\Enums\SaleType;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sell;
use App\Models\SellProduct;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SalesReportService
{
    private const string NET_AMOUNT_SQL = '(sells.gross_amount + sells.vat - sells.discount - sells.special_discount_amount - sells.coin_discount_amount - sells.round_off_amount)';

    private const string DISCOUNT_AMOUNT_SQL = '(sells.discount + sells.special_discount_amount + sells.coin_discount_amount + sells.round_off_amount)';

    public const TYPE_DAILY = 'daily';

    public const TYPE_MONTHLY = 'monthly';

    public const TYPE_HOURLY = 'hourly';

    public const TYPE_PRODUCT = 'product';

    public const TYPE_BRAND = 'brand';

    public const TYPE_CATEGORY = 'category';

    public const TYPE_PROFIT = 'profit';

    public const TYPE_STOCK = 'stock';

    public const TYPE_LOW_STOCK = 'low_stock';

    public const TYPE_DEAD_STOCK = 'dead_stock';

    public const TYPE_FAST_MOVING = 'fast_moving';

    public const TYPE_SLOW_MOVING = 'slow_moving';

    public const TYPE_SALESMAN = 'salesman';

    public const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    public const DEFAULT_DEAD_STOCK_DAYS = 90;

    public function __construct(private InventoryCostService $costService) {}

    /**
     * @return list<array{key: string, label: string, group: string}>
     */
    public function typeOptions(): array
    {
        return [
            ['key' => self::TYPE_DAILY, 'label' => 'Daily Sales', 'group' => 'Sales'],
            ['key' => self::TYPE_MONTHLY, 'label' => 'Monthly Sales', 'group' => 'Sales'],
            ['key' => self::TYPE_HOURLY, 'label' => 'Hourly Sales', 'group' => 'Sales'],
            ['key' => self::TYPE_PRODUCT, 'label' => 'Product wise Sales', 'group' => 'Sales'],
            ['key' => self::TYPE_BRAND, 'label' => 'Brand wise Sales', 'group' => 'Sales'],
            ['key' => self::TYPE_CATEGORY, 'label' => 'Category wise Sales', 'group' => 'Sales'],
            ['key' => self::TYPE_PROFIT, 'label' => 'Profit Report', 'group' => 'Sales'],
            ['key' => self::TYPE_STOCK, 'label' => 'Stock Report', 'group' => 'Stock'],
            ['key' => self::TYPE_LOW_STOCK, 'label' => 'Low stock', 'group' => 'Stock'],
            ['key' => self::TYPE_DEAD_STOCK, 'label' => 'Dead stock', 'group' => 'Stock'],
            ['key' => self::TYPE_FAST_MOVING, 'label' => 'Fast moving item', 'group' => 'Stock'],
            ['key' => self::TYPE_SLOW_MOVING, 'label' => 'Slow moving item', 'group' => 'Stock'],
            ['key' => self::TYPE_SALESMAN, 'label' => 'Salesman Report', 'group' => 'Sales'],
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedTypes(): array
    {
        return array_column($this->typeOptions(), 'key');
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function build(
        string $type,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $filterBranchId = null,
        ?int $lowStockThreshold = null,
        ?int $deadStockDays = null,
    ): array {
        $type = in_array($type, $this->allowedTypes(), true) ? $type : self::TYPE_DAILY;
        $branchId = $this->resolveBranchFilter($filterBranchId);
        $threshold = max(0, $lowStockThreshold ?? self::DEFAULT_LOW_STOCK_THRESHOLD);
        $inactiveDays = max(1, $deadStockDays ?? self::DEFAULT_DEAD_STOCK_DAYS);

        $label = collect($this->typeOptions())->firstWhere('key', $type)['label'] ?? $type;

        return match ($type) {
            self::TYPE_DAILY => $this->dailySales($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_MONTHLY => $this->monthlySales($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_HOURLY => $this->hourlySales($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_PRODUCT => $this->productSales($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_BRAND => $this->brandSales($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_CATEGORY => $this->categorySales($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_PROFIT => $this->profitReport($dateFrom, $dateTo, $branchId, $label),
            self::TYPE_STOCK => $this->stockReport($branchId, $label),
            self::TYPE_LOW_STOCK => $this->lowStockReport($branchId, $threshold, $label),
            self::TYPE_DEAD_STOCK => $this->deadStockReport($branchId, $inactiveDays, $label),
            self::TYPE_FAST_MOVING => $this->movingItemsReport($dateFrom, $dateTo, $branchId, $label, true),
            self::TYPE_SLOW_MOVING => $this->movingItemsReport($dateFrom, $dateTo, $branchId, $label, false),
            self::TYPE_SALESMAN => $this->salesmanReport($dateFrom, $dateTo, $branchId, $label),
            default => $this->dailySales($dateFrom, $dateTo, $branchId, $label),
        };
    }

    public function canFilterByBranch(): bool
    {
        $branchId = $this->userBranchId();

        return $branchId === null || Branch::isMainBranch($branchId);
    }

    /**
     * Sale line economics for reuse by related sales/profit reports.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function economicsLines(?string $dateFrom, ?string $dateTo, ?int $filterBranchId = null): Collection
    {
        return $this->saleLineEconomics($dateFrom, $dateTo, $this->resolveBranchFilter($filterBranchId));
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
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function dailySales(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        return $this->periodSalesReport(
            $label,
            self::TYPE_DAILY,
            'Date',
            $dateFrom,
            $dateTo,
            $branchId,
            'DATE(sells.date)',
            fn (array $line): string => (string) $line['date'],
        );
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function monthlySales(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        return $this->periodSalesReport(
            $label,
            self::TYPE_MONTHLY,
            'Month',
            $dateFrom,
            $dateTo,
            $branchId,
            "DATE_FORMAT(sells.date, '%Y-%m')",
            fn (array $line): string => (string) $line['month'],
        );
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function hourlySales(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        $netSql = self::NET_AMOUNT_SQL;
        $discountSql = self::DISCOUNT_AMOUNT_SQL;

        $salesRows = $this->salesQuery($dateFrom, $dateTo, $branchId)
            ->selectRaw('HOUR(sells.created_at) as period_key')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(sells.gross_amount), 0) as gross')
            ->selectRaw("COALESCE(SUM({$discountSql}), 0) as discount")
            ->selectRaw('COALESCE(SUM(sells.vat), 0) as vat')
            ->selectRaw("COALESCE(SUM({$netSql}), 0) as net")
            ->selectRaw('COALESCE(SUM(sells.paid_amount), 0) as paid')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy(fn ($row) => (string) $row->period_key);

        $costByPeriod = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => (string) $line['hour'])
            ->map(fn (Collection $lines) => round($lines->sum('cost'), 2));

        $rows = $salesRows->map(function ($row) use ($costByPeriod) {
            $key = (string) $row->period_key;
            $hour = (int) $row->period_key;
            $gross = round((float) $row->gross, 2);
            $discount = round((float) $row->discount, 2);
            $vat = round((float) $row->vat, 2);
            $net = round((float) $row->net, 2);
            $cost = (float) ($costByPeriod[$key] ?? 0);
            $profit = round($net - $cost, 2);

            return [
                'period' => sprintf('%02d:00 – %02d:59', $hour, $hour),
                'invoice_count' => (int) $row->invoice_count,
                'gross' => $gross,
                'discount' => $discount,
                'discount_pct' => $this->percentOf($discount, $gross),
                'vat' => $vat,
                'vat_pct' => $this->percentOf($vat, $gross),
                'net' => $net,
                'paid' => round((float) $row->paid, 2),
                'cost' => $cost,
                'profit' => $profit,
                'margin' => $this->percentOf($profit, $net),
            ];
        })->values()->all();

        return $this->periodResult($label, self::TYPE_HOURLY, 'Hour', $rows);
    }

    /**
     * @param  callable(array<string, mixed>): string  $linePeriodKey
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function periodSalesReport(
        string $label,
        string $type,
        string $periodLabel,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $branchId,
        string $periodSql,
        callable $linePeriodKey,
    ): array {
        $netSql = self::NET_AMOUNT_SQL;
        $discountSql = self::DISCOUNT_AMOUNT_SQL;

        $salesRows = $this->salesQuery($dateFrom, $dateTo, $branchId)
            ->selectRaw("{$periodSql} as period_key")
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(sells.gross_amount), 0) as gross')
            ->selectRaw("COALESCE(SUM({$discountSql}), 0) as discount")
            ->selectRaw('COALESCE(SUM(sells.vat), 0) as vat')
            ->selectRaw("COALESCE(SUM({$netSql}), 0) as net")
            ->selectRaw('COALESCE(SUM(sells.paid_amount), 0) as paid')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy(fn ($row) => (string) $row->period_key);

        $costByPeriod = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => $linePeriodKey($line))
            ->map(fn (Collection $lines) => round($lines->sum('cost'), 2));

        $rows = $salesRows->map(function ($row) use ($costByPeriod) {
            $key = (string) $row->period_key;
            $gross = round((float) $row->gross, 2);
            $discount = round((float) $row->discount, 2);
            $vat = round((float) $row->vat, 2);
            $net = round((float) $row->net, 2);
            $cost = (float) ($costByPeriod[$key] ?? 0);
            $profit = round($net - $cost, 2);

            return [
                'period' => $key,
                'invoice_count' => (int) $row->invoice_count,
                'gross' => $gross,
                'discount' => $discount,
                'discount_pct' => $this->percentOf($discount, $gross),
                'vat' => $vat,
                'vat_pct' => $this->percentOf($vat, $gross),
                'net' => $net,
                'paid' => round((float) $row->paid, 2),
                'cost' => $cost,
                'profit' => $profit,
                'margin' => $this->percentOf($profit, $net),
            ];
        })->values()->all();

        return $this->periodResult($label, $type, $periodLabel, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function periodResult(string $label, string $type, string $periodLabel, array $rows): array
    {
        $totalGross = round(collect($rows)->sum('gross'), 2);
        $totalDiscount = round(collect($rows)->sum('discount'), 2);
        $totalVat = round(collect($rows)->sum('vat'), 2);
        $totalNet = round(collect($rows)->sum('net'), 2);
        $totalProfit = round(collect($rows)->sum('profit'), 2);

        return [
            'type' => $type,
            'label' => $label,
            'columns' => [
                ['key' => 'period', 'label' => $periodLabel],
                ['key' => 'invoice_count', 'label' => 'Invoices', 'align' => 'right'],
                ['key' => 'gross', 'label' => 'Gross', 'align' => 'right'],
                ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
                ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
                ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
                ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
                ['key' => 'net', 'label' => 'Net', 'align' => 'right'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'right'],
                ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
                ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
                ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => [
                'invoice_count' => collect($rows)->sum('invoice_count'),
                'gross' => $totalGross,
                'discount' => $totalDiscount,
                'discount_pct' => $this->percentOf($totalDiscount, $totalGross),
                'vat' => $totalVat,
                'vat_pct' => $this->percentOf($totalVat, $totalGross),
                'net' => $totalNet,
                'paid' => round(collect($rows)->sum('paid'), 2),
                'cost' => round(collect($rows)->sum('cost'), 2),
                'profit' => $totalProfit,
                'margin' => $this->percentOf($totalProfit, $totalNet),
            ],
            'meta' => [],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function productSales(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        $includeBranch = $branchId === null && $this->canFilterByBranch();

        $rows = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => $includeBranch
                ? $line['product_id'].'|'.$line['branch_id']
                : (string) $line['product_id'])
            ->map(function (Collection $lines) use ($includeBranch) {
                $first = $lines->first();
                $row = $this->sumEconomicsGroup($lines, [
                    'name' => $first['product_name'],
                    'code' => $first['product_code'],
                ]);

                if ($includeBranch) {
                    $row = ['branch' => $first['branch_name'], ...$row];
                }

                return $row;
            })
            ->sortByDesc('quantity')
            ->values()
            ->all();

        $columns = [
            ...($includeBranch ? [['key' => 'branch', 'label' => 'Branch']] : []),
            ['key' => 'name', 'label' => 'Product'],
            ['key' => 'code', 'label' => 'Code'],
            ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
            ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
            ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
            ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
            ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
            ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
            ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
        ];

        return $this->economicsResult(self::TYPE_PRODUCT, $label, $columns, $rows);
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function brandSales(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        $rows = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => (string) ($line['brand_id'] ?? 'none'))
            ->map(function (Collection $lines) {
                $first = $lines->first();

                return $this->sumEconomicsGroup($lines, [
                    'name' => $first['brand_name'],
                ]);
            })
            ->sortByDesc('quantity')
            ->values()
            ->all();

        return $this->economicsResult(self::TYPE_BRAND, $label, [
            ['key' => 'name', 'label' => 'Brand'],
            ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
            ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
            ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
            ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
            ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
            ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
            ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
        ], $rows);
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function categorySales(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        $rows = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => (string) ($line['category_id'] ?? 'none'))
            ->map(function (Collection $lines) {
                $first = $lines->first();

                return $this->sumEconomicsGroup($lines, [
                    'name' => $first['category_name'],
                ]);
            })
            ->sortByDesc('quantity')
            ->values()
            ->all();

        return $this->economicsResult(self::TYPE_CATEGORY, $label, [
            ['key' => 'name', 'label' => 'Category'],
            ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
            ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
            ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
            ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
            ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
            ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
            ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
        ], $rows);
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function profitReport(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        $includeBranch = $branchId === null && $this->canFilterByBranch();

        $rows = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => $includeBranch
                ? $line['product_id'].'|'.$line['branch_id']
                : (string) $line['product_id'])
            ->map(function (Collection $lines) use ($includeBranch) {
                $first = $lines->first();
                $row = $this->sumEconomicsGroup($lines, [
                    'name' => $first['product_name'],
                    'code' => $first['product_code'],
                ]);
                $row['revenue'] = $row['amount'];
                unset($row['amount']);

                if ($includeBranch) {
                    $row = ['branch' => $first['branch_name'], ...$row];
                }

                return $row;
            })
            ->sortByDesc('profit')
            ->values()
            ->all();

        $totalRevenue = round(collect($rows)->sum('revenue'), 2);
        $totalDiscount = round(collect($rows)->sum('discount'), 2);
        $totalVat = round(collect($rows)->sum('vat'), 2);
        $totalProfit = round(collect($rows)->sum('profit'), 2);

        return [
            'type' => self::TYPE_PROFIT,
            'label' => $label,
            'columns' => [
                ...($includeBranch ? [['key' => 'branch', 'label' => 'Branch']] : []),
                ['key' => 'name', 'label' => 'Product'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
                ['key' => 'revenue', 'label' => 'Revenue', 'align' => 'right'],
                ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
                ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
                ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
                ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
                ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
                ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
                ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => [
                'quantity' => round(collect($rows)->sum('quantity'), 2),
                'revenue' => $totalRevenue,
                'discount' => $totalDiscount,
                'discount_pct' => $this->percentOf($totalDiscount, $totalRevenue + $totalDiscount),
                'vat' => $totalVat,
                'vat_pct' => $this->percentOf($totalVat, $totalRevenue),
                'cost' => round(collect($rows)->sum('cost'), 2),
                'profit' => $totalProfit,
                'margin' => $this->percentOf($totalProfit, $totalRevenue),
            ],
            'meta' => [],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function stockReport(?int $branchId, string $label): array
    {
        $rows = $this->stockRows($branchId)
            ->sortBy('name')
            ->values()
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'code' => $row['code'],
                'branch' => $row['branch_name'],
                'stock' => $row['stock'],
                'value' => $row['value'],
            ])
            ->all();

        $includeBranch = $branchId === null && $this->canFilterByBranch();

        return [
            'type' => self::TYPE_STOCK,
            'label' => $label,
            'columns' => [
                ...($includeBranch ? [['key' => 'branch', 'label' => 'Branch']] : []),
                ['key' => 'name', 'label' => 'Product'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'stock', 'label' => 'Stock', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Stock Value', 'align' => 'right'],
            ],
            'rows' => $includeBranch
                ? $rows
                : collect($rows)->map(fn (array $row) => collect($row)->except('branch')->all())->all(),
            'totals' => [
                'stock' => round(collect($rows)->sum('stock'), 2),
                'value' => round(collect($rows)->sum('value'), 2),
            ],
            'meta' => [],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function lowStockReport(?int $branchId, int $threshold, string $label): array
    {
        $includeBranch = $branchId === null && $this->canFilterByBranch();

        $rows = $this->stockRows($branchId)
            ->filter(fn (array $row) => $row['stock'] <= $threshold)
            ->sortBy('stock')
            ->values()
            ->map(function (array $row) use ($threshold, $includeBranch) {
                $mapped = [
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'stock' => $row['stock'],
                    'threshold' => $threshold,
                ];

                if ($includeBranch) {
                    $mapped = ['branch' => $row['branch_name'], ...$mapped];
                }

                return $mapped;
            })
            ->all();

        return [
            'type' => self::TYPE_LOW_STOCK,
            'label' => $label,
            'columns' => [
                ...($includeBranch ? [['key' => 'branch', 'label' => 'Branch']] : []),
                ['key' => 'name', 'label' => 'Product'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'stock', 'label' => 'Stock', 'align' => 'right'],
                ['key' => 'threshold', 'label' => 'Threshold', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => [
                'stock' => round(collect($rows)->sum('stock'), 2),
                'items' => count($rows),
            ],
            'meta' => ['threshold' => $threshold],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function deadStockReport(?int $branchId, int $inactiveDays, string $label): array
    {
        $cutoff = Carbon::today()->subDays($inactiveDays)->toDateString();
        $includeBranch = $branchId === null && $this->canFilterByBranch();

        $soldProductIds = $this->saleLinesQuery($cutoff, null, $branchId)
            ->distinct()
            ->pluck('sell_products.product_id')
            ->all();

        $rows = $this->stockRows($branchId)
            ->filter(fn (array $row) => $row['stock'] > 0 && ! in_array($row['id'], $soldProductIds, true))
            ->sortByDesc('stock')
            ->values()
            ->map(function (array $row) use ($inactiveDays, $includeBranch) {
                $mapped = [
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'stock' => $row['stock'],
                    'value' => $row['value'],
                    'inactive_days' => $inactiveDays,
                ];

                if ($includeBranch) {
                    $mapped = ['branch' => $row['branch_name'], ...$mapped];
                }

                return $mapped;
            })
            ->all();

        return [
            'type' => self::TYPE_DEAD_STOCK,
            'label' => $label,
            'columns' => [
                ...($includeBranch ? [['key' => 'branch', 'label' => 'Branch']] : []),
                ['key' => 'name', 'label' => 'Product'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'stock', 'label' => 'Stock', 'align' => 'right'],
                ['key' => 'value', 'label' => 'Stock Value', 'align' => 'right'],
                ['key' => 'inactive_days', 'label' => 'No sale days', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => [
                'stock' => round(collect($rows)->sum('stock'), 2),
                'value' => round(collect($rows)->sum('value'), 2),
                'items' => count($rows),
            ],
            'meta' => ['inactive_days' => $inactiveDays],
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function movingItemsReport(
        ?string $dateFrom,
        ?string $dateTo,
        ?int $branchId,
        string $label,
        bool $fast,
    ): array {
        $rows = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => (string) $line['product_id'])
            ->map(function (Collection $lines) {
                $first = $lines->first();

                return $this->sumEconomicsGroup($lines, [
                    'name' => $first['product_name'],
                    'code' => $first['product_code'],
                ]);
            });

        $rows = ($fast ? $rows->sortByDesc('quantity') : $rows->sortBy('quantity'))
            ->take(50)
            ->values()
            ->all();

        return $this->economicsResult(
            $fast ? self::TYPE_FAST_MOVING : self::TYPE_SLOW_MOVING,
            $label,
            [
                ['key' => 'name', 'label' => 'Product'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'quantity', 'label' => 'Qty Sold', 'align' => 'right'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
                ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
                ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
                ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
                ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
                ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
                ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
                ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
            ],
            $rows,
            ['limit' => 50],
        );
    }

    /**
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function salesmanReport(?string $dateFrom, ?string $dateTo, ?int $branchId, string $label): array
    {
        $netSql = self::NET_AMOUNT_SQL;
        $discountSql = self::DISCOUNT_AMOUNT_SQL;

        $salesRows = $this->salesQuery($dateFrom, $dateTo, $branchId)
            ->leftJoin('users', 'users.id', '=', 'sells.user_id')
            ->selectRaw('sells.user_id as period_key')
            ->selectRaw("COALESCE(users.name, 'Unknown') as name")
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(sells.gross_amount), 0) as gross')
            ->selectRaw("COALESCE(SUM({$discountSql}), 0) as discount")
            ->selectRaw('COALESCE(SUM(sells.vat), 0) as vat')
            ->selectRaw("COALESCE(SUM({$netSql}), 0) as net")
            ->selectRaw('COALESCE(SUM(sells.paid_amount), 0) as paid')
            ->groupBy('sells.user_id', 'users.name')
            ->orderByDesc('net')
            ->get()
            ->keyBy(fn ($row) => (string) ($row->period_key ?? 'none'));

        $costByUser = $this->saleLineEconomics($dateFrom, $dateTo, $branchId)
            ->groupBy(fn (array $line) => (string) ($line['user_id'] ?? 'none'))
            ->map(fn (Collection $lines) => round($lines->sum('cost'), 2));

        $rows = $salesRows->map(function ($row) use ($costByUser) {
            $key = (string) ($row->period_key ?? 'none');
            $gross = round((float) $row->gross, 2);
            $discount = round((float) $row->discount, 2);
            $vat = round((float) $row->vat, 2);
            $net = round((float) $row->net, 2);
            $cost = (float) ($costByUser[$key] ?? 0);
            $profit = round($net - $cost, 2);

            return [
                'name' => (string) $row->name,
                'invoice_count' => (int) $row->invoice_count,
                'gross' => $gross,
                'discount' => $discount,
                'discount_pct' => $this->percentOf($discount, $gross),
                'vat' => $vat,
                'vat_pct' => $this->percentOf($vat, $gross),
                'net' => $net,
                'paid' => round((float) $row->paid, 2),
                'cost' => $cost,
                'profit' => $profit,
                'margin' => $this->percentOf($profit, $net),
            ];
        })->values()->all();

        $totalGross = round(collect($rows)->sum('gross'), 2);
        $totalDiscount = round(collect($rows)->sum('discount'), 2);
        $totalVat = round(collect($rows)->sum('vat'), 2);
        $totalNet = round(collect($rows)->sum('net'), 2);
        $totalProfit = round(collect($rows)->sum('profit'), 2);

        return [
            'type' => self::TYPE_SALESMAN,
            'label' => $label,
            'columns' => [
                ['key' => 'name', 'label' => 'Salesman'],
                ['key' => 'invoice_count', 'label' => 'Invoices', 'align' => 'right'],
                ['key' => 'gross', 'label' => 'Gross', 'align' => 'right'],
                ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
                ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
                ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
                ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
                ['key' => 'net', 'label' => 'Net', 'align' => 'right'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'right'],
                ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
                ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
                ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
            ],
            'rows' => $rows,
            'totals' => [
                'invoice_count' => collect($rows)->sum('invoice_count'),
                'gross' => $totalGross,
                'discount' => $totalDiscount,
                'discount_pct' => $this->percentOf($totalDiscount, $totalGross),
                'vat' => $totalVat,
                'vat_pct' => $this->percentOf($totalVat, $totalGross),
                'net' => $totalNet,
                'paid' => round(collect($rows)->sum('paid'), 2),
                'cost' => round(collect($rows)->sum('cost'), 2),
                'profit' => $totalProfit,
                'margin' => $this->percentOf($totalProfit, $totalNet),
            ],
            'meta' => [],
        ];
    }

    /**
     * @param  list<array{key: string, label: string, align?: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $meta
     * @return array{
     *     type: string,
     *     label: string,
     *     columns: list<array{key: string, label: string, align?: string}>,
     *     rows: list<array<string, mixed>>,
     *     totals: array<string, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function economicsResult(string $type, string $label, array $columns, array $rows, array $meta = []): array
    {
        $totalAmount = round(collect($rows)->sum('amount'), 2);
        $totalDiscount = round(collect($rows)->sum('discount'), 2);
        $totalVat = round(collect($rows)->sum('vat'), 2);
        $totalProfit = round(collect($rows)->sum('profit'), 2);

        return [
            'type' => $type,
            'label' => $label,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => [
                'quantity' => round(collect($rows)->sum('quantity'), 2),
                'amount' => $totalAmount,
                'discount' => $totalDiscount,
                'discount_pct' => $this->percentOf($totalDiscount, $totalAmount + $totalDiscount),
                'vat' => $totalVat,
                'vat_pct' => $this->percentOf($totalVat, $totalAmount),
                'cost' => round(collect($rows)->sum('cost'), 2),
                'profit' => $totalProfit,
                'margin' => $this->percentOf($totalProfit, $totalAmount),
            ],
            'meta' => $meta,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $identity
     * @return array<string, mixed>
     */
    private function sumEconomicsGroup(Collection $lines, array $identity): array
    {
        $quantity = round($lines->sum('quantity'), 2);
        $amount = round($lines->sum('revenue'), 2);
        $discount = round($lines->sum('discount'), 2);
        $vat = round($lines->sum('vat'), 2);
        $cost = round($lines->sum('cost'), 2);
        $profit = round($amount - $cost, 2);

        return [
            ...$identity,
            'quantity' => $quantity,
            'amount' => $amount,
            'discount' => $discount,
            'discount_pct' => $this->percentOf($discount, $amount + $discount),
            'vat' => $vat,
            'vat_pct' => $this->percentOf($vat, $amount),
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $this->percentOf($profit, $amount),
        ];
    }

    /**
     * Superadmin (no branch) sees every branch sale. Branch users only see their branch.
     *
     * Invoice-level discounts (invoice / special / coin / round-off) and VAT are allocated
     * to lines by share of post–line-discount revenue so product/brand/category reports match the sale.
     *
     * @return Collection<int, array{
     *     sell_id: int,
     *     branch_id: int|null,
     *     branch_name: string,
     *     user_id: int|null,
     *     date: string,
     *     month: string,
     *     hour: int,
     *     product_id: int,
     *     product_name: string,
     *     product_code: string,
     *     brand_id: int|null,
     *     brand_name: string,
     *     category_id: int|null,
     *     category_name: string,
     *     quantity: float,
     *     revenue: float,
     *     discount: float,
     *     vat: float,
     *     cost: float
     * }>
     */
    private function saleLineEconomics(?string $dateFrom, ?string $dateTo, ?int $branchId): Collection
    {
        $lines = $this->saleLinesQuery($dateFrom, $dateTo, $branchId)
            ->with([
                'product:id,name,code,purchase_price,brand_id,category_id,branch_id',
                'product.brand:id,name',
                'product.category:id,name',
                'product.branch:id,name',
                'variation:id,purchase_price',
                'sell:id,date,created_at,user_id,branch_id,gross_amount,vat,discount,special_discount_amount,coin_discount_amount,round_off_amount',
            ])
            ->orderBy('sell_products.id')
            ->get();

        $baseRevenueBySellId = $lines
            ->groupBy('sell_id')
            ->map(function (Collection $sellLines) {
                return $sellLines->sum(function (SellProduct $line) {
                    return $this->lineBaseRevenue($line);
                });
            });

        return $lines->map(function (SellProduct $line) use ($baseRevenueBySellId) {
            $qty = (float) $line->quantity + (float) ($line->free_quantity ?? 0);
            $lineOnlyDiscount = (float) $line->discount + (float) ($line->promotion_discount ?? 0);
            $baseRevenue = $this->lineBaseRevenue($line);
            $cost = $this->costService->costForLine(
                $line->variation_id ? (int) $line->variation_id : null,
                $qty,
                is_array($line->batches) ? $line->batches : [],
            );

            if ($cost <= 0 && $line->product) {
                $unitCost = $line->variation
                    ? (float) $line->variation->purchase_price
                    : (float) $line->product->purchase_price;
                $cost = round($unitCost * $qty, 2);
            }

            $sell = $line->sell;
            $sellVat = (float) ($sell?->vat ?? 0);
            $sellInvoiceDiscount = $this->invoiceLevelDiscountTotal($sell);
            $sellBaseTotal = (float) ($baseRevenueBySellId[$line->sell_id] ?? 0);
            $share = $sellBaseTotal > 0 ? ($baseRevenue / $sellBaseTotal) : 0.0;
            $allocatedInvoiceDiscount = $sellInvoiceDiscount > 0 && $share > 0
                ? round($sellInvoiceDiscount * $share, 2)
                : 0.0;
            $allocatedVat = $sellVat > 0 && $share > 0
                ? round($sellVat * $share, 2)
                : 0.0;
            $revenue = round($baseRevenue - $allocatedInvoiceDiscount, 2);
            $discount = round($lineOnlyDiscount + $allocatedInvoiceDiscount, 2);

            $sellDate = $sell?->date;

            return [
                'sell_id' => (int) $line->sell_id,
                'branch_id' => $sell?->branch_id !== null ? (int) $sell->branch_id : null,
                'branch_name' => $line->product?->branch?->name
                    ?? ($sell?->branch_id ? 'Branch #'.$sell->branch_id : 'All'),
                'user_id' => $sell?->user_id !== null ? (int) $sell->user_id : null,
                'date' => $sellDate?->format('Y-m-d') ?? '',
                'month' => $sellDate?->format('Y-m') ?? '',
                'hour' => $sell?->created_at ? (int) $sell->created_at->format('G') : 0,
                'product_id' => (int) $line->product_id,
                'product_name' => $line->product?->name ?? '—',
                'product_code' => $line->product?->code ?? '—',
                'brand_id' => $line->product?->brand_id !== null ? (int) $line->product->brand_id : null,
                'brand_name' => $line->product?->brand?->name ?? 'Unbranded',
                'category_id' => $line->product?->category_id !== null ? (int) $line->product->category_id : null,
                'category_name' => $line->product?->category?->name ?? 'Uncategorized',
                'quantity' => $qty,
                'revenue' => $revenue,
                'discount' => $discount,
                'vat' => $allocatedVat,
                'cost' => $cost,
                '_sell_vat' => $sellVat,
                '_sell_invoice_discount' => $sellInvoiceDiscount,
                '_allocated_invoice_discount' => $allocatedInvoiceDiscount,
            ];
        })->pipe(function (Collection $mapped) {
            // Fix rounding leftovers so allocated totals match the invoice.
            return $mapped
                ->groupBy('sell_id')
                ->flatMap(fn (Collection $sellLines) => $this->balanceAllocatedSellAmounts($sellLines)->values())
                ->values();
        });
    }

    private function lineBaseRevenue(SellProduct $line): float
    {
        return ((float) $line->quantity * (float) $line->unit_price) - (float) $line->discount;
    }

    private function invoiceLevelDiscountTotal(?Sell $sell): float
    {
        if ($sell === null) {
            return 0.0;
        }

        return round(
            (float) $sell->discount
            + (float) $sell->special_discount_amount
            + (float) $sell->coin_discount_amount
            + (float) $sell->round_off_amount,
            2,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sellLines
     * @return Collection<int, array<string, mixed>>
     */
    private function balanceAllocatedSellAmounts(Collection $sellLines): Collection
    {
        $first = $sellLines->first() ?? [];
        $vatDiff = round((float) ($first['_sell_vat'] ?? 0) - round($sellLines->sum('vat'), 2), 2);
        $invoiceDiscountDiff = round(
            (float) ($first['_sell_invoice_discount'] ?? 0) - round($sellLines->sum('_allocated_invoice_discount'), 2),
            2,
        );
        $lastKey = $sellLines->keys()->last();

        return $sellLines->map(function (array $line, $key) use ($lastKey, $vatDiff, $invoiceDiscountDiff) {
            if ($key === $lastKey) {
                if (abs($vatDiff) >= 0.01) {
                    $line['vat'] = round((float) $line['vat'] + $vatDiff, 2);
                }

                if (abs($invoiceDiscountDiff) >= 0.01) {
                    $line['_allocated_invoice_discount'] = round(
                        (float) $line['_allocated_invoice_discount'] + $invoiceDiscountDiff,
                        2,
                    );
                    $line['discount'] = round((float) $line['discount'] + $invoiceDiscountDiff, 2);
                    $line['revenue'] = round((float) $line['revenue'] - $invoiceDiscountDiff, 2);
                }
            }

            unset($line['_sell_vat'], $line['_sell_invoice_discount'], $line['_allocated_invoice_discount']);

            return $line;
        });
    }

    private function salesQuery(?string $dateFrom, ?string $dateTo, ?int $branchId): Builder
    {
        return Sell::query()
            ->sale()
            ->when($branchId !== null, fn (Builder $q) => $q->where('sells.branch_id', $branchId))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('sells.date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('sells.date', '<=', $d));
    }

    private function saleLinesQuery(?string $dateFrom, ?string $dateTo, ?int $branchId): Builder
    {
        return SellProduct::query()
            ->select('sell_products.*')
            ->join('sells', 'sells.id', '=', 'sell_products.sell_id')
            ->where('sells.type', SaleType::Sale)
            ->when($branchId !== null, fn (Builder $q) => $q->where('sells.branch_id', $branchId))
            ->when($dateFrom, fn (Builder $q, string $d) => $q->whereDate('sells.date', '>=', $d))
            ->when($dateTo, fn (Builder $q, string $d) => $q->whereDate('sells.date', '<=', $d));
    }

    /**
     * @return Collection<int, array{id: int, name: string, code: string, branch_name: string, stock: float, value: float}>
     */
    private function stockRows(?int $branchId): Collection
    {
        $products = Product::query()
            ->with([
                'branch:id,name',
                'variations' => function ($q) use ($branchId) {
                    $q->select(['id', 'product_id', 'branch_id', 'stock', 'purchase_price']);
                    if ($branchId !== null) {
                        $q->where('branch_id', $branchId);
                    }
                },
            ])
            ->when(
                $branchId !== null,
                fn (Builder $q) => $q->where('branch_id', $branchId),
                // Superadmin with no branch filter: every product in every branch.
                fn (Builder $q) => $this->canFilterByBranch() ? $q : $q->ownBranch(),
            )
            ->get(['id', 'name', 'code', 'purchase_price', 'branch_id']);

        $batchStock = Batch::query()
            ->select('product_id')
            ->selectRaw('COALESCE(SUM(available), 0) as stock')
            ->selectRaw('COALESCE(SUM(available * purchase_price), 0) as value')
            ->when($branchId !== null, function (Builder $q) use ($branchId) {
                if (Branch::isMainBranch($branchId)) {
                    $q->where(function (Builder $inner) {
                        $inner->where('branch_id', Branch::MAIN_BRANCH_ID)
                            ->orWhereNull('branch_id');
                    });
                } else {
                    $q->where(function (Builder $inner) use ($branchId) {
                        $inner->where('branch_id', $branchId)
                            ->orWhereNull('branch_id');
                    });
                }
            })
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        return $products->map(function (Product $product) use ($batchStock) {
            $variations = $product->variations;

            if ($variations->isNotEmpty()) {
                $stock = (float) $variations->sum('stock');
                $value = (float) $variations->sum(
                    fn (ProductVariation $variation) => (float) $variation->stock * (float) $variation->purchase_price,
                );
            } else {
                $batch = $batchStock->get($product->id);
                $stock = (float) ($batch->stock ?? 0);
                $value = (float) ($batch->value ?? 0);
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code ?? '—',
                'branch_name' => $product->branch?->name ?? '—',
                'stock' => round($stock, 2),
                'value' => round($value, 2),
            ];
        });
    }

    private function percentOf(float $part, float $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round(($part / $whole) * 100, 1);
    }

    private function resolveBranchFilter(?int $filterBranchId): ?int
    {
        if ($this->canFilterByBranch()) {
            return $filterBranchId;
        }

        return $this->userBranchId();
    }

    private function userBranchId(): ?int
    {
        return Auth::user()?->branch_id;
    }
}
