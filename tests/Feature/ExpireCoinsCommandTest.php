<?php

use App\Enums\CoinTransactionType;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCoinLot;
use App\Models\CustomerCoinTransaction;
use App\Models\Sell;

test('coins expire command removes only past due remaining coins', function () {
    $branch = Branch::factory()->create();
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'point' => 20,
        'is_default' => false,
    ]);
    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
    ]);

    $earn = CustomerCoinTransaction::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'sell_id' => $sell->id,
        'type' => CoinTransactionType::Earn,
        'coins' => 12,
        'balance_after' => 20,
        'meta' => [],
    ]);

    $expiredLot = CustomerCoinLot::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'earn_transaction_id' => $earn->id,
        'sell_id' => $sell->id,
        'original_coins' => 12,
        'remaining_coins' => 12,
        'expires_at' => now()->subHour(),
    ]);

    $futureEarn = CustomerCoinTransaction::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'type' => CoinTransactionType::Earn,
        'coins' => 8,
        'balance_after' => 20,
        'meta' => [],
    ]);

    CustomerCoinLot::query()->create([
        'customer_id' => $customer->id,
        'branch_id' => $branch->id,
        'earn_transaction_id' => $futureEarn->id,
        'original_coins' => 8,
        'remaining_coins' => 8,
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('coins:expire')
        ->assertSuccessful();

    $expiredLot->refresh();
    $customer->refresh();

    expect((float) $expiredLot->remaining_coins)->toBe(0.0);
    expect((float) $customer->point)->toBe(8.0);
    expect(
        CustomerCoinTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', CoinTransactionType::Expire)
            ->count()
    )->toBe(1);
});
