<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\User;
use App\Models\Voucher;
use App\Services\SystemAccountService;
use App\Services\VoucherService;

test('next voucher number uses the highest numeric sequence not the latest id', function () {
    $user = User::factory()->create();

    $next = VoucherService::nextVoucherNo(VoucherType::Expense);
    expect($next)->toMatch('/^EXP-\d+$/');

    $base = (int) substr($next, 4);
    $highNumeric = 'EXP-'.($base + 50);
    $customSuffix = 'EXP-CUSTOM-'.fake()->unique()->numerify('######');

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => $highNumeric,
        'date' => now()->toDateString(),
        'total_amount' => 10,
        'created_by' => $user->id,
        'branch_id' => $user->branch_id,
    ]);

    // Newer row with a non-standard number must not reset the sequence.
    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => $customSuffix,
        'date' => now()->toDateString(),
        'total_amount' => 10,
        'created_by' => $user->id,
        'branch_id' => $user->branch_id,
    ]);

    expect(VoucherService::nextVoucherNo(VoucherType::Expense))->toBe('EXP-'.($base + 51));
});

test('creating an expense voucher allocates the next sequential number', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create();
    $user->givePermissionTo('accounts.create');
    $cash = seedAccountingAccounts(user: $user);
    SystemAccountService::seed($user->branch_id);
    $expense = SystemAccountService::resolve(SystemAccountKey::RentExpense, $user->branch_id);

    expect($expense->type)->toBe(AccountType::Expenses)
        ->and($expense->parent_id)->not->toBeNull();

    $expected = VoucherService::nextVoucherNo(VoucherType::Expense);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Expense->value,
            'voucher_no' => 'EXP-1002',
            'date' => now()->format('Y-m-d'),
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $expense->id,
                    'amount' => 25,
                    'narration' => 'Auto number',
                ],
            ],
        ])
        ->assertRedirect(route('accounts.vouchers.index', ['type' => 'expense']))
        ->assertSessionHas('success');

    expect(Voucher::query()->latest('id')->value('voucher_no'))->toBe($expected);
});
