<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait UsesInventoryAccounting
{
    protected function resolvePaymentAccountId(Request $request, float $paidAmount): ?int
    {
        if ($paidAmount <= 0) {
            return null;
        }

        $paymentAccountId = $request->integer('payment_account_id');

        if ($paymentAccountId <= 0) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Payment account is required when paid amount is greater than zero.',
            ]);
        }

        return $paymentAccountId;
    }

    protected function requirePaymentAccountId(Request $request): int
    {
        $paymentAccountId = $request->integer('payment_account_id');

        if ($paymentAccountId <= 0) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Payment account is required.',
            ]);
        }

        return $paymentAccountId;
    }
}
