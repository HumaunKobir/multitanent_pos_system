<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalesProfitTrendService
{
    public const GROUP_PRODUCT = 'product';

    public const GROUP_BRAND = 'brand';

    public const GROUP_CATEGORY = 'category';

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_MONTHLY = 'monthly';

    public const COMPARISON_LIMIT = 10;

    public function __construct(private SalesReportService $salesReports) {}

    /**
     * @return list<array{key: string, label: string}>
     */
    public function groupOptions(): array
    {
        return [
            ['key' => self::GROUP_PRODUCT, 'label' => 'Product wise'],
            ['key' => self::GROUP_BRAND, 'label' => 'Brand wise'],
            ['key' => self::GROUP_CATEGORY, 'label' => 'Category wise'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function periodOptions(): array
    {
        return [
            ['key' => self::PERIOD_DAILY, 'label' => 'Daily'],
            ['key' => self::PERIOD_MONTHLY, 'label' => 'Monthly'],
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedGroups(): array
    {
        return array_column($this->groupOptions(), 'key');
    }

    /**
     * @return list<string>
     */
    public function allowedPeriods(): array
    {
        return array_column($this->periodOptions(), 'key');
    }

    public function canFilterByBranch(): bool
    {
        return $this->salesReports->canFilterByBranch();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function branchOptions(): array
    {
        return $this->salesReports->branchOptions();
    }

    /**
     * @return array{
     *     group_by: string,
     *     period: string,
     *     trend: list<array{period: string, label: string, sales: float, discount: float, vat: float, cost: float, profit: float, margin: float}>,
     *     comparison: list<array{name: string, sales: float, discount: float, vat: float, cost: float, profit: float, margin: float}>,
     *     breakdown: array{
     *         columns: list<array{key: string, label: string, align?: string}>,
     *         rows: list<array<string, mixed>>
     *     },
     *     totals: array<string, float>
     * }
     */
    public function build(
        string $groupBy,
        string $period,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $filterBranchId = null,
    ): array {
        $groupBy = in_array($groupBy, $this->allowedGroups(), true) ? $groupBy : self::GROUP_PRODUCT;
        $period = in_array($period, $this->allowedPeriods(), true) ? $period : self::PERIOD_DAILY;

        $lines = $this->salesReports->economicsLines($dateFrom, $dateTo, $filterBranchId);
        $breakdownRows = $this->breakdownRows($lines, $groupBy);
        $totals = $this->totalsFromRows($breakdownRows);

        return [
            'group_by' => $groupBy,
            'period' => $period,
            'trend' => $this->trendSeries($lines, $period, $dateFrom, $dateTo),
            'comparison' => collect($breakdownRows)
                ->take(self::COMPARISON_LIMIT)
                ->map(fn (array $row) => [
                    'name' => (string) ($row['name'] ?? '—'),
                    'sales' => (float) $row['sales'],
                    'discount' => (float) $row['discount'],
                    'vat' => (float) $row['vat'],
                    'cost' => (float) $row['cost'],
                    'profit' => (float) $row['profit'],
                    'margin' => (float) $row['margin'],
                ])
                ->values()
                ->all(),
            'breakdown' => [
                'columns' => $this->breakdownColumns($groupBy),
                'rows' => $breakdownRows,
            ],
            'totals' => $totals,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array{period: string, label: string, sales: float, discount: float, vat: float, cost: float, profit: float, margin: float}>
     */
    private function trendSeries(Collection $lines, string $period, ?string $dateFrom, ?string $dateTo): array
    {
        $grouped = $lines->groupBy(function (array $line) use ($period) {
            if ($period === self::PERIOD_MONTHLY) {
                return (string) ($line['month'] ?: '—');
            }

            return (string) ($line['date'] ?: '—');
        });

        $keys = $this->periodKeys($period, $dateFrom, $dateTo, $grouped->keys()->all());

        return collect($keys)
            ->map(function (string $key) use ($grouped) {
                $bucket = $grouped->get($key, collect());
                $sales = round((float) $bucket->sum('revenue'), 2);
                $discount = round((float) $bucket->sum('discount'), 2);
                $vat = round((float) $bucket->sum('vat'), 2);
                $cost = round((float) $bucket->sum('cost'), 2);
                $profit = round($sales - $cost, 2);

                return [
                    'period' => $key,
                    'label' => $this->periodLabel($key),
                    'sales' => $sales,
                    'discount' => $discount,
                    'vat' => $vat,
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin' => $this->percentOf($profit, $sales),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $existingKeys
     * @return list<string>
     */
    private function periodKeys(string $period, ?string $dateFrom, ?string $dateTo, array $existingKeys): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $to = $dateTo ? Carbon::parse($dateTo)->startOfDay() : null;

        if ($from === null && $to === null) {
            $sorted = collect($existingKeys)->filter()->sort()->values();

            return $sorted->all();
        }

        $from ??= $to?->copy() ?? now()->startOfDay();
        $to ??= $from->copy();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $keys = [];

        if ($period === self::PERIOD_MONTHLY) {
            $cursor = $from->copy()->startOfMonth();
            $end = $to->copy()->startOfMonth();

            while ($cursor->lte($end)) {
                $keys[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            return $keys;
        }

        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $keys;
    }

    private function periodLabel(string $key): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) === 1) {
            return Carbon::parse($key)->format('d M');
        }

        if (preg_match('/^\d{4}-\d{2}$/', $key) === 1) {
            return Carbon::createFromFormat('Y-m', $key)->format('M Y');
        }

        return $key;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function breakdownRows(Collection $lines, string $groupBy): array
    {
        return $lines
            ->groupBy(function (array $line) use ($groupBy) {
                return match ($groupBy) {
                    self::GROUP_BRAND => (string) ($line['brand_id'] ?? 'none'),
                    self::GROUP_CATEGORY => (string) ($line['category_id'] ?? 'none'),
                    default => (string) $line['product_id'],
                };
            })
            ->map(function (Collection $group) use ($groupBy) {
                $first = $group->first();
                $sales = round((float) $group->sum('revenue'), 2);
                $discount = round((float) $group->sum('discount'), 2);
                $vat = round((float) $group->sum('vat'), 2);
                $cost = round((float) $group->sum('cost'), 2);
                $profit = round($sales - $cost, 2);
                $quantity = round((float) $group->sum('quantity'), 2);

                $row = [
                    'name' => match ($groupBy) {
                        self::GROUP_BRAND => $first['brand_name'] ?? 'Unbranded',
                        self::GROUP_CATEGORY => $first['category_name'] ?? 'Uncategorized',
                        default => $first['product_name'] ?? '—',
                    },
                    'quantity' => $quantity,
                    'sales' => $sales,
                    'discount' => $discount,
                    'discount_pct' => $this->percentOf($discount, $sales + $discount),
                    'vat' => $vat,
                    'vat_pct' => $this->percentOf($vat, $sales),
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin' => $this->percentOf($profit, $sales),
                ];

                if ($groupBy === self::GROUP_PRODUCT) {
                    $row['code'] = $first['product_code'] ?? '—';
                }

                return $row;
            })
            ->sortByDesc('profit')
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, align?: string}>
     */
    private function breakdownColumns(string $groupBy): array
    {
        $nameLabel = match ($groupBy) {
            self::GROUP_BRAND => 'Brand',
            self::GROUP_CATEGORY => 'Category',
            default => 'Product',
        };

        return [
            ['key' => 'name', 'label' => $nameLabel],
            ...($groupBy === self::GROUP_PRODUCT
                ? [['key' => 'code', 'label' => 'Code']]
                : []),
            ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
            ['key' => 'sales', 'label' => 'Sales', 'align' => 'right'],
            ['key' => 'discount', 'label' => 'Discount', 'align' => 'right'],
            ['key' => 'discount_pct', 'label' => 'Discount %', 'align' => 'right'],
            ['key' => 'vat', 'label' => 'VAT', 'align' => 'right'],
            ['key' => 'vat_pct', 'label' => 'VAT %', 'align' => 'right'],
            ['key' => 'cost', 'label' => 'Cost', 'align' => 'right'],
            ['key' => 'profit', 'label' => 'Profit', 'align' => 'right'],
            ['key' => 'margin', 'label' => 'Profit %', 'align' => 'right'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{quantity: float, sales: float, discount: float, discount_pct: float, vat: float, vat_pct: float, cost: float, profit: float, margin: float}
     */
    private function totalsFromRows(array $rows): array
    {
        $sales = round(collect($rows)->sum('sales'), 2);
        $discount = round(collect($rows)->sum('discount'), 2);
        $vat = round(collect($rows)->sum('vat'), 2);
        $cost = round(collect($rows)->sum('cost'), 2);
        $profit = round($sales - $cost, 2);

        return [
            'quantity' => round(collect($rows)->sum('quantity'), 2),
            'sales' => $sales,
            'discount' => $discount,
            'discount_pct' => $this->percentOf($discount, $sales + $discount),
            'vat' => $vat,
            'vat_pct' => $this->percentOf($vat, $sales),
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $this->percentOf($profit, $sales),
        ];
    }

    private function percentOf(float $part, float $whole): float
    {
        if ($whole <= 0) {
            return 0.0;
        }

        return round(($part / $whole) * 100, 1);
    }
}
