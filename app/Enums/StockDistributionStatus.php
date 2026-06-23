<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum StockDistributionStatus: int
{
    use Commons;

    case Pending = 1;
    case Received = 2;
    case PartiallyReceived = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Received => 'Received',
            self::PartiallyReceived => 'Partially Received',
        };
    }
}
