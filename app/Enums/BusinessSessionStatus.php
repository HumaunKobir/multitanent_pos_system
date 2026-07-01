<?php

namespace App\Enums;

enum BusinessSessionStatus: int
{
    case Open = 1;
    case ClosingPending = 2;
    case Closed = 3;
    case Reopened = 4;
    case Cancelled = 5;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::ClosingPending => 'Closing Pending',
            self::Closed => 'Closed',
            self::Reopened => 'Reopened',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Reopened, self::ClosingPending], true);
    }
}
