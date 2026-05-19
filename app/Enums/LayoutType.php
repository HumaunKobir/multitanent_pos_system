<?php

namespace App\Enums;

use App\Enums\Traits\Commons;

enum LayoutType: int
{
    use Commons;

    case Image_Block = 1;
    case Slider = 2;

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'value' => $case->value,
                'label' => str_replace('_', ' ', $case->name),
            ])
            ->values()
            ->all();
    }
}
