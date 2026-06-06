<?php

namespace App\Services;

use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\ChartOfAccount;

class SystemAccountService
{
    /**
     * @var array<string, ChartOfAccount>
     */
    private static array $resolved = [];

    public static function seed(): void
    {
        ChartOfAccount::$skipCodeGeneration = true;

        try {
            $heads = [
                SystemAccountKey::CurrentAssets,
                SystemAccountKey::CurrentLiabilities,
                SystemAccountKey::Equity,
                SystemAccountKey::Income,
                SystemAccountKey::Expenses,
            ];

            foreach ($heads as $headKey) {
                self::ensureAccount($headKey, null);
            }

            $leaves = [
                SystemAccountKey::Inventory,
                SystemAccountKey::InputVat,
                SystemAccountKey::AccountsReceivable,
                SystemAccountKey::AccountsPayable,
                SystemAccountKey::OutputVat,
                SystemAccountKey::OpeningBalanceEquity,
                SystemAccountKey::SalesRevenue,
                SystemAccountKey::SalesReturns,
                SystemAccountKey::CostOfGoodsSold,
                SystemAccountKey::InventoryDamage,
            ];

            foreach ($leaves as $leafKey) {
                $parentKey = $leafKey->parentKey();
                $parent = $parentKey !== null
                    ? self::findByKey($parentKey)
                    : null;
                self::ensureAccount($leafKey, $parent);
            }
        } finally {
            ChartOfAccount::$skipCodeGeneration = false;
            self::$resolved = [];
        }
    }

    public static function resolve(SystemAccountKey $key): ChartOfAccount
    {
        $cacheKey = $key->value;

        if (isset(self::$resolved[$cacheKey])) {
            return self::$resolved[$cacheKey];
        }

        $account = self::findByKey($key);

        if ($account === null) {
            self::seed();
            $account = self::findByKey($key);
        }

        if ($account === null) {
            throw new \RuntimeException("System account not found: {$key->value}");
        }

        self::$resolved[$cacheKey] = $account;

        return $account;
    }

    private static function findByKey(SystemAccountKey $key): ?ChartOfAccount
    {
        return ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->where('is_system', true)
            ->first();
    }

    public static function id(SystemAccountKey $key): int
    {
        return self::resolve($key)->id;
    }

    private static function ensureAccount(SystemAccountKey $key, ?ChartOfAccount $parent): ChartOfAccount
    {
        $existing = ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return ChartOfAccount::create([
            'parent_id' => $parent?->id,
            'code' => self::fixedCode($key),
            'account_number' => $key->accountNumber(),
            'name' => $key->defaultName(),
            'type' => $key->accountType(),
            'current_balance' => 0,
            'status' => CommonStatus::Active,
            'is_system' => true,
        ]);
    }

    private static function fixedCode(SystemAccountKey $key): string
    {
        return match ($key) {
            SystemAccountKey::CurrentAssets => 'A001',
            SystemAccountKey::Inventory => 'A001-01',
            SystemAccountKey::InputVat => 'A001-02',
            SystemAccountKey::AccountsReceivable => 'A001-03',
            SystemAccountKey::CurrentLiabilities => 'L001',
            SystemAccountKey::AccountsPayable => 'L001-01',
            SystemAccountKey::OutputVat => 'L001-02',
            SystemAccountKey::Equity => 'E001',
            SystemAccountKey::OpeningBalanceEquity => 'E001-01',
            SystemAccountKey::Income => 'I001',
            SystemAccountKey::SalesRevenue => 'I001-01',
            SystemAccountKey::SalesReturns => 'I001-02',
            SystemAccountKey::Expenses => 'X001',
            SystemAccountKey::CostOfGoodsSold => 'X001-01',
            SystemAccountKey::InventoryDamage => 'X001-02',
        };
    }
}
