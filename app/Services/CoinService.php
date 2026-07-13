<?php

namespace App\Services;

use App\Enums\CoinTransactionType;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\CustomerCoinLot;
use App\Models\CustomerCoinTransaction;
use App\Models\ProductExchange;
use App\Models\SaleReturn;
use App\Models\Sell;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                'expiry_value' => null,
                'expiry_unit' => null,
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
        $expiresAt = $this->settingsForBranch($sell->branch_id)?->resolveExpiresAt($sell->created_at);

        if ($coinsRedeemed > 0) {
            $balance = round($balance - $coinsRedeemed, 2);
            $consumedLots = $this->consumeLotsFefo($customer->id, $coinsRedeemed);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $sell->branch_id,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::Redeem,
                'coins' => -$coinsRedeemed,
                'balance_after' => $balance,
                'meta' => [
                    'coin_discount_amount' => (float) ($coinResult['coin_discount_amount'] ?? 0),
                    'consumed_lots' => $consumedLots,
                ],
            ]);
        }

        if ($coinsEarned > 0) {
            $balance = round($balance + $coinsEarned, 2);

            $earnTransaction = CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $sell->branch_id,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::Earn,
                'coins' => $coinsEarned,
                'balance_after' => $balance,
                'meta' => [
                    'effective_paid' => (float) ($coinResult['effective_paid'] ?? 0),
                    'expires_at' => $expiresAt?->toIso8601String(),
                ],
            ]);

            $this->createLotForEarn(
                $earnTransaction,
                $customer->id,
                $sell->branch_id,
                $coinsEarned,
                $expiresAt,
                sellId: $sell->id,
            );
        }

        $customer->update(['point' => $balance]);
    }

    public function reverseProportionalForSaleReturn(Sell $sell, SaleReturn $saleReturn, float $proportion): void
    {
        if ($sell->customer_id === null || $proportion <= 0) {
            return;
        }

        $proportion = min(1.0, max(0.0, $proportion));
        $coinsRedeemed = round((float) $sell->coins_redeemed * $proportion, 2);
        $coinsEarned = round((float) $sell->coins_earned * $proportion, 2);

        if ($coinsRedeemed <= 0 && $coinsEarned <= 0) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->find($sell->customer_id);

        if ($customer === null || $customer->is_default) {
            return;
        }

        $branchId = $sell->branch_id ?? $saleReturn->branch_id ?? $customer->branch_id;

        if ($branchId === null) {
            return;
        }

        $balance = (float) $customer->point;

        if ($coinsRedeemed > 0) {
            $balance = round($balance + $coinsRedeemed, 2);
            $restoredLots = $this->restoreLotsFefo($customer->id, $coinsRedeemed);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $branchId,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::ReverseRedeem,
                'coins' => $coinsRedeemed,
                'balance_after' => $balance,
                'meta' => [
                    'sale_return_id' => $saleReturn->id,
                    'source' => 'sale_return',
                    'restored_lots' => $restoredLots,
                ],
            ]);
        }

        if ($coinsEarned > 0) {
            $balance = round($balance - $coinsEarned, 2);
            $this->reduceLotsForSell($sell->id, $coinsEarned);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $branchId,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::ReverseEarn,
                'coins' => -$coinsEarned,
                'balance_after' => $balance,
                'meta' => [
                    'sale_return_id' => $saleReturn->id,
                    'source' => 'sale_return',
                ],
            ]);
        }

        $customer->update(['point' => max(0, $balance)]);
    }

    public function restoreForSaleReturn(Sell $sell, SaleReturn $saleReturn): void
    {
        if ($sell->customer_id === null) {
            return;
        }

        $reversedTransactionIds = $this->reversedSaleReturnTransactionIds($sell, $saleReturn);

        $transactions = CustomerCoinTransaction::query()
            ->where('sell_id', $sell->id)
            ->whereIn('type', [CoinTransactionType::ReverseRedeem, CoinTransactionType::ReverseEarn])
            ->where('meta->sale_return_id', $saleReturn->id)
            ->when(
                $reversedTransactionIds !== [],
                fn ($query) => $query->whereNotIn('id', $reversedTransactionIds),
            )
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $customerId = $transactions->first()->customer_id ?? $sell->customer_id;
        $customer = Customer::query()->lockForUpdate()->find($customerId);

        if ($customer === null) {
            return;
        }

        $balance = (float) $customer->point;
        $expiresAt = $this->settingsForBranch($sell->branch_id)?->resolveExpiresAt($sell->created_at);

        foreach ($transactions as $transaction) {
            $balance = round($balance - (float) $transaction->coins, 2);

            $restoreType = $transaction->type === CoinTransactionType::ReverseRedeem
                ? CoinTransactionType::Redeem
                : CoinTransactionType::Earn;

            $branchId = $transaction->branch_id ?? $sell->branch_id ?? $saleReturn->branch_id ?? $customer->branch_id;

            if ($branchId === null) {
                continue;
            }

            $meta = [
                'reversed_transaction_id' => $transaction->id,
                'source' => 'sale_return_rollback',
            ];

            if ($restoreType === CoinTransactionType::Redeem) {
                $amount = abs((float) $transaction->coins);
                $meta['consumed_lots'] = $this->consumeLotsFefo($customer->id, $amount);
            }

            $restoredTransaction = CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $branchId,
                'sell_id' => $sell->id,
                'type' => $restoreType,
                'coins' => -(float) $transaction->coins,
                'balance_after' => $balance,
                'meta' => $meta,
            ]);

            if ($restoreType === CoinTransactionType::Earn) {
                $amount = abs((float) $transaction->coins);
                $this->createLotForEarn(
                    $restoredTransaction,
                    $customer->id,
                    $branchId,
                    $amount,
                    $expiresAt,
                    sellId: $sell->id,
                );
            }
        }

        $customer->update(['point' => max(0, $balance)]);
    }

    public function reverseForSell(Sell $sell): void
    {
        if ($sell->customer_id === null) {
            return;
        }

        $reversedTransactionIds = $this->reversedOriginalTransactionIds($sell);

        $transactions = CustomerCoinTransaction::query()
            ->where('sell_id', $sell->id)
            ->whereIn('type', [CoinTransactionType::Redeem, CoinTransactionType::Earn])
            ->when(
                $reversedTransactionIds !== [],
                fn ($query) => $query->whereNotIn('id', $reversedTransactionIds),
            )
            ->orderBy('id')
            ->get();

        if ($transactions->isNotEmpty()) {
            $this->reverseCoinTransactions($sell, $transactions);

            return;
        }

        if ($this->sellCoinsAlreadyReversed($sell)) {
            return;
        }

        $this->reverseSellCoinSnapshot($sell);
    }

    /**
     * Apply coin redeem/earn transactions for a product exchange, mirroring applyToSale.
     *
     * @param  array{coins_redeemed: float, coin_discount_amount: float, coins_earned: float, effective_paid?: float}  $coinResult
     */
    public function applyToExchange(ProductExchange $exchange, Customer $customer, array $coinResult): void
    {
        $coinsRedeemed = (float) ($coinResult['coins_redeemed'] ?? 0);
        $coinsEarned = (float) ($coinResult['coins_earned'] ?? 0);

        if ($coinsRedeemed <= 0 && $coinsEarned <= 0) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
        $balance = (float) $customer->point;
        $expiresAt = $this->settingsForBranch($exchange->branch_id)?->resolveExpiresAt($exchange->created_at);

        if ($coinsRedeemed > 0) {
            $balance = round($balance - $coinsRedeemed, 2);
            $consumedLots = $this->consumeLotsFefo($customer->id, $coinsRedeemed);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $exchange->branch_id,
                'product_exchange_id' => $exchange->id,
                'type' => CoinTransactionType::Redeem,
                'coins' => -$coinsRedeemed,
                'balance_after' => $balance,
                'meta' => [
                    'coin_discount_amount' => (float) ($coinResult['coin_discount_amount'] ?? 0),
                    'consumed_lots' => $consumedLots,
                ],
            ]);
        }

        if ($coinsEarned > 0) {
            $balance = round($balance + $coinsEarned, 2);

            $earnTransaction = CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $exchange->branch_id,
                'product_exchange_id' => $exchange->id,
                'type' => CoinTransactionType::Earn,
                'coins' => $coinsEarned,
                'balance_after' => $balance,
                'meta' => [
                    'effective_paid' => (float) ($coinResult['effective_paid'] ?? 0),
                    'expires_at' => $expiresAt?->toIso8601String(),
                ],
            ]);

            $this->createLotForEarn(
                $earnTransaction,
                $customer->id,
                $exchange->branch_id,
                $coinsEarned,
                $expiresAt,
                productExchangeId: $exchange->id,
            );
        }

        $customer->update(['point' => $balance]);
    }

    public function reverseForExchange(ProductExchange $exchange): void
    {
        if ($exchange->customer_id === null) {
            return;
        }

        $reversedTransactionIds = $this->reversedOriginalExchangeTransactionIds($exchange);

        $transactions = CustomerCoinTransaction::query()
            ->where('product_exchange_id', $exchange->id)
            ->whereIn('type', [CoinTransactionType::Redeem, CoinTransactionType::Earn])
            ->when(
                $reversedTransactionIds !== [],
                fn ($query) => $query->whereNotIn('id', $reversedTransactionIds),
            )
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            return;
        }

        $customerId = $transactions->first()->customer_id ?? $exchange->customer_id;
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

            if ($transaction->type === CoinTransactionType::Redeem) {
                $this->restoreLotsFromMeta($transaction->meta['consumed_lots'] ?? []);
            }

            if ($transaction->type === CoinTransactionType::Earn) {
                $this->zeroLotForEarnTransaction($transaction->id);
            }

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $transaction->branch_id,
                'product_exchange_id' => $exchange->id,
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

    /**
     * Expire lots past their expires_at and debit customer balances.
     *
     * @return array{customers: int, lots: int, coins: float}
     */
    public function expireLots(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $customersAffected = 0;
        $lotsExpired = 0;
        $coinsExpired = 0.0;

        $customerIds = CustomerCoinLot::query()
            ->where('remaining_coins', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf)
            ->distinct()
            ->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $result = $this->expireLotsForCustomer((int) $customerId, $asOf);
            $customersAffected += $result['lots'] > 0 ? 1 : 0;
            $lotsExpired += $result['lots'];
            $coinsExpired = round($coinsExpired + $result['coins'], 2);
        }

        return [
            'customers' => $customersAffected,
            'lots' => $lotsExpired,
            'coins' => $coinsExpired,
        ];
    }

    /**
     * @return array{lots: int, coins: float}
     */
    public function expireLotsForCustomer(int $customerId, ?CarbonInterface $asOf = null): array
    {
        return DB::transaction(function () use ($customerId, $asOf): array {
            $asOf ??= now();
            $customer = Customer::query()->lockForUpdate()->find($customerId);

            if ($customer === null) {
                return ['lots' => 0, 'coins' => 0.0];
            }

            $lots = CustomerCoinLot::query()
                ->where('customer_id', $customerId)
                ->where('remaining_coins', '>', 0)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $asOf)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lots->isEmpty()) {
                return ['lots' => 0, 'coins' => 0.0];
            }

            $balance = (float) $customer->point;
            $lotsExpired = 0;
            $coinsExpired = 0.0;

            foreach ($lots as $lot) {
                $amount = round((float) $lot->remaining_coins, 2);

                if ($amount <= 0) {
                    continue;
                }

                $balance = round(max(0, $balance - $amount), 2);
                $coinsExpired = round($coinsExpired + $amount, 2);
                $lotsExpired++;

                CustomerCoinTransaction::create([
                    'customer_id' => $customer->id,
                    'branch_id' => $lot->branch_id,
                    'sell_id' => $lot->sell_id,
                    'product_exchange_id' => $lot->product_exchange_id,
                    'type' => CoinTransactionType::Expire,
                    'coins' => -$amount,
                    'balance_after' => $balance,
                    'meta' => [
                        'lot_id' => $lot->id,
                        'earn_transaction_id' => $lot->earn_transaction_id,
                        'expires_at' => $lot->expires_at?->toIso8601String(),
                    ],
                ]);

                $lot->update(['remaining_coins' => 0]);
            }

            $customer->update(['point' => $balance]);

            return [
                'lots' => $lotsExpired,
                'coins' => $coinsExpired,
            ];
        });
    }

    /**
     * @return array<int, int>
     */
    private function reversedOriginalExchangeTransactionIds(ProductExchange $exchange): array
    {
        return CustomerCoinTransaction::query()
            ->where('product_exchange_id', $exchange->id)
            ->whereIn('type', [CoinTransactionType::ReverseRedeem, CoinTransactionType::ReverseEarn])
            ->get()
            ->map(fn (CustomerCoinTransaction $transaction): ?int => $transaction->meta['reversed_transaction_id'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function reversedSaleReturnTransactionIds(Sell $sell, SaleReturn $saleReturn): array
    {
        return CustomerCoinTransaction::query()
            ->where('sell_id', $sell->id)
            ->whereIn('type', [CoinTransactionType::Redeem, CoinTransactionType::Earn])
            ->where('meta->source', 'sale_return_rollback')
            ->get()
            ->map(fn (CustomerCoinTransaction $transaction): ?int => $transaction->meta['reversed_transaction_id'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function reversedOriginalTransactionIds(Sell $sell): array
    {
        return CustomerCoinTransaction::query()
            ->where('sell_id', $sell->id)
            ->whereIn('type', [CoinTransactionType::ReverseRedeem, CoinTransactionType::ReverseEarn])
            ->get()
            ->map(fn (CustomerCoinTransaction $transaction): ?int => $transaction->meta['reversed_transaction_id'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function sellCoinsAlreadyReversed(Sell $sell): bool
    {
        return CustomerCoinTransaction::query()
            ->where('sell_id', $sell->id)
            ->whereIn('type', [CoinTransactionType::ReverseRedeem, CoinTransactionType::ReverseEarn])
            ->exists();
    }

    /**
     * @param  Collection<int, CustomerCoinTransaction>  $transactions
     */
    private function reverseCoinTransactions(Sell $sell, Collection $transactions): void
    {
        $customerId = $transactions->first()->customer_id ?? $sell->customer_id;
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

            if ($transaction->type === CoinTransactionType::Redeem) {
                $this->restoreLotsFromMeta($transaction->meta['consumed_lots'] ?? []);
            }

            if ($transaction->type === CoinTransactionType::Earn) {
                $this->zeroLotForEarnTransaction($transaction->id);
            }

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

    private function reverseSellCoinSnapshot(Sell $sell): void
    {
        $coinsRedeemed = round((float) $sell->coins_redeemed, 2);
        $coinsEarned = round((float) $sell->coins_earned, 2);

        if ($coinsRedeemed <= 0 && $coinsEarned <= 0) {
            return;
        }

        $customer = Customer::query()->lockForUpdate()->find($sell->customer_id);

        if ($customer === null || $customer->is_default) {
            return;
        }

        $balance = (float) $customer->point;

        if ($coinsRedeemed > 0) {
            $balance = round($balance + $coinsRedeemed, 2);
            $restoredLots = $this->restoreLotsFefo($customer->id, $coinsRedeemed);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $sell->branch_id,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::ReverseRedeem,
                'coins' => $coinsRedeemed,
                'balance_after' => $balance,
                'meta' => [
                    'source' => 'sell_snapshot',
                    'restored_lots' => $restoredLots,
                ],
            ]);
        }

        if ($coinsEarned > 0) {
            $balance = round($balance - $coinsEarned, 2);
            $this->reduceLotsForSell($sell->id, $coinsEarned);

            CustomerCoinTransaction::create([
                'customer_id' => $customer->id,
                'branch_id' => $sell->branch_id,
                'sell_id' => $sell->id,
                'type' => CoinTransactionType::ReverseEarn,
                'coins' => -$coinsEarned,
                'balance_after' => $balance,
                'meta' => [
                    'source' => 'sell_snapshot',
                ],
            ]);
        }

        $customer->update(['point' => max(0, $balance)]);
    }

    private function createLotForEarn(
        CustomerCoinTransaction $earnTransaction,
        int $customerId,
        int $branchId,
        float $coins,
        ?CarbonInterface $expiresAt,
        ?int $sellId = null,
        ?int $productExchangeId = null,
    ): CustomerCoinLot {
        return CustomerCoinLot::query()->create([
            'customer_id' => $customerId,
            'branch_id' => $branchId,
            'earn_transaction_id' => $earnTransaction->id,
            'sell_id' => $sellId,
            'product_exchange_id' => $productExchangeId,
            'original_coins' => $coins,
            'remaining_coins' => $coins,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * @return list<array{lot_id: int, coins: float}>
     */
    private function consumeLotsFefo(int $customerId, float $amount): array
    {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            return [];
        }

        $lots = CustomerCoinLot::query()
            ->where('customer_id', $customerId)
            ->where('remaining_coins', '>', 0)
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;
        $consumed = [];

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $available = round((float) $lot->remaining_coins, 2);
            $take = round(min($available, $remaining), 2);

            if ($take <= 0) {
                continue;
            }

            $lot->update(['remaining_coins' => round($available - $take, 2)]);
            $consumed[] = [
                'lot_id' => $lot->id,
                'coins' => $take,
            ];
            $remaining = round($remaining - $take, 2);
        }

        return $consumed;
    }

    /**
     * @param  list<array{lot_id?: int, coins?: float|int|string}>  $consumedLots
     */
    private function restoreLotsFromMeta(array $consumedLots): void
    {
        foreach ($consumedLots as $entry) {
            $lotId = (int) ($entry['lot_id'] ?? 0);
            $coins = round((float) ($entry['coins'] ?? 0), 2);

            if ($lotId <= 0 || $coins <= 0) {
                continue;
            }

            $lot = CustomerCoinLot::query()->lockForUpdate()->find($lotId);

            if ($lot === null) {
                continue;
            }

            $newRemaining = round(min(
                (float) $lot->original_coins,
                (float) $lot->remaining_coins + $coins,
            ), 2);

            $lot->update(['remaining_coins' => $newRemaining]);
        }
    }

    /**
     * Restore redeemed coins into open lots (soonest-expiring first), then leftover as uncapped top-up of last lot.
     *
     * @return list<array{lot_id: int, coins: float}>
     */
    private function restoreLotsFefo(int $customerId, float $amount): array
    {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            return [];
        }

        $lots = CustomerCoinLot::query()
            ->where('customer_id', $customerId)
            ->whereColumn('remaining_coins', '<', 'original_coins')
            ->orderByRaw('expires_at is null')
            ->orderBy('expires_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;
        $restored = [];

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $room = round((float) $lot->original_coins - (float) $lot->remaining_coins, 2);

            if ($room <= 0) {
                continue;
            }

            $add = round(min($room, $remaining), 2);
            $lot->update(['remaining_coins' => round((float) $lot->remaining_coins + $add, 2)]);
            $restored[] = [
                'lot_id' => $lot->id,
                'coins' => $add,
            ];
            $remaining = round($remaining - $add, 2);
        }

        return $restored;
    }

    private function zeroLotForEarnTransaction(int $earnTransactionId): void
    {
        CustomerCoinLot::query()
            ->where('earn_transaction_id', $earnTransactionId)
            ->update(['remaining_coins' => 0]);
    }

    private function reduceLotsForSell(int $sellId, float $amount): void
    {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            return;
        }

        $lots = CustomerCoinLot::query()
            ->where('sell_id', $sellId)
            ->where('remaining_coins', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $amount;

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $available = round((float) $lot->remaining_coins, 2);
            $take = round(min($available, $remaining), 2);
            $lot->update(['remaining_coins' => round($available - $take, 2)]);
            $remaining = round($remaining - $take, 2);
        }
    }
}
