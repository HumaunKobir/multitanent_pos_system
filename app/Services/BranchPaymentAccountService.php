<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use Illuminate\Support\Collection;

class BranchPaymentAccountService
{
    public static function find(?int $accountId, ?int $branchId): ?ChartOfAccount
    {
        if ($accountId === null) {
            return null;
        }

        $account = ChartOfAccount::query()->whereKey($accountId)->first();

        if ($account === null || ! self::isActiveAsset($account)) {
            return null;
        }

        if (self::belongsToBranchCashAndBank($account, $branchId)) {
            return $account;
        }

        if ($branchId !== null && self::belongsToGlobalCashAndBank($account)) {
            return $account;
        }

        return null;
    }

    /**
     * @return Collection<int, ChartOfAccount>
     */
    public static function listForBranch(?int $branchId): Collection
    {
        SystemAccountService::ensureConfigured($branchId);

        $accounts = ChartOfAccount::query()
            ->where('type', AccountType::Asset)
            ->where('status', CommonStatus::Active)
            ->where('parent_id', SystemAccountService::id(SystemAccountKey::CashAndBank, $branchId))
            ->when(
                $branchId === null,
                fn ($query) => $query->whereNull('source_type')->whereNull('source_id'),
                fn ($query) => $query->where('source_type', Branch::class)->where('source_id', $branchId),
            )
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        if ($branchId !== null) {
            SystemAccountService::ensureConfigured(null);

            $globalAccounts = ChartOfAccount::query()
                ->whereNull('source_type')
                ->whereNull('source_id')
                ->where('type', AccountType::Asset)
                ->where('status', CommonStatus::Active)
                ->where('parent_id', SystemAccountService::id(SystemAccountKey::CashAndBank, null))
                ->orderBy('code')
                ->get(['id', 'code', 'name']);

            $accounts = $accounts
                ->concat($globalAccounts)
                ->unique('id')
                ->sortBy('code')
                ->values();
        }

        return $accounts;
    }

    public static function isValid(?int $accountId, ?int $branchId): bool
    {
        return self::find($accountId, $branchId) !== null;
    }

    private static function isActiveAsset(ChartOfAccount $account): bool
    {
        return $account->type === AccountType::Asset
            && $account->status === CommonStatus::Active;
    }

    private static function belongsToBranchCashAndBank(ChartOfAccount $account, ?int $branchId): bool
    {
        SystemAccountService::ensureConfigured($branchId);

        return $account->parent_id === SystemAccountService::id(SystemAccountKey::CashAndBank, $branchId);
    }

    private static function belongsToGlobalCashAndBank(ChartOfAccount $account): bool
    {
        SystemAccountService::ensureConfigured(null);

        return $account->parent_id === SystemAccountService::id(SystemAccountKey::CashAndBank, null);
    }
}
