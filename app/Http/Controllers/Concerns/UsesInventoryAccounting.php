<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChartOfAccount;
use App\Models\Sell;
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

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     effective_paid: float,
     *     due_amount: float,
     *     change_amount: float,
     *     payment_lines: array<int, array{payment_account_id: int, amount: float}>
     * }
     */
    protected function resolveSalePayments(array $data, float $netAmount): array
    {
        $netAmount = round(max(0, $netAmount), 2);
        $paymentLines = $this->normalizeSalePaymentLines($data);

        if ($paymentLines !== []) {
            $effectivePaid = round(array_sum(array_column($paymentLines, 'amount')), 2);

            if ($effectivePaid > $netAmount) {
                throw ValidationException::withMessages([
                    'payments' => 'Total payment amount cannot exceed the net payable.',
                ]);
            }

            return [
                'effective_paid' => $effectivePaid,
                'due_amount' => round(max(0, $netAmount - $effectivePaid), 2),
                'change_amount' => 0.0,
                'payment_lines' => $paymentLines,
            ];
        }

        $payment = $this->resolveSalePaymentAmounts($netAmount, (float) ($data['paid_amount'] ?? 0));
        $legacyLines = [];

        if ($payment['effective_paid'] > 0) {
            $legacyLines[] = [
                'payment_account_id' => $this->resolvePaymentAccountIdFromData($data, $payment['effective_paid']),
                'amount' => $payment['effective_paid'],
            ];
        }

        return [
            'effective_paid' => $payment['effective_paid'],
            'due_amount' => $payment['due_amount'],
            'change_amount' => $payment['change_amount'],
            'payment_lines' => $legacyLines,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{payment_account_id: int, amount: float}>
     */
    protected function normalizeSalePaymentLines(array $data): array
    {
        $rawPayments = $data['payments'] ?? null;

        if (! is_array($rawPayments) || $rawPayments === []) {
            return [];
        }

        $lines = [];

        foreach ($rawPayments as $index => $payment) {
            if (! is_array($payment)) {
                continue;
            }

            $accountId = (int) ($payment['payment_account_id'] ?? 0);
            $amount = round(max(0, (float) ($payment['amount'] ?? 0)), 2);

            if ($accountId <= 0 || $amount <= 0) {
                continue;
            }

            $this->assertValidPaymentAccountId($accountId, "payments.{$index}.payment_account_id");

            $lines[] = [
                'payment_account_id' => $accountId,
                'amount' => $amount,
            ];
        }

        if ($lines === []) {
            return [];
        }

        return $lines;
    }

    protected function resolvePaymentAccountId(Request $request, float $paidAmount): ?int
    {
        if ($paidAmount <= 0) {
            return null;
        }

        return $this->requirePaymentAccountId($request);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolvePaymentAccountIdFromData(array $data, float $paidAmount): int
    {
        $paymentAccountId = (int) ($data['payment_account_id'] ?? 0);

        if ($paymentAccountId <= 0) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'Payment account is required.',
            ]);
        }

        $this->assertValidPaymentAccountId($paymentAccountId, 'payment_account_id');

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

        $this->assertValidPaymentAccountId($paymentAccountId, 'payment_account_id');

        return $paymentAccountId;
    }

    protected function assertValidPaymentAccountId(int $paymentAccountId, string $field): void
    {
        $isValidPaymentAccount = ChartOfAccount::query()
            ->paymentAccount()
            ->whereKey($paymentAccountId)
            ->exists();

        if (! $isValidPaymentAccount) {
            throw ValidationException::withMessages([
                $field => 'Account must be an active cash or bank account.',
            ]);
        }
    }

    /**
     * @param  array<int, array{payment_account_id: int, amount: float}>  $paymentLines
     */
    protected function syncSellPayments(Sell $sell, array $paymentLines): void
    {
        $sell->payments()->delete();

        foreach ($paymentLines as $paymentLine) {
            $sell->payments()->create([
                'payment_account_id' => $paymentLine['payment_account_id'],
                'amount' => $paymentLine['amount'],
            ]);
        }
    }
}
