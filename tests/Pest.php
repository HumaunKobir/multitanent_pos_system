<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\EcommerceBranchService;
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

function ensureMainBranch(): int
{
    Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    return Branch::resolveMainBranchId();
}

function seedAccountingAccounts(float $minimumBalance = 100000, ?int $branchId = null, ?User $user = null): ChartOfAccount
{
    $branchId ??= $user?->branch_id ?? auth()->user()?->branch_id;

    SystemAccountService::seed($branchId);

    $cash = SystemAccountService::resolve(SystemAccountKey::CashInHand, $branchId);

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

    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, $branchId);
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

/**
 * @return array{branch: Branch, sslCommerz: ChartOfAccount, cashInHand: ChartOfAccount}
 */
function seedEcommerceBranchAccounts(): array
{
    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    User::query()->updateOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
            'branch_id' => $branch->id,
        ])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    SystemAccountService::seed($branch->id);
    seedAccountingAccounts(branchId: $branch->id);

    return [
        'branch' => $branch,
        'sslCommerz' => SystemAccountService::resolve(SystemAccountKey::SslCommerz, $branch->id),
        'cashInHand' => SystemAccountService::resolve(SystemAccountKey::CashInHand, $branch->id),
    ];
}

function storefrontEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    User::query()->updateOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
            'branch_id' => $branch->id,
        ])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    return $branch;
}

function storefrontProduct(array $attributes = []): Product
{
    $ecommerceBranch = storefrontEcommerceBranch();

    return Product::factory()->create(array_merge([
        'branch_id' => $ecommerceBranch->id,
    ], $attributes));
}
