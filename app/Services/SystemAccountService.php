<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
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

        if (isset(self::$configuredBranches[$cacheKey])) {
            return;
        }

        // Always seed so newly added system keys (e.g. Discount Applied) are created
        // on charts that already have Cash & Bank. seed() is idempotent.
        self::seed($branchId);
        self::$configuredBranches[$cacheKey] = true;
    }

    public static function seed(?int $branchId = null): void
    {
        self::$seedBranchId = $branchId ?? Auth::user()?->branch_id;

        ChartOfAccount::$skipCodeGeneration = true;

        try {
            self::migrateRenamedAccountNumbers();
            self::migrateDiscountAccountsToContraRevenue();
            self::migrateTaxesPaidToContraLiability();
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

    /**
     * Seed the global chart and every branch panel chart that already exists.
     */
    public static function seedAllPanels(): void
    {
        self::seed(null);

        $mainBranch = Branch::query()->where('name', Branch::MAIN_BRANCH_NAME)->first();
        if ($mainBranch) {
            ChartOfAccount::query()
                ->where('source_type', Branch::class)
                ->where('source_id', $mainBranch->id)
                ->delete();
        }

        $branchIds = ChartOfAccount::query()
            ->where('source_type', Branch::class)
            ->whereNotNull('source_id')
            ->when($mainBranch, fn ($q) => $q->where('source_id', '!=', $mainBranch->id))
            ->distinct()
            ->orderBy('source_id')
            ->pluck('source_id');

        foreach ($branchIds as $branchId) {
            self::seed((int) $branchId);
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
            // Force a full seed for this panel so missing keys are created even when
            // Cash & Bank already exists (ensureConfigured used to skip in that case).
            unset(self::$configuredBranches[$branchId ?? 'global']);
            self::seed($branchId);
            self::$configuredBranches[$branchId ?? 'global'] = true;
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
        $branchId = self::branchId();
        $isSuperAdmin = $branchId === null || Branch::isMainBranch($branchId);

        if ($isSuperAdmin) {
            // SuperAdmin global panel: only SaaS platform & billing accounts
            return [
                SystemAccountKey::CashAndBank,
                SystemAccountKey::CashInHand,
                SystemAccountKey::SslCommerz,
                SystemAccountKey::Bkash,
                SystemAccountKey::Nagad,
                SystemAccountKey::AccountsReceivable,
                SystemAccountKey::SubscriptionReceivable,
                SystemAccountKey::LoansPayable,
                SystemAccountKey::TaxesPayable,
                SystemAccountKey::OutputVat,
                SystemAccountKey::TaxesPaid,
                SystemAccountKey::OwnersCapital,
                SystemAccountKey::RetainedEarnings,
                SystemAccountKey::CurrentYearEarnings,
                SystemAccountKey::OwnersDrawings,
                SystemAccountKey::OpeningBalanceEquity,
                SystemAccountKey::OpeningBalanceClearing,
                SystemAccountKey::SalesRevenue,
                SystemAccountKey::SubscriptionIncome,
                SystemAccountKey::OtherIncome,
                SystemAccountKey::Expenses,
                SystemAccountKey::RentExpense,
                SystemAccountKey::SalaryExpense,
                SystemAccountKey::UtilitiesExpense,
            ];
        }

        // Branch retail POS panel: full retail store tree + subscription payable & expense
        return [
            SystemAccountKey::CashAndBank,
            SystemAccountKey::CashInHand,
            SystemAccountKey::SslCommerz,
            SystemAccountKey::Bkash,
            SystemAccountKey::Nagad,
            SystemAccountKey::Inventory,
            SystemAccountKey::ProductInventory,
            SystemAccountKey::AccountsReceivable,
            SystemAccountKey::CustomerReceivables,
            SystemAccountKey::IntercompanyReceivable,
            SystemAccountKey::AccountsPayable,
            SystemAccountKey::SupplierPayables,
            SystemAccountKey::IntercompanyPayable,
            SystemAccountKey::SubscriptionPayable,
            SystemAccountKey::LoansPayable,
            SystemAccountKey::AdvanceFromCustomer,
            SystemAccountKey::CustomerCoinPayable,
            SystemAccountKey::TaxesPayable,
            SystemAccountKey::OutputVat,
            SystemAccountKey::TaxesPaid,
            SystemAccountKey::OwnersCapital,
            SystemAccountKey::RetainedEarnings,
            SystemAccountKey::CurrentYearEarnings,
            SystemAccountKey::OwnersDrawings,
            SystemAccountKey::OpeningBalanceEquity,
            SystemAccountKey::OpeningBalanceClearing,
            SystemAccountKey::SalesRevenue,
            SystemAccountKey::ProductSales,
            SystemAccountKey::SalesReturns,
            SystemAccountKey::DiscountApplied,
            SystemAccountKey::CoinDiscountApplied,
            SystemAccountKey::OtherIncome,
            SystemAccountKey::Expenses,
            SystemAccountKey::CostOfGoodsSold,
            SystemAccountKey::InventoryDamage,
            SystemAccountKey::StockAdjustmentGain,
            SystemAccountKey::StockAdjustmentLoss,
            SystemAccountKey::RentExpense,
            SystemAccountKey::SalaryExpense,
            SystemAccountKey::UtilitiesExpense,
            SystemAccountKey::SubscriptionExpense,
        ];
    }

    private static function applyPanelSource($query, ?int $branchId): void
    {
        if ($branchId === null || Branch::isMainBranch($branchId)) {
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
        $branchId = self::branchId();
        if ($branchId !== null && ! Branch::isMainBranch($branchId)) {
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

        $branchId = self::branchId();
        if ($branchId !== null && ! Branch::isMainBranch($branchId)) {
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
            SystemAccountKey::PurchaseReturns->accountNumber(),
            'SYS:input_vat',
            'SYS:current_liabilities',
            'SYS:equity',
            'SYS:income',
        ];

        $branchId = self::branchId();
        $isSuperAdmin = $branchId === null || Branch::isMainBranch($branchId);

        // SuperAdmin global panel does not use retail POS inventory, purchases, supplier/customer payables/receivables
        if ($isSuperAdmin) {
            $retiredAccountNumbers = array_merge($retiredAccountNumbers, [
                SystemAccountKey::Inventory->accountNumber(),
                SystemAccountKey::ProductInventory->accountNumber(),
                SystemAccountKey::CustomerReceivables->accountNumber(),
                SystemAccountKey::IntercompanyReceivable->accountNumber(),
                SystemAccountKey::AccountsPayable->accountNumber(),
                SystemAccountKey::SupplierPayables->accountNumber(),
                SystemAccountKey::IntercompanyPayable->accountNumber(),
                SystemAccountKey::SubscriptionPayable->accountNumber(),
                SystemAccountKey::AdvanceFromCustomer->accountNumber(),
                SystemAccountKey::CustomerCoinPayable->accountNumber(),
                SystemAccountKey::ProductSales->accountNumber(),
                SystemAccountKey::SalesReturns->accountNumber(),
                SystemAccountKey::DiscountApplied->accountNumber(),
                SystemAccountKey::CoinDiscountApplied->accountNumber(),
                SystemAccountKey::CostOfGoodsSold->accountNumber(),
                SystemAccountKey::InventoryDamage->accountNumber(),
                SystemAccountKey::StockAdjustmentGain->accountNumber(),
                SystemAccountKey::StockAdjustmentLoss->accountNumber(),
                SystemAccountKey::SubscriptionExpense->accountNumber(),
            ]);
        } else {
            // Branch panel does not use SuperAdmin subscription income and subscription receivable
            $retiredAccountNumbers = array_merge($retiredAccountNumbers, [
                SystemAccountKey::SubscriptionIncome->accountNumber(),
                SystemAccountKey::SubscriptionReceivable->accountNumber(),
            ]);
        }

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
            SystemAccountKey::Bkash => 'A001-03',
            SystemAccountKey::Nagad => 'A001-04',
            SystemAccountKey::Inventory => 'A002',
            SystemAccountKey::ProductInventory => 'A002-01',
            SystemAccountKey::AccountsReceivable => 'A003',
            SystemAccountKey::CustomerReceivables => 'A003-01',
            SystemAccountKey::IntercompanyReceivable => 'A003-02',
            SystemAccountKey::SubscriptionReceivable => 'A003-03',
            SystemAccountKey::AccountsPayable => 'L001',
            SystemAccountKey::SupplierPayables => 'L001-01',
            SystemAccountKey::IntercompanyPayable => 'L001-02',
            SystemAccountKey::SubscriptionPayable => 'L001-03',
            SystemAccountKey::LoansPayable => 'L002',
            SystemAccountKey::AdvanceFromCustomer => 'L003',
            SystemAccountKey::CustomerCoinPayable => 'L005',
            SystemAccountKey::TaxesPayable => 'L004',
            SystemAccountKey::OutputVat => 'L004-01',
            SystemAccountKey::TaxesPaid => 'L004-02',
            SystemAccountKey::OwnersCapital => 'E001',
            SystemAccountKey::RetainedEarnings => 'E002',
            SystemAccountKey::CurrentYearEarnings => 'E002-01',
            SystemAccountKey::OwnersDrawings => 'E003',
            SystemAccountKey::OpeningBalanceEquity => 'E004',
            SystemAccountKey::OpeningBalanceClearing => 'E004-01',
            SystemAccountKey::SalesRevenue => 'I001',
            SystemAccountKey::ProductSales => 'I001-01',
            SystemAccountKey::SalesReturns => 'I001-02',
            SystemAccountKey::DiscountApplied => 'I001-03',
            SystemAccountKey::CoinDiscountApplied => 'I001-04',
            SystemAccountKey::SubscriptionIncome => 'I001-05',
            SystemAccountKey::OtherIncome => 'I002',
            SystemAccountKey::StockAdjustmentGain => 'I002-01',
            SystemAccountKey::Expenses => 'X001',
            SystemAccountKey::CostOfGoodsSold => 'X001-01',
            SystemAccountKey::InventoryDamage => 'X001-02',
            SystemAccountKey::PurchaseReturns => 'X001-03',
            SystemAccountKey::RentExpense => 'X001-04',
            SystemAccountKey::SalaryExpense => 'X001-05',
            SystemAccountKey::UtilitiesExpense => 'X001-06',
            SystemAccountKey::StockAdjustmentLoss => 'X001-07',
            SystemAccountKey::SubscriptionExpense => 'X001-08',
        };
    }

    /**
     * Taxes Paid used to be an expense (or soft-deleted). Restore/reseed it as a
     * contra-liability under Taxes Payable so the parent nets collected − paid.
     */
    private static function migrateTaxesPaidToContraLiability(): void
    {
        $query = ChartOfAccount::withTrashed()
            ->where('account_number', SystemAccountKey::TaxesPaid->accountNumber())
            ->where('is_system', true);

        if (self::branchId() !== null) {
            self::applyPanelSource($query, self::branchId());
        }

        $taxesPayableId = self::findByKey(SystemAccountKey::TaxesPayable)?->id;

        foreach ($query->orderBy('id')->get() as $account) {
            if ($account->trashed()) {
                $account->restore();
            }

            $wasExpense = $account->type === AccountType::Expenses;
            $balance = round((float) $account->current_balance, 2);

            if ($wasExpense && abs($balance) >= 0.005) {
                // Expense debit left a positive balance; liability debit should be negative.
                self::flipDiscountAccountBalances($account);
            }

            $account->update([
                'type' => AccountType::Liability,
                'parent_id' => $taxesPayableId ?? $account->parent_id,
                'code' => self::fixedCode(SystemAccountKey::TaxesPaid),
                'name' => SystemAccountKey::TaxesPaid->defaultName(),
                'is_system' => true,
            ]);
        }
    }

    /**
     * Discount Applied used to live under Expenses (debit increases balance).
     * As contra-revenue under Sales Revenue (Income), debit decreases balance.
     * Flip leftover expense-style positive balances (including accounts whose
     * type was already changed to Income without flipping).
     */
    private static function migrateDiscountAccountsToContraRevenue(): void
    {
        foreach ([SystemAccountKey::DiscountApplied, SystemAccountKey::CoinDiscountApplied] as $key) {
            $query = ChartOfAccount::query()
                ->where('account_number', $key->accountNumber())
                ->where('is_system', true);

            // Branch seed: that panel only. Global seed: every panel.
            if (self::branchId() !== null) {
                self::applyPanelSource($query, self::branchId());
            }

            foreach ($query->orderBy('id')->get() as $account) {
                if ($account->type === AccountType::Expenses) {
                    self::flipDiscountAccountBalances($account);
                    $account->update(['type' => AccountType::Income]);

                    continue;
                }

                if ($account->type !== AccountType::Income) {
                    continue;
                }

                $debit = round((float) Ledger::query()->where('account_id', $account->id)->sum('debit'), 2);
                $credit = round((float) Ledger::query()->where('account_id', $account->id)->sum('credit'), 2);
                $balance = round((float) $account->current_balance, 2);

                // Debit-heavy contra-revenue must not sit as a positive income balance.
                if (($debit - $credit) > 0.005 && $balance > 0.005) {
                    self::flipDiscountAccountBalances($account);
                }
            }
        }
    }

    private static function flipDiscountAccountBalances(ChartOfAccount $account): void
    {
        $account->update([
            'current_balance' => round(-1 * (float) $account->current_balance, 2),
        ]);

        Ledger::query()
            ->where('account_id', $account->id)
            ->orderBy('id')
            ->each(function (Ledger $ledger): void {
                $ledger->update([
                    'opening_balance' => round(-1 * (float) $ledger->opening_balance, 2),
                    'closing_balance' => round(-1 * (float) $ledger->closing_balance, 2),
                ]);
            });
    }
}
