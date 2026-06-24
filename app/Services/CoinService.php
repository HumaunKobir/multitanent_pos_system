<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\CustomerCoinTransaction;
use App\Models\Sell;
use Illuminate\Validation\ValidationException;

class CoinService
{
    public function settingsForBranch(?int $branchId): ?CoinSettings
    {
        if ($branchId === null) {
            return null;
        }

        return CoinSettings::query()
            ->where('branch_id', $branchId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsPayloadForBranch(?int $branchId): array
    {
        $settings = $this->settingsForBranch($branchId);

        if ($settings === null) {
            return [
                'enabled' => false,
                'earn_spend_amount' => 100.0,
                'earn_coins' => 1.0,
                'coin_value' => 1.0,
                'min_redeem_coins' => 0.0,
                'max_redeem_percent' => 50.0,
            ];
        }

        return $settings->toPosPayload();
    }

    public function maxRedeemableCoins(
        Customer $customer,
        CoinSettings $settings,
        float $netBeforeCoin,
        float $coinBalanceOffset = 0,
    ): float {
        if (! $settings->isActive() || $customer->is_default) {
            return 0.0;
        }

        $netBeforeCoin = round(max(0, $netBeforeCoin), 2);
        $coinValue = (float) $settings->coin_value;

        if ($netBeforeCoin <= 0 || $coinValue <= 0) {
            return 0.0;
        }

        $balanceCap = round(max(0, (float) $customer->point + $coinBalanceOffset), 2);
        $billCap = floor($netBeforeCoin / $coinValue);
        $percentCap = floor(
            ($netBeforeCoin * ((float) $settings->max_redeem_percent / 100)) / $coinValue
        );

        return max(0, min($balanceCap, $billCap, $percentCap));
    }

    public function redeemDiscount(float $coinsToRedeem, CoinSettings $settings, float $netBeforeCoin): float
    {
        $coinsToRedeem = round(max(0, $coinsToRedeem), 2);

        if ($coinsToRedeem <= 0 || ! $settings->isActive()) {
            return 0.0;
        }

        $discount = round($coinsToRedeem * (float) $settings->coin_value, 2);

        return min($discount, round(max(0, $netBeforeCoin), 2));
    }

    public function earnCoins(float $paidBase, CoinSettings $settings): float
    {
        if (! $settings->isActive()) {
            return 0.0;
        }

        $paidBase = round(max(0, $paidBase), 2);
        $spendAmount = (float) $settings->earn_spend_amount;

        if ($paidBase <= 0 || $spendAmount <= 0) {
            return 0.0;
        }

        $multiplier = floor($paidBase / $spendAmount);

        return round($multiplier * (float) $settings->earn_coins, 2);
    }

    /**
     * @return array{
     *     coins_redeemed: float,
     *     coin_discount_amount: float,
     *     coins_earned: float
     * }
     */
    public function resolveForSale(
        Customer $customer,
        CoinSettings $settings,
        float $netBeforeCoin,
        float $coinsToRedeem,
        float $earnBase,
        float $coinBalanceOffset = 0,
    ): array {
        $coinsToRedeem = round(max(0, $coinsToRedeem), 2);
        $effectiveBalance = round(max(0, (float) $customer->point + $coinBalanceOffset), 2);

        if ($customer->is_default || ! $settings->isActive()) {
            if ($coinsToRedeem > 0) {
                throw ValidationException::withMessages([
                    'coins_redeemed' => 'Coins cannot be redeemed for walk-in customers.',
                ]);
            }

            return [
                'coins_redeemed' => 0.0,
                'coin_discount_amount' => 0.0,
                'coins_earned' => 0.0,
            ];
        }

        if ($coinsToRedeem > 0) {
            $minRedeem = (float) $settings->min_redeem_coins;

            if ($minRedeem > 0 && $coinsToRedeem < $minRedeem) {
                throw ValidationException::withMessages([
                    'coins_redeemed' => "Minimum {$minRedeem} coins required to redeem.",
                ]);
            }

            if ($coinsToRedeem > $effectiveBalance) {
                throw ValidationException::withMessages([
                    'coins_redeemed' => 'Insufficient coin balance.',
                ]);
            }

            $maxRedeemable = $this->maxRedeemableCoins($customer, $settings, $netBeforeCoin, $coinBalanceOffset);

            if ($coinsToRedeem > $maxRedeemable) {
                throw ValidationException::withMessages([
                    'coins_redeemed' => "You can redeem at most {$maxRedeemable} coins for this sale.",
                ]);
            }
        }

        $coinDiscountAmount = $this->redeemDiscount($coinsToRedeem, $settings, $netBeforeCoin);
        $coinsEarned = $this->earnCoins($earnBase, $settings);

        return [
            'coins_redeemed' => $coinsToRedeem,
            'coin_discount_amount' => $coinDiscountAmount,
            'coins_earned' => $coinsEarned,
        ];
    }

    public function applyToSale(Sell $sell, Customer $customer, array $coinResult): void
    {
        $coinsRedeemed = (float) ($coinResult['coins_redeemed'] ?? 0);
        $coinsEarned = (float) ($coinResult['coins_earned'] ?? 0);

        if ($coinsRedeemed <= 0 && $coinsEarned <= 0) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
        $balance = (float) $customer->point;

        if ($coinsRedeemed > 0) {
            $balance = round($balance - $coinsRedeemed, 2);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $sell->branch_id,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::Redeem,
                'coins' => -$coinsRedeemed,
                'balance_after' => $balance,
                'meta' => [
                    'coin_discount_amount' => (float) ($coinResult['coin_discount_amount'] ?? 0),
                ],
            ]);
        }

        if ($coinsEarned > 0) {
            $balance = round($balance + $coinsEarned, 2);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $sell->branch_id,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::Earn,
                'coins' => $coinsEarned,
                'balance_after' => $balance,
                'meta' => [
                    'effective_paid' => (float) ($coinResult['effective_paid'] ?? 0),
                ],
            ]);
        }

        $customer->update(['point' => $balance]);
    }

    public function reverseForSell(Sell $sell): void
    {
        $transactions = CustomerCoinTransaction::query()
            ->where('sell_id', $sell->id)
            ->whereIn('type', [CoinTransactionType::Redeem, CoinTransactionType::Earn])
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $customerId = $transactions->first()->customer_id;
        $customer = Customer::query()->lockForUpdate()->find($customerId);

        if ($customer === null) {
            return;
        }

        $balance = (float) $customer->point;

        foreach ($transactions as $transaction) {
            $balance = round($balance - (float) $transaction->coins, 2);

            $reverseType = $transaction->type === CoinTransactionType::Redeem
                ? CoinTransactionType::ReverseRedeem
                : CoinTransactionType::ReverseEarn;

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $transaction->branch_id,
                'sell_id' => $sell->id,
                'type' => $reverseType,
                'coins' => -(float) $transaction->coins,
                'balance_after' => $balance,
                'meta' => [
                    'reversed_transaction_id' => $transaction->id,
                ],
            ]);
        }

        $customer->update(['point' => max(0, $balance)]);
    }
}
