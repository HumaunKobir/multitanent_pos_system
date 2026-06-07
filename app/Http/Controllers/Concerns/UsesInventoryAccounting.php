<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait UsesInventoryAccounting
{
    protected function resolvePaymentAccountId(Request $request, float $paidAmount): ?int
    {
        if ($paidAmount <= 0) {
            return null;
        }

        return $this->requirePaymentAccountId($request);
    }

    protected function requirePaymentAccountId(Request $request): int
    {
        $paymentAccountId = $request->integer('payment_account_id');

        if ($paymentAccountId <= 0) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Payment account is required.',
            ]);
        }

        $isValidPaymentAccount = ChartOfAccount::query()
            ->paymentAccount()
            ->whereKey($paymentAccountId)
            ->exists();

        if (! $isValidPaymentAccount) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Account must be an active cash or bank account.',
            ]);
        }

        return $paymentAccountId;
    }
}
