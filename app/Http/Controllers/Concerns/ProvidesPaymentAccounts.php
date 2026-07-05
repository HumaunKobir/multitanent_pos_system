<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChartOfAccount;
use App\Services\BranchPaymentAccountService;

trait ProvidesPaymentAccounts
{
    /**
     * @return array<int, array{id: int, code: string, name: string, label: string}>
     */
    protected function paymentAccounts(): array
    {
        return $this->paymentAccountsForBranch(null);
    }

    /**
     * @return array<int, array{id: int, code: string, name: string, label: string}>
     */
    protected function paymentAccountsForBranch(?int $branchId): array
    {
        return BranchPaymentAccountService::listForBranch($branchId)
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'label' => "{$account->code} — {$account->name}",
            ])
            ->values()
            ->all();
    }

    protected function paymentAccountIsValidForBranch(?int $paymentAccountId, ?int $branchId): bool
    {
        return BranchPaymentAccountService::isValid($paymentAccountId, $branchId);
    }
}
