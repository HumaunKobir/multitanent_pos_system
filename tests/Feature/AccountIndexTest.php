<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\SystemAccountService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function accountIndexUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('accounts index lists parent child system accounts across account types', function () {
    $this->artisan('permissions:sync');

    $user = accountIndexUser(['accounts.view']);

    SystemAccountService::seed($user->branch_id);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/account/index')
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'A001' && $account['name'] === 'Cash & Bank',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'A001-01' && $account['name'] === 'Cash in Hand',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'A002' && $account['name'] === 'Inventory',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'A002-01' && $account['name'] === 'Product Inventory',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'L001' && $account['name'] === 'Accounts Payable',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'L001-01' && $account['name'] === 'Supplier Payables',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'I001' && $account['name'] === 'Sales Revenue',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'I001-01' && $account['name'] === 'Product Sales',
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'I001-03'
                    && $account['name'] === 'Discount Applied'
                    && $account['type'] === AccountType::Income->value,
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'E002-01'
                    && $account['name'] === 'Current Year Earnings'
                    && $account['is_system'] === true,
            ))
            ->where('accounts', fn ($accounts) => ! collect($accounts)->contains(
                fn ($account) => $account['name'] === 'Input VAT',
            ))
        );
});

test('accounts index can filter by account type', function () {
    $this->artisan('permissions:sync');

    $user = accountIndexUser(['accounts.view']);

    SystemAccountService::seed($user->branch_id);

    $this->actingAs($user)
        ->get(route('accounts.index', ['type' => AccountType::Liability->value]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/account/index')
            ->where('accounts', fn ($accounts) => collect($accounts)->every(
                fn ($account) => $account['type'] === AccountType::Liability->value,
            ))
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['code'] === 'L001',
            ))
        );
});

test('branch user only sees accounts for their branch source', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $branchUser = accountIndexUser(['accounts.view']);
    $branchUser->update(['branch_id' => $branch->id]);

    SystemAccountService::seed($branch->id);
    SystemAccountService::seed($otherBranch->id);

    $branchCashId = SystemAccountService::id(SystemAccountKey::CashInHand, $branch->id);
    $otherBranchCashId = SystemAccountService::id(SystemAccountKey::CashInHand, $otherBranch->id);

    $this->actingAs($branchUser)
        ->get(route('accounts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/account/index')
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['id'] === $branchCashId,
            ))
            ->where('accounts', fn ($accounts) => ! collect($accounts)->contains(
                fn ($account) => $account['id'] === $otherBranchCashId,
            ))
        );
});

test('accounts index shows parent display balance as sum of children without changing posted balance', function () {
    $this->artisan('permissions:sync');

    $user = accountIndexUser(['accounts.view']);
    SystemAccountService::seed($user->branch_id);

    $parent = ChartOfAccount::query()->create([
        'source_type' => Branch::class,
        'source_id' => $user->branch_id,
        'parent_id' => null,
        'type' => AccountType::Asset->value,
        'name' => 'Display Rollup Parent',
        'code' => 'A9'.fake()->unique()->numerify('##'),
        'current_balance' => 10,
        'status' => 1,
        'is_system' => false,
    ]);

    ChartOfAccount::query()->create([
        'source_type' => Branch::class,
        'source_id' => $user->branch_id,
        'parent_id' => $parent->id,
        'type' => AccountType::Asset->value,
        'name' => 'Display Rollup Child A',
        'code' => $parent->code.'-01',
        'current_balance' => 120.50,
        'status' => 1,
        'is_system' => false,
    ]);

    ChartOfAccount::query()->create([
        'source_type' => Branch::class,
        'source_id' => $user->branch_id,
        'parent_id' => $parent->id,
        'type' => AccountType::Asset->value,
        'name' => 'Display Rollup Child B',
        'code' => $parent->code.'-02',
        'current_balance' => 80.25,
        'status' => 1,
        'is_system' => false,
    ]);

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/account/index')
            ->where('accounts', fn ($accounts) => collect($accounts)->contains(
                fn ($account) => $account['id'] === $parent->id
                    && (float) $account['current_balance'] === 10.0
                    && (float) $account['display_balance'] === 210.75,
            )));

    expect((float) $parent->fresh()->current_balance)->toBe(10.0);
});
