<?php

namespace App\Enums;

use Carbon\Carbon;

enum DashboardSalesPeriod: string
{
    case LastYear = 'last_year';
    case ThisYear = 'this_year';
    case CurrentMonth = 'current_month';
    case PreviousMonth = 'previous_month';
    case PreviousWeek = 'previous_week';
    case Last7Days = 'last_7_days';
    case CustomRange = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::LastYear => 'Last Year',
            self::ThisYear => 'This Year',
            self::CurrentMonth => 'Current Month',
            self::PreviousMonth => 'Previous Month',
            self::PreviousWeek => 'Previous Week',
            self::Last7Days => 'Last 7 Days',
            self::CustomRange => 'Custom Range',
        };
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function dateRange(?Carbon $today = null): array
    {
        $today = ($today ?? Carbon::today())->copy();

        return match ($this) {
            self::ThisYear => [
                'from' => $today->copy()->startOfYear(),
                'to' => $today,
            ],
            self::LastYear => [
                'from' => $today->copy()->subYear()->startOfYear(),
                'to' => $today->copy()->subYear()->endOfYear(),
            ],
            self::CurrentMonth => [
                'from' => $today->copy()->startOfMonth(),
                'to' => $today,
            ],
            self::PreviousMonth => [
                'from' => $today->copy()->subMonthNoOverflow()->startOfMonth(),
                'to' => $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            self::PreviousWeek => [
                'from' => $today->copy()->subWeek()->startOfWeek(),
                'to' => $today->copy()->subWeek()->endOfWeek(),
            ],
            self::Last7Days => [
                'from' => $today->copy()->subDays(6),
                'to' => $today,
            ],
            self::CustomRange => throw new \InvalidArgumentException('Custom range dates must be provided explicitly.'),
        };
    }

    public static function tryFromInput(?string $value): self
    {
        if ($value === null) {
            return self::CurrentMonth;
        }

        return self::tryFrom($value) ?? self::CurrentMonth;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $period): array => [
                'value' => $period->value,
                'label' => $period->label(),
            ],
            self::cases(),
        );
    }
}
