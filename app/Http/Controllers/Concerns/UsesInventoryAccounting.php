<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ReceivedPaymentMethod;
use App\Models\ChartOfAccount;
use App\Models\SaleReturn;
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
            $tenderedAmount = round(array_sum(array_column($paymentLines, 'amount')), 2);
            $payment = $this->resolveSalePaymentAmounts($netAmount, $tenderedAmount);

            return [
                'effective_paid' => $payment['effective_paid'],
                'due_amount' => $payment['due_amount'],
                'change_amount' => $payment['change_amount'],
                'payment_lines' => $paymentLines,
            ];
        }

        $tenderedAmount = round(max(0, (float) ($data['paid_amount'] ?? 0)), 2);
        $payment = $this->resolveSalePaymentAmounts($netAmount, $tenderedAmount);
        $tenderedLines = [];

        if ($tenderedAmount > 0) {
            $tenderedLines[] = [
                'payment_account_id' => $this->resolvePaymentAccountIdFromData($data, $payment['effective_paid']),
                'amount' => $tenderedAmount,
            ];
        }

        return [
            'effective_paid' => $payment['effective_paid'],
            'due_amount' => $payment['due_amount'],
            'change_amount' => $payment['change_amount'],
            'payment_lines' => $tenderedLines,
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
    protected function resolveSalePaymentsWithCollectionAmount(array $data, float $netAmount, float $collectionTotal): array
    {
        $collectionTotal = round(max(0, $collectionTotal), 2);
        $dataForResolve = $data;
        $paymentLines = $this->normalizeSalePaymentLines($data);

        if ($paymentLines === []) {
            $reportedPaid = round(max(0, (float) ($data['paid_amount'] ?? 0)), 2);
            $dataForResolve['paid_amount'] = max(0, round($reportedPaid - $collectionTotal, 2));
        }

        $payment = $this->resolveSalePayments($dataForResolve, $netAmount);
        $sellTendered = round(array_sum(array_column($payment['payment_lines'], 'amount')), 2);

        if ($sellTendered + $collectionTotal > $netAmount + 0.01) {
            throw ValidationException::withMessages([
                'payments' => 'Sale payments and due collections cannot exceed the invoice total.',
            ]);
        }

        $totalPaid = round(min($netAmount, $sellTendered + $collectionTotal), 2);

        return [
            'effective_paid' => $totalPaid,
            'due_amount' => round(max(0, $netAmount - $totalPaid), 2),
            'change_amount' => $payment['change_amount'],
            'payment_lines' => $payment['payment_lines'],
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

    protected function paymentAccountBalanceWarning(int $paymentAccountId, float $amount, float $restorableAmount = 0): ?string
    {
        if ($amount <= 0) {
            return null;
        }

        $account = ChartOfAccount::query()
            ->whereKey($paymentAccountId)
            ->first(['id', 'name', 'current_balance']);

        if ($account === null) {
            return 'Selected payment account was not found.';
        }

        $requiredAmount = round($amount, 2);
        $availableBalance = round((float) $account->current_balance + max(0, $restorableAmount), 2);

        if ($availableBalance < $requiredAmount) {
            return sprintf(
                'Insufficient balance in %s. Available: ৳%s, required: ৳%s.',
                $account->name,
                number_format($availableBalance, 2),
                number_format($requiredAmount, 2),
            );
        }

        return null;
    }

    protected function isInsufficientBalanceException(\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'Insufficient balance');
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

    /**
     * @param  array<int, array{payment_account_id: int, amount: float}>  $paymentLines
     */
    protected function syncSaleReturnPayments(SaleReturn $saleReturn, array $paymentLines): void
    {
        $saleReturn->payments()->delete();

        foreach ($paymentLines as $paymentLine) {
            $saleReturn->payments()->create([
                'payment_account_id' => $paymentLine['payment_account_id'],
                'amount' => $paymentLine['amount'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     paid_amount: float,
     *     payment_type: ReceivedPaymentMethod,
     *     payment_account_id: ?int,
     *     payment_lines: array<int, array{payment_account_id: int, amount: float}>
     * }
     */
    protected function resolveReturnRefund(array $data, Request $request, float $netReturnAmount): array
    {
        $paymentLines = $this->normalizeSalePaymentLines($data);
        $maxRefund = round(min((float) ($data['paid_amount'] ?? 0), $netReturnAmount), 2);

        if ($paymentLines !== []) {
            $totalTendered = round(array_sum(array_column($paymentLines, 'amount')), 2);

            if ($totalTendered > $maxRefund + 0.009) {
                throw ValidationException::withMessages([
                    'payments' => 'Refund total cannot exceed the refundable amount.',
                ]);
            }

            $effectivePaid = round(min($totalTendered, $maxRefund), 2);

            return [
                'paid_amount' => $effectivePaid,
                'payment_type' => ReceivedPaymentMethod::Cash,
                'payment_account_id' => $paymentLines[0]['payment_account_id'] ?? null,
                'payment_lines' => $paymentLines,
            ];
        }

        $paymentType = ReceivedPaymentMethod::from((int) $data['payment_type']);
        $paymentAccountId = $paymentType === ReceivedPaymentMethod::Cash
            ? $this->resolvePaymentAccountId($request, $maxRefund)
            : null;
        $paidAmount = $paymentType === ReceivedPaymentMethod::Cash
            ? $maxRefund
            : round(min((float) ($data['paid_amount'] ?? 0), $netReturnAmount), 2);

        $lines = [];

        if ($paymentType === ReceivedPaymentMethod::Cash && $paidAmount > 0 && $paymentAccountId) {
            $lines[] = [
                'payment_account_id' => $paymentAccountId,
                'amount' => $paidAmount,
            ];
        }

        return [
            'paid_amount' => $paidAmount,
            'payment_type' => $paymentType,
            'payment_account_id' => $paymentAccountId,
            'payment_lines' => $lines,
        ];
    }
}
