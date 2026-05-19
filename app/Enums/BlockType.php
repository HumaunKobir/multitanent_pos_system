<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum BlockType: int
{
    use Commons;

    case Image = 1;
    case Item = 2;

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'value' => $case->value,
                'label' => $case->name,
            ])
            ->values()
            ->all();
    }
}
