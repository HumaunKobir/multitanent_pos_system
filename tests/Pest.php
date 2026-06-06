<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Services\InventoryAccountingService;
use App\Services\SystemAccountService;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function seedAccountingAccounts(float $minimumBalance = 100000): ChartOfAccount
{
    SystemAccountService::seed();

    ChartOfAccount::$skipCodeGeneration = true;

    $cash = ChartOfAccount::query()->firstOrCreate(
        ['code' => 'A001-99'],
        [
            'parent_id' => SystemAccountService::resolve(SystemAccountKey::CurrentAssets)->id,
            'name' => 'Test Cash Account',
            'type' => AccountType::Asset,
            'status' => CommonStatus::Active,
            'current_balance' => 0,
        ],
    );

    ChartOfAccount::$skipCodeGeneration = false;

    if (! Transaction::query()
        ->where('source_type', ChartOfAccount::class)
        ->where('source_id', $cash->id)
        ->exists()) {
        app(InventoryAccountingService::class)->postAccountOpeningBalance(
            $cash,
            $minimumBalance,
            now()->format('Y-m-d'),
        );
    }

    $cash->refresh();

    $shortfall = round($minimumBalance - (float) $cash->current_balance, 2);

    if ($shortfall > 0) {
        app(InventoryAccountingService::class)->postAccountOpeningBalance(
            $cash,
            $shortfall,
            now()->format('Y-m-d'),
        );
        $cash->refresh();
    }

    $inventory = SystemAccountService::resolve(SystemAccountKey::Inventory);
    $inventoryShortfall = round($minimumBalance - (float) $inventory->current_balance, 2);

    if ($inventoryShortfall > 0) {
        app(InventoryAccountingService::class)->postAccountOpeningBalance(
            $inventory,
            $inventoryShortfall,
            now()->format('Y-m-d'),
        );
    }

    return $cash;
}
