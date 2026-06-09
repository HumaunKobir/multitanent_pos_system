<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait UsesInventoryAccounting
{
    /**
     * @return array{effective_paid: float, due_amount: float, change_amount: float}
     */
    protected function resolveSalePaymentAmounts(float $netAmount, float $tenderedAmount): array
    {
        $netAmount = round(max(0, $netAmount), 2);
        $tenderedAmount = round(max(0, $tenderedAmount), 2);
        $effectivePaid = round(min($tenderedAmount, $netAmount), 2);

        return [
            'effective_paid' => $effectivePaid,
            'due_amount' => round(max(0, $netAmount - $effectivePaid), 2),
            'change_amount' => round(max(0, $tenderedAmount - $netAmount), 2),
        ];
    }

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
