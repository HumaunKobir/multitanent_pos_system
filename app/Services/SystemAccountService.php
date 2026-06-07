<?php

namespace App\Services;

use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Auth;

class SystemAccountService
{
    /**
     * @var array<string, ChartOfAccount>
     */
    private static array $resolved = [];

    private static ?int $seedBranchId = null;

    /**
     * @var array<string, true>
     */
    private static array $configuredBranches = [];

    public static function isConfigured(?int $branchId = null): bool
    {
        return self::findByKey(SystemAccountKey::CashAndBank, $branchId) !== null;
    }

    public static function ensureConfigured(?int $branchId = null): void
    {
        $branchId ??= Auth::user()?->branch_id;
        $cacheKey = ($branchId ?? 'global');

        if (isset(self::$configuredBranches[$cacheKey]) || self::isConfigured($branchId)) {
            self::$configuredBranches[$cacheKey] = true;

            return;
        }

        self::seed($branchId);
        self::$configuredBranches[$cacheKey] = true;
    }

    public static function seed(?int $branchId = null): void
    {
        self::$seedBranchId = $branchId ?? Auth::user()?->branch_id;

        ChartOfAccount::$skipCodeGeneration = true;

        try {
            self::migrateRenamedAccountNumbers();
            self::deduplicateSystemAccounts();

            foreach (self::orderedKeys() as $key) {
                $parentKey = $key->parentKey();
                $parent = $parentKey !== null ? self::findByKey($parentKey) : null;
                self::ensureAccount($key, $parent);
            }

            self::syncAccountStructure();
            self::removeRetiredAccounts();
            self::deduplicateSystemAccounts();
        } finally {
            ChartOfAccount::$skipCodeGeneration = false;
            self::$resolved = [];
            self::$seedBranchId = null;
        }
    }

    public static function resolve(SystemAccountKey $key, ?int $branchId = null): ChartOfAccount
    {
        $branchId ??= Auth::user()?->branch_id;
        $cacheKey = self::cacheKey($branchId, $key);

        if (isset(self::$resolved[$cacheKey])) {
            return self::$resolved[$cacheKey];
        }

        $account = self::findByKey($key, $branchId);

        if ($account === null) {
            self::ensureConfigured($branchId);
            $account = self::findByKey($key, $branchId);
        }

        if ($account === null) {
            throw new \RuntimeException("System account not found: {$key->value}");
        }

        self::$resolved[$cacheKey] = $account;

        return $account;
    }

    public static function id(SystemAccountKey $key, ?int $branchId = null): int
    {
        return self::resolve($key, $branchId)->id;
    }

    private static function cacheKey(?int $branchId, SystemAccountKey $key): string
    {
        return ($branchId ?? 'global').':'.$key->value;
    }

    private static function branchId(): ?int
    {
        return self::$seedBranchId ?? Auth::user()?->branch_id;
    }

    private static function orderedKeys(): array
    {
        return [
            SystemAccountKey::CashAndBank,
            SystemAccountKey::CashInHand,
            SystemAccountKey::SslCommerz,
            SystemAccountKey::Inventory,
            SystemAccountKey::ProductInventory,
            SystemAccountKey::AccountsReceivable,
            SystemAccountKey::CustomerReceivables,
            SystemAccountKey::AccountsPayable,
            SystemAccountKey::SupplierPayables,
            SystemAccountKey::LoansPayable,
            SystemAccountKey::AdvanceFromCustomer,
            SystemAccountKey::TaxesPayable,
            SystemAccountKey::OutputVat,
            SystemAccountKey::OwnersCapital,
            SystemAccountKey::RetainedEarnings,
            SystemAccountKey::OwnersDrawings,
            SystemAccountKey::OpeningBalanceEquity,
            SystemAccountKey::OpeningBalanceClearing,
            SystemAccountKey::SalesRevenue,
            SystemAccountKey::ProductSales,
            SystemAccountKey::SalesReturns,
            SystemAccountKey::OtherIncome,
            SystemAccountKey::Expenses,
            SystemAccountKey::CostOfGoodsSold,
            SystemAccountKey::InventoryDamage,
            SystemAccountKey::PurchaseReturns,
            SystemAccountKey::RentExpense,
            SystemAccountKey::SalaryExpense,
            SystemAccountKey::UtilitiesExpense,
        ];
    }

    private static function applyPanelSource($query, ?int $branchId): void
    {
        if ($branchId === null) {
            $query->whereNull('source_type')->whereNull('source_id');

            return;
        }

        $query->where('source_type', Branch::class)->where('source_id', $branchId);
    }

    private static function findByKey(SystemAccountKey $key, ?int $branchId = null): ?ChartOfAccount
    {
        $branchId ??= self::branchId();

        $query = ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->where('is_system', true);

        self::applyPanelSource($query, $branchId);

        return $query->orderBy('id')->first();
    }

    private static function ensureAccount(SystemAccountKey $key, ?ChartOfAccount $parent): ChartOfAccount
    {
        $existing = self::findByKey($key);

        if ($existing !== null) {
            return $existing;
        }

        return ChartOfAccount::create([
            ...ChartOfAccount::panelSourceAttributes(self::branchId()),
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

    private static function migrateRenamedAccountNumbers(): void
    {
        if (self::branchId() !== null) {
            return;
        }

        $legacy = ChartOfAccount::query()
            ->where('account_number', 'SYS:customer_deposits')
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->first();

        if ($legacy === null) {
            return;
        }

        $targetExists = ChartOfAccount::query()
            ->where('account_number', SystemAccountKey::AdvanceFromCustomer->accountNumber())
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->exists();

        if ($targetExists) {
            $legacy->delete();

            return;
        }

        $legacy->update([
            'account_number' => SystemAccountKey::AdvanceFromCustomer->accountNumber(),
            'name' => SystemAccountKey::AdvanceFromCustomer->defaultName(),
            'code' => self::fixedCode(SystemAccountKey::AdvanceFromCustomer),
            'is_system' => true,
        ]);
    }

    private static function deduplicateSystemAccounts(): void
    {
        $branchId = self::branchId();

        foreach (SystemAccountKey::cases() as $key) {
            $query = ChartOfAccount::query()
                ->where('account_number', $key->accountNumber())
                ->where('is_system', true);

            self::applyPanelSource($query, $branchId);

            $accounts = $query
                ->withCount('ledgers')
                ->orderBy('id')
                ->get();

            if ($accounts->count() <= 1) {
                continue;
            }

            $keeper = $accounts->sortByDesc('ledgers_count')->first() ?? $accounts->first();

            foreach ($accounts as $account) {
                if ($account->id === $keeper->id) {
                    continue;
                }

                ChartOfAccount::query()
                    ->where('parent_id', $account->id)
                    ->update(['parent_id' => $keeper->id]);

                $account->delete();
            }
        }
    }

    private static function syncAccountStructure(): void
    {
        foreach (self::orderedKeys() as $key) {
            $account = self::findByKey($key);

            if ($account === null) {
                continue;
            }

            $parentKey = $key->parentKey();
            $parentId = $parentKey !== null ? self::findByKey($parentKey)?->id : null;

            $account->update([
                'parent_id' => $parentId,
                'code' => self::fixedCode($key),
                'name' => $key->defaultName(),
                'type' => $key->accountType(),
                'is_system' => true,
            ]);
        }

        if (self::branchId() !== null) {
            return;
        }

        $currentAssets = ChartOfAccount::query()
            ->where('account_number', 'SYS:current_assets')
            ->whereNull('source_type')
            ->whereNull('source_id')
            ->first();

        if ($currentAssets === null) {
            return;
        }

        ChartOfAccount::query()
            ->where('parent_id', $currentAssets->id)
            ->update(['parent_id' => null]);

        $currentAssets->delete();
    }

    private static function removeRetiredAccounts(): void
    {
        $retiredAccountNumbers = [
            SystemAccountKey::BankAccount->accountNumber(),
            SystemAccountKey::BranchInventory->accountNumber(),
            SystemAccountKey::Bkash->accountNumber(),
            SystemAccountKey::Nagad->accountNumber(),
            'SYS:input_vat',
            'SYS:current_liabilities',
            'SYS:equity',
            'SYS:income',
        ];

        $query = ChartOfAccount::query()->whereIn('account_number', $retiredAccountNumbers);

        self::applyPanelSource($query, self::branchId());

        $query->delete();
    }

    private static function fixedCode(SystemAccountKey $key): string
    {
        return match ($key) {
            SystemAccountKey::CashAndBank => 'A001',
            SystemAccountKey::CashInHand => 'A001-01',
            SystemAccountKey::SslCommerz => 'A001-02',
            SystemAccountKey::Inventory => 'A002',
            SystemAccountKey::ProductInventory => 'A002-01',
            SystemAccountKey::AccountsReceivable => 'A003',
            SystemAccountKey::CustomerReceivables => 'A003-01',
            SystemAccountKey::AccountsPayable => 'L001',
            SystemAccountKey::SupplierPayables => 'L001-01',
            SystemAccountKey::LoansPayable => 'L002',
            SystemAccountKey::AdvanceFromCustomer => 'L003',
            SystemAccountKey::TaxesPayable => 'L004',
            SystemAccountKey::OutputVat => 'L004-01',
            SystemAccountKey::OwnersCapital => 'E001',
            SystemAccountKey::RetainedEarnings => 'E002',
            SystemAccountKey::OwnersDrawings => 'E003',
            SystemAccountKey::OpeningBalanceEquity => 'E004',
            SystemAccountKey::OpeningBalanceClearing => 'E004-01',
            SystemAccountKey::SalesRevenue => 'I001',
            SystemAccountKey::ProductSales => 'I001-01',
            SystemAccountKey::SalesReturns => 'I001-02',
            SystemAccountKey::OtherIncome => 'I002',
            SystemAccountKey::Expenses => 'X001',
            SystemAccountKey::CostOfGoodsSold => 'X001-01',
            SystemAccountKey::InventoryDamage => 'X001-02',
            SystemAccountKey::PurchaseReturns => 'X001-03',
            SystemAccountKey::RentExpense => 'X001-04',
            SystemAccountKey::SalaryExpense => 'X001-05',
            SystemAccountKey::UtilitiesExpense => 'X001-06',
        };
    }
}
