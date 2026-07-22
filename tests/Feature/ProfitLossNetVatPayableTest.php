<?php

use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\User;
use App\Services\ReportService;
use App\Services\SystemAccountService;
use App\Services\TransactionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Spatie\Permission\Models\Permission;

test('profit and loss net vat payable decreases after vat payable expense', function () {
    $this->artisan('permissions:sync');
    $this->seed(ChartOfAccountsSeeder::class);

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('accounts.create', 'web');
    $user->givePermissionTo('accounts.create');

    SystemAccountService::seed($branch->id);
    $cash = seedAccountingAccounts(user: $user);
    $vatPayable = SystemAccountService::resolve(SystemAccountKey::OutputVat, $branch->id);
    $date = now()->format('Y-m-d');

    app(TransactionService::class)->recordJournalEntry([
        'source_type' => ChartOfAccount::class,
        'source_id' => $vatPayable->id,
        'date' => $date,
        'description' => 'Collect VAT',
        'performed_by_type' => User::class,
        'performed_by_id' => $user->id,
    ], [
        [
            'account_id' => $cash->id,
            'debit' => 300,
            'credit' => 0,
            'decrease' => false,
        ],
        [
            'account_id' => $vatPayable->id,
            'debit' => 0,
            'credit' => 300,
            'decrease' => false,
        ],
    ]);

    $before = app(ReportService::class)->profitAndLoss($date, $date, $branch->id);

    expect($before['vat_collected'])->toBe(300.0)
        ->and($before['vat_paid'])->toBe(0.0)
        ->and($before['vat_payable'])->toBe(300.0)
        ->and($before['net_vat_payable'])->toBe(300.0);

    $party = Party::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Expense->value,
            'voucher_no' => 'EXP-PL-'.fake()->unique()->numerify('####'),
            'date' => $date,
            'party_key' => 'party:'.$party->id,
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $vatPayable->id,
                    'amount' => 120,
                    'narration' => 'Pay VAT',
                ],
            ],
        ])
        ->assertRedirect(route('accounts.vouchers.index', ['type' => 'expense']));

    $after = app(ReportService::class)->profitAndLoss($date, $date, $branch->id);

    expect($after['vat_collected'])->toBe(300.0)
        ->and($after['vat_paid'])->toBe(120.0)
        ->and($after['vat_payable'])->toBe(180.0)
        ->and($after['net_vat_payable'])->toBe(180.0)
        ->and($after['output_vat'])->toBe(180.0);

    $vatSection = collect($after['sections'])->firstWhere('slug', 'vat_payable');
    expect($vatSection['total'])->toBe(180.0)
        ->and($vatSection['type'])->toBe('VAT Payable (liability)')
        ->and($vatSection['lines'][0]['code'])->toBe('L004-01')
        ->and($vatSection['lines'][0]['name'])->toBe('VAT Payable')
        ->and($vatSection['lines'][1]['code'])->toBe('L004-01')
        ->and($vatSection['lines'][1]['name'])->toBe('(−) VAT Paid');
});
