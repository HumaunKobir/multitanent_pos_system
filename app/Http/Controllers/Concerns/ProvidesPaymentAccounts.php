<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Models\ChartOfAccount;

trait ProvidesPaymentAccounts
{
    /**
     * @return array<int, array{id: int, code: string, name: string, label: string}>
     */
    protected function paymentAccounts(): array
    {
        return ChartOfAccount::query()
            ->where('type', AccountType::Asset)
            ->whereNotNull('parent_id')
            ->where('status', CommonStatus::Active)
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
}
