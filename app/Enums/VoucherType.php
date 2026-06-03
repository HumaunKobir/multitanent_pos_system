<?php

namespace App\Enums;

enum VoucherType: int
{
    case Journal = 1;
    case Income = 2;
    case Expense = 3;
    case Contra = 4;

    public function label(): string
    {
        return match ($this) {
            self::Journal => 'Journal',
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Contra => 'Contra',
        };
    }

    public function slug(): string
    {
        return match ($this) {
            self::Journal => 'journal',
            self::Income => 'income',
            self::Expense => 'expense',
            self::Contra => 'contra',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Journal => 'JV-',
            self::Income => 'INC-',
            self::Expense => 'EXP-',
            self::Contra => 'CONT-',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        return match ($slug) {
            'journal' => self::Journal,
            'income' => self::Income,
            'expense' => self::Expense,
            'contra' => self::Contra,
            default => null,
        };
    }

    /**
     * @return array<int, array{id: int, slug: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'slug' => $case->slug(),
            'name' => $case->label(),
        ], self::cases());
    }
}
