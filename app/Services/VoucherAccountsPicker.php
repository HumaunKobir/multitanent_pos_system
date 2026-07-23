<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\ChartOfAccount;
use Illuminate\Support\Collection;

class VoucherAccountsPicker
{
    /**
     * @return array<int, array{type: string, groups: array<int, array{parent: array{id: int, code: string, name: string}, accounts: array<int, array{id: int, label: string}>}>}>
     */
    public static function forType(VoucherType $type): array
    {
        return match ($type) {
            VoucherType::Journal => self::groupedAllLeafAccounts(),
            VoucherType::Contra => [],
            VoucherType::Expense => self::expenseVoucherAccounts(),
            VoucherType::Income => self::groupedLeafAccounts([AccountType::Income]),
        };
    }

    /**
     * Expense vouchers may debit expense heads or Taxes Paid (to remit collected VAT).
     *
     * @return array<int, array{type: string, groups: array<int, array{parent: array{id: int, code: string, name: string}, accounts: array<int, array{id: int, label: string}>}>}>
     */
    private static function expenseVoucherAccounts(): array
    {
        $accounts = self::leafAccountsQuery()
            ->where(function ($query): void {
                $query->where('type', AccountType::Expenses)
                    ->orWhere('account_number', SystemAccountKey::TaxesPaid->accountNumber());
            })
            ->get();

        return self::buildGroupedPicker($accounts);
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, label: string}>
     */
    public static function assetLeafAccounts(): array
    {
        return ChartOfAccount::query()
            ->paymentAccount()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => "{$account->code} — {$account->name}",
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, AccountType>  $types
     * @return array<int, array{type: string, groups: array<int, array{parent: array{id: int, code: string, name: string}, accounts: array<int, array{id: int, label: string}>}>}>
     */
    private static function groupedLeafAccounts(array $types): array
    {
        $accounts = self::leafAccountsQuery()
            ->whereIn('type', $types)
            ->get();

        return self::buildGroupedPicker($accounts);
    }

    /**
     * @return array<int, array{type: string, groups: array<int, array{parent: array{id: int, code: string, name: string}, accounts: array<int, array{id: int, label: string}>}>}>
     */
    private static function groupedAllLeafAccounts(): array
    {
        $accounts = self::leafAccountsQuery()->get();

        return self::buildGroupedPicker($accounts);
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, label: string}>
     */
    private static function flatAssetLeafAccounts(): array
    {
        return self::assetLeafAccounts();
    }

    private static function leafAccountsQuery()
    {
        return ChartOfAccount::query()
            ->forPanel()
            ->whereNotNull('parent_id')
            ->where('status', CommonStatus::Active)
            ->orderBy('code');
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @return array<int, array{type: string, groups: array<int, array{parent: array{id: int, code: string, name: string}, accounts: array<int, array{id: int, label: string}>}>}>
     */
    private static function buildGroupedPicker(Collection $accounts): array
    {
        $parents = ChartOfAccount::query()
            ->forPanel()
            ->whereIn('id', $accounts->pluck('parent_id')->unique()->filter())
            ->get(['id', 'code', 'name', 'type'])
            ->keyBy('id');

        $byType = [];

        foreach ($accounts as $account) {
            $parent = $parents->get($account->parent_id);
            $typeLabel = $account->type->label();
            $parentKey = $parent ? (string) $parent->id : 'orphan';

            $byType[$typeLabel][$parentKey]['parent'] = $parent
                ? ['id' => $parent->id, 'code' => $parent->code, 'name' => $parent->name]
                : ['id' => 0, 'code' => '', 'name' => 'Other'];
            $byType[$typeLabel][$parentKey]['accounts'][] = [
                'id' => $account->id,
                'label' => "{$account->code} — {$account->name}",
            ];
        }

        $result = [];
        foreach (AccountType::cases() as $typeCase) {
            $typeLabel = $typeCase->label();
            if (! isset($byType[$typeLabel])) {
                continue;
            }
            $groups = array_values($byType[$typeLabel]);
            usort($groups, fn ($a, $b) => strcmp($a['parent']['code'] ?? '', $b['parent']['code'] ?? ''));
            $result[] = ['type' => $typeLabel, 'groups' => $groups];
        }

        return $result;
    }
}
