<?php

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Models\Party;
use App\Models\User;
use App\Services\SystemAccountService;
use App\Services\TransactionService;
use App\Services\VoucherAccountsPicker;
use Database\Seeders\ChartOfAccountsSeeder;
use Spatie\Permission\Models\Permission;

test('taxes paid is a contra liability under taxes payable', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $taxesPaid = SystemAccountService::resolve(SystemAccountKey::TaxesPaid);
    $taxesHead = SystemAccountService::resolve(SystemAccountKey::TaxesPayable);
    $vatPayable = SystemAccountService::resolve(SystemAccountKey::OutputVat);
    $expensesHead = SystemAccountService::resolve(SystemAccountKey::Expenses);

    expect($taxesPaid->type)->toBe(AccountType::Liability)
        ->and($taxesPaid->name)->toBe('Taxes Paid')
        ->and($taxesPaid->code)->toBe('L004-02')
        ->and($taxesPaid->parent_id)->toBe($taxesHead->id)
        ->and($vatPayable->parent_id)->toBe($taxesHead->id)
        ->and(
            ChartOfAccount::query()
                ->where('parent_id', $expensesHead->id)
                ->where('account_number', SystemAccountKey::TaxesPaid->accountNumber())
                ->exists()
        )->toBeFalse();
});

test('expense voucher picker includes taxes paid and excludes vat payable', function () {
    $this->seed(ChartOfAccountsSeeder::class);

    $taxesPaid = SystemAccountService::resolve(SystemAccountKey::TaxesPaid);
    $vatPayable = SystemAccountService::resolve(SystemAccountKey::OutputVat);
    $picker = VoucherAccountsPicker::forType(VoucherType::Expense);
    $ids = collect($picker)->flatMap(
        fn (array $typeGroup) => collect($typeGroup['groups'])->flatMap(
            fn (array $group) => collect($group['accounts'])->pluck('id')
        )
    );
    $labels = collect($picker)->flatMap(
        fn (array $typeGroup) => collect($typeGroup['groups'])->flatMap(
            fn (array $group) => collect($group['accounts'])->pluck('label')
        )
    );

    expect($ids)->toContain($taxesPaid->id)
        ->and($ids)->not->toContain($vatPayable->id)
        ->and($labels->first(fn (string $label) => str_contains($label, 'Taxes Paid')))->not->toBeNull()
        ->and($labels->first(fn (string $label) => str_contains($label, 'VAT Payable')))->toBeNull()
        ->and($labels->first(fn (string $label) => str_contains($label, 'Purchase Returns')))->toBeNull();
});

test('expense voucher with taxes paid increases contra and decreases net taxes payable', function () {
    $this->artisan('permissions:sync');
    $this->seed(ChartOfAccountsSeeder::class);

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('accounts.create', 'web');
    $user->givePermissionTo('accounts.create');

    SystemAccountService::seed($branch->id);
    $cash = seedAccountingAccounts(user: $user);
    $vatPayable = SystemAccountService::resolve(SystemAccountKey::OutputVat, $branch->id);
    $taxesPaid = SystemAccountService::resolve(SystemAccountKey::TaxesPaid, $branch->id);

    app(TransactionService::class)->recordJournalEntry([
        'source_type' => ChartOfAccount::class,
        'source_id' => $vatPayable->id,
        'date' => now()->format('Y-m-d'),
        'description' => 'Seed VAT payable',
        'performed_by_type' => User::class,
        'performed_by_id' => $user->id,
    ], [
        [
            'account_id' => $cash->id,
            'debit' => 500,
            'credit' => 0,
            'decrease' => false,
        ],
        [
            'account_id' => $vatPayable->id,
            'debit' => 0,
            'credit' => 500,
            'decrease' => false,
        ],
    ]);

    $vatPayable->refresh();
    $taxesPaid->refresh();
    $openingVat = (float) $vatPayable->current_balance;
    $openingTaxesPaid = (float) $taxesPaid->current_balance;
    $openingCash = (float) $cash->fresh()->current_balance;
    $party = Party::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Expense->value,
            'voucher_no' => 'EXP-TAX-'.fake()->unique()->numerify('####'),
            'date' => now()->format('Y-m-d'),
            'party_key' => 'party:'.$party->id,
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $taxesPaid->id,
                    'amount' => 200,
                    'narration' => 'VAT remittance',
                ],
            ],
        ])
        ->assertRedirect(route('accounts.vouchers.index', ['type' => 'expense']))
        ->assertSessionHas('success');

    $vatPayable->refresh();
    $taxesPaid->refresh();
    $cash->refresh();

    expect((float) $vatPayable->current_balance)->toBe($openingVat)
        ->and((float) $taxesPaid->current_balance)->toBe(round($openingTaxesPaid - 200, 2))
        ->and((float) $taxesPaid->current_balance)->toBeLessThan(0)
        ->and(round($openingVat + (float) $taxesPaid->current_balance, 2))->toBe(round($openingVat - 200, 2))
        ->and((float) $cash->current_balance)->toBe(round($openingCash - 200, 2));

    expect(Ledger::query()->where('account_id', $taxesPaid->id)->where('debit', 200)->latest('id')->first())
        ->not->toBeNull();
});
