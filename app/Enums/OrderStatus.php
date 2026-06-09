<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum OrderStatus: int
{
    use Commons;
    case Pending = 1;
    case Processing = 2;
    case Confirmed = 4;
    case Shipping = 3;
    case Delivered = 5;
    case Canceled = 6;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Confirmed => 'Confirmed',
            self::Shipping => 'Shipping',
            self::Delivered => 'Delivered',
            self::Canceled => 'Canceled',
        };
    }

    public function getStatuses(): array
    {
        return array_map(function ($case) {
            return ['id' => $case->value, 'name' => $case->label()];
        }, self::cases());
    }
}
