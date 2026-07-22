<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Party;
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

test('income voucher index includes parties suppliers and customers as contacts', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.view']);
    $party = Party::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Voucher Party '.fake()->unique()->numerify('####'),
    ]);
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
            ->where('contacts.parties', fn ($parties) => collect($parties)->contains('id', $party->id))
            ->where('contacts.suppliers', fn ($suppliers) => collect($suppliers)->contains('id', $supplier->id))
            ->where('contacts.customers', fn ($customers) => collect($customers)->contains('id', $customer->id)));
});

test('income voucher can be saved with a party contact', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    $cash = seedAccountingAccounts(user: $user);
    $incomeAccount = voucherLeafAccount(SystemAccountKey::SalesRevenue, AccountType::Income, 'income-party-voucher');
    $party = Party::factory()->create(['branch_id' => $user->branch_id, 'name' => 'Received Party']);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Income->value,
            'voucher_no' => 'INC-PARTY-'.fake()->unique()->numerify('####'),
            'date' => '2026-06-06',
            'party_key' => "party:{$party->id}",
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $incomeAccount->id,
                    'amount' => 900,
                    'narration' => 'Misc income from party',
                ],
            ],
        ])
        ->assertRedirect(route('accounts.vouchers.index', ['type' => 'income']))
        ->assertSessionHas('success');

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->type)->toBe(VoucherType::Income);
    expect($voucher->party_type)->toBe(Party::class);
    expect($voucher->party_id)->toBe($party->id);
    expect($voucher->party?->name)->toBe('Received Party');
});

test('income voucher can be saved with a customer contact', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    $cash = seedAccountingAccounts(user: $user);
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
    $cash = seedAccountingAccounts(user: $user);
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

test('journal voucher validation refers to account instead of account id', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    seedAccountingAccounts(user: $user);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Journal->value,
            'voucher_no' => 'JRN-VAL-'.fake()->unique()->numerify('####'),
            'date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'side' => 'debit',
                    'account_id' => null,
                    'amount' => 100,
                ],
                [
                    'side' => 'credit',
                    'account_id' => null,
                    'amount' => 100,
                ],
            ],
        ])
        ->assertSessionHasErrors(['lines.0.account_id', 'lines.1.account_id']);

    $message = session('errors')->first('lines.0.account_id');

    expect($message)->not->toContain('account id')
        ->and(strtolower($message))->toContain('account');
});

test('journal insufficient balance error uses account name not account id', function () {
    $this->artisan('permissions:sync');

    $user = voucherUser(['accounts.create']);
    $cash = seedAccountingAccounts(minimumBalance: 50, user: $user);
    $expense = voucherLeafAccount(SystemAccountKey::Expenses, AccountType::Expenses, 'jrn-bal-exp');

    // Force a low cash balance for the insufficient-funds path.
    $cash->update(['current_balance' => 10]);

    $this->actingAs($user)
        ->from('/accounts/vouchers?type=journal')
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Journal->value,
            'voucher_no' => 'JRN-BAL-'.fake()->unique()->numerify('####'),
            'date' => now()->format('Y-m-d'),
            'lines' => [
                [
                    'side' => 'debit',
                    'account_id' => $expense->id,
                    'amount' => 100,
                ],
                [
                    'side' => 'credit',
                    'account_id' => $cash->id,
                    'amount' => 100,
                ],
            ],
        ])
        ->assertRedirect('/accounts/vouchers?type=journal')
        ->assertSessionHasErrors('general');

    $message = session('errors')->first('general');

    expect($message)->toContain($cash->name)
        ->and($message)->not->toContain('account ID')
        ->and($message)->not->toContain((string) $cash->id);
});
