<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AccountPostingRules;
use App\Services\SystemAccountService;
use App\Services\TransactionService;
use Spatie\Permission\Models\Permission;

function ledgerPartyUser(array $permissions): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function ledgerPartyIncomeAccount(): ChartOfAccount
{
    SystemAccountService::seed();

    ChartOfAccount::$skipCodeGeneration = true;

    $account = ChartOfAccount::query()->firstOrCreate(
        ['code' => 'TEST-ledger-party-income'],
        [
            'parent_id' => SystemAccountService::resolve(SystemAccountKey::SalesRevenue)->id,
            'name' => 'Ledger Party Income',
            'type' => AccountType::Income,
            'status' => CommonStatus::Active,
            'current_balance' => 0,
        ],
    );

    ChartOfAccount::$skipCodeGeneration = false;

    return $account;
}

test('account ledger and transactions show party name when present and N/A when missing', function () {
    $this->artisan('permissions:sync');

    $user = ledgerPartyUser([
        ReportController::PERMISSION_ACCOUNT_LEDGER,
        ReportController::PERMISSION_ACCOUNT_TRANSACTIONS,
        'accounts.create',
    ]);
    $cash = seedAccountingAccounts(user: $user);
    $incomeAccount = ledgerPartyIncomeAccount();
    $party = Party::factory()->create([
        'branch_id' => $user->branch_id,
        'name' => 'Ledger Party Alpha',
    ]);

    $this->actingAs($user)
        ->post('/accounts/vouchers', [
            'type' => VoucherType::Income->value,
            'voucher_no' => 'INC-LEDGER-PARTY-'.fake()->unique()->numerify('####'),
            'date' => '2026-07-10',
            'party_key' => "party:{$party->id}",
            'payment_account_id' => $cash->id,
            'lines' => [
                [
                    'account_id' => $incomeAccount->id,
                    'amount' => 500,
                    'narration' => 'Income with party',
                ],
            ],
        ])
        ->assertRedirect();

    expect(Voucher::query()->latest('id')->value('party_id'))->toBe($party->id);

    TransactionService::recordJournalEntry(
        [
            'source_type' => ChartOfAccount::class,
            'source_id' => $cash->id,
            'date' => '2026-07-11',
            'description' => 'Manual journal without party',
        ],
        [
            [
                'account_id' => $cash->id,
                'debit' => 50,
                'credit' => 0,
                'decrease' => AccountPostingRules::decreaseForSide($cash->type, true),
                'description' => 'Debit',
            ],
            [
                'account_id' => $incomeAccount->id,
                'debit' => 0,
                'credit' => 50,
                'decrease' => AccountPostingRules::decreaseForSide($incomeAccount->type, false),
                'description' => 'Credit',
            ],
        ],
        false,
    );

    $this->actingAs($user)
        ->get('/report/account-ledger?account_id='.$cash->id.'&date_from=2026-07-01&date_to=2026-07-31')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/account-ledger')
            ->where('entries', fn ($entries) => collect($entries)->contains(fn ($entry) => ($entry['party'] ?? null) === 'Ledger Party Alpha')
                && collect($entries)->contains(fn ($entry) => ($entry['party'] ?? null) === 'N/A')));

    $this->actingAs($user)
        ->get('/report/account-transactions?account_id='.$cash->id.'&date_from=2026-07-01&date_to=2026-07-31')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/reports/account-transactions')
            ->where('transactions', fn ($transactions) => collect($transactions)->contains(fn ($txn) => ($txn['party'] ?? null) === 'Ledger Party Alpha')
                && collect($transactions)->contains(fn ($txn) => ($txn['party'] ?? null) === 'N/A')));
});
