<?php

namespace App\Enums;

enum BusinessSessionOpeningMethod: int
{
    case StartedDuringLogin = 1;
    case ManualFromPanel = 2;

    public function label(): string
    {
        return match ($this) {
            self::StartedDuringLogin => 'Started During Login',
            self::ManualFromPanel => 'Manual From Panel',
        };
    }
}
