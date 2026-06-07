<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use App\Services\SystemAccountService;
use Spatie\Permission\Models\Permission;

function voucherUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function voucherLeafAccount(SystemAccountKey $parentKey, AccountType $type, string $suffix): ChartOfAccount
{
    SystemAccountService::seed();

    ChartOfAccount::$skipCodeGeneration = true;

    $account = ChartOfAccount::query()->firstOrCreate(
        ['code' => "TEST-{$suffix}"],
        [
            'parent_id' => SystemAccountService::resolve($parentKey)->id,
            'name' => "Test {$suffix}",
            'type' => $type,
            'status' => CommonStatus::Active,
            'current_balance' => 0,
        ],
    );

    ChartOfAccount::$skipCodeGeneration = false;

    return $account;
}

test('income voucher index includes suppliers and customers as contacts', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.view']);
    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Voucher Supplier '.fake()->unique()->numerify('####'),
    ]);
    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Voucher Customer '.fake()->unique()->numerify('####'),
    ]);

    $this->actingAs($user)
        ->get('/accounts/vouchers?type=income')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/accounts/vouchers/index')
            ->where('contacts.suppliers', fn ($suppliers) => collect($suppliers)->contains('id', $supplier->id))
            ->where('contacts.customers', fn ($customers) => collect($customers)->contains('id', $customer->id)));
});

test('income voucher can be saved with a customer contact', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    $cash = seedAccountingAccounts();
    $incomeAccount = voucherLeafAccount(SystemAccountKey::SalesRevenue, AccountType::Income, 'income-voucher');
    $customer = Customer::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Income->value,
            'voucher_no' => 'INC-TEST-'.fake()->unique()->numerify('####'),
            'date' => '2026-06-06',
            'party_key' => "customer:{$customer->id}",
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $incomeAccount->id,
                    'amount' => 1500,
                    'narration' => 'Misc income',
                ],
            ],
        ])
        ->assertRedirect(route('accounts.vouchers.index', ['type' => 'income']))
        ->assertSessionHas('success');

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->type)->toBe(VoucherType::Income);
    expect($voucher->party_type)->toBe(Customer::class);
    expect($voucher->party_id)->toBe($customer->id);
    expect($voucher->party?->name)->toBe($customer->name);
});

test('expense voucher can be saved with a supplier contact', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    $cash = seedAccountingAccounts();
    $expenseAccount = voucherLeafAccount(SystemAccountKey::Expenses, AccountType::Expenses, 'expense-voucher');
    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Expense->value,
            'voucher_no' => 'EXP-TEST-'.fake()->unique()->numerify('####'),
            'date' => '2026-06-06',
            'party_key' => "supplier:{$supplier->id}",
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $expenseAccount->id,
                    'amount' => 750,
                    'narration' => 'Office expense',
                ],
            ],
        ])
        ->assertRedirect(route('accounts.vouchers.index', ['type' => 'expense']))
        ->assertSessionHas('success');

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->type)->toBe(VoucherType::Expense);
    expect($voucher->party_type)->toBe(Supplier::class);
    expect($voucher->party_id)->toBe($supplier->id);
    expect($voucher->party?->name)->toBe($supplier->name);
});

test('voucher rejects contact from another branch', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    $cash = seedAccountingAccounts();
    $incomeAccount = voucherLeafAccount(SystemAccountKey::SalesRevenue, AccountType::Income, 'income-branch-check');
    $otherBranch = Branch::factory()->create();
    $customer = Customer::factory()->create(['branch_id' => $otherBranch->id]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Income->value,
            'voucher_no' => 'INC-BR-'.fake()->unique()->numerify('####'),
            'date' => '2026-06-06',
            'party_key' => "customer:{$customer->id}",
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $incomeAccount->id,
                    'amount' => 500,
                ],
            ],
        ])
        ->assertSessionHasErrors('party_key');
});
