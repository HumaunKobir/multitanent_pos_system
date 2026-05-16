<?php

namespace App\Enums;

enum AccountType: int
{
    case Asset = 1;
    case Liability = 2;
    case Equity = 3;
    case Income = 4;
    case Expenses = 5;

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Equity => 'Equity',
            self::Income => 'Income',
            self::Expenses => 'Expenses',
        };
    }

    public function slug(): string
    {
        return strtolower($this->name);
        // e.g. Asset -> "asset", Liability -> "liability"
    }

    public static function getAccountTypes(): array
    {
        return array_map(function ($case) {
            return ['id' => $case->value, 'name' => $case->label()];
        }, self::cases());
    }

    public static function fromString(string $accountType): AccountType
    {
        return match ($accountType) {
            'asset' => self::Asset,
            'liability' => self::Liability,
            'equity' => self::Equity,
            'income' => self::Income,
            'expenses' => self::Expenses,
            default => throw new \InvalidArgumentException("Invalid account type: {$accountType}"),
        };
    }
}
