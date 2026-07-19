<?php

use App\Enums\AccountType;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AccountPostingRules;
use App\Services\InventoryAccountingService;
use App\Services\ReportService;
use App\Services\SystemAccountService;
use App\Services\TransactionService;
use Inertia\Testing\AssertableInertia as Assert;

function balanceSheetEquityUser(array $permissions = []): User
{
    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $user->givePermissionTo($permissions);

    return $user;
}

test('opening balance clearing closes into owners capital leaving clearing at zero', function () {
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $clearing = SystemAccountService::resolve(SystemAccountKey::OpeningBalanceClearing, Branch::MAIN_BRANCH_ID);
    $capital = SystemAccountService::resolve(SystemAccountKey::OwnersCapital, Branch::MAIN_BRANCH_ID);

    $clearingBefore = round((float) $clearing->fresh()->current_balance, 2);
    $capitalBefore = round((float) $capital->fresh()->current_balance, 2);

    $petty = ChartOfAccount::query()->create([
        ...ChartOfAccount::panelSourceAttributes(Branch::MAIN_BRANCH_ID),
        'parent_id' => SystemAccountService::id(SystemAccountKey::CashAndBank, Branch::MAIN_BRANCH_ID),
        'code' => 'A'.fake()->unique()->numerify('####'),
        'account_number' => 'TEST:petty.'.fake()->unique()->numerify('######'),
        'name' => 'Petty Cash BS '.fake()->unique()->numerify('####'),
        'type' => AccountType::Asset,
        'current_balance' => 0,
        'status' => CommonStatus::Active,
        'is_system' => false,
    ]);

    app(InventoryAccountingService::class)->postAccountOpeningBalance(
        $petty,
        1250.00,
        now()->format('Y-m-d'),
    );

    expect(round((float) $clearing->fresh()->current_balance, 2))->toBe($clearingBefore)
        ->and(round((float) $petty->fresh()->current_balance, 2))->toBe(1250.0)
        ->and(round((float) $capital->fresh()->current_balance, 2))->toBe(round($capitalBefore + 1250.0, 2));
});

test('balance sheet includes current year earnings and stays balanced after profit', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $user = balanceSheetEquityUser([ReportController::PERMISSION_BALANCE_SHEET]);
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $cye = SystemAccountService::resolve(SystemAccountKey::CurrentYearEarnings, Branch::MAIN_BRANCH_ID);
    expect($cye->code)->toBe('E002-01')
        ->and($cye->name)->toBe('Current Year Earnings');

    $this->actingAs($user);

    $date = now()->format('Y-m-d');
    $earningsBefore = app(ReportService::class)->currentYearEarningsAsOf($date);

    $cash = SystemAccountService::resolve(SystemAccountKey::CashInHand, Branch::MAIN_BRANCH_ID);
    $sales = SystemAccountService::resolve(SystemAccountKey::ProductSales, Branch::MAIN_BRANCH_ID);
    $cogs = SystemAccountService::resolve(SystemAccountKey::CostOfGoodsSold, Branch::MAIN_BRANCH_ID);
    $inventory = SystemAccountService::resolve(SystemAccountKey::ProductInventory, Branch::MAIN_BRANCH_ID);

    // Revenue 500, COGS 200 → net profit +300
    TransactionService::recordJournalEntry(
        [
            'source_type' => ChartOfAccount::class,
            'source_id' => $cash->id,
            'date' => $date,
            'description' => 'Test cash sale',
        ],
        [
            [
                'account_id' => $cash->id,
                'debit' => 500,
                'credit' => 0,
                'decrease' => AccountPostingRules::decreaseForSide($cash->type, true),
                'description' => 'Cash in',
            ],
            [
                'account_id' => $sales->id,
                'debit' => 0,
                'credit' => 500,
                'decrease' => AccountPostingRules::decreaseForSide($sales->type, false),
                'description' => 'Sales',
            ],
        ],
        false,
    );

    TransactionService::recordJournalEntry(
        [
            'source_type' => ChartOfAccount::class,
            'source_id' => $inventory->id,
            'date' => $date,
            'description' => 'Test COGS',
        ],
        [
            [
                'account_id' => $cogs->id,
                'debit' => 200,
                'credit' => 0,
                'decrease' => AccountPostingRules::decreaseForSide($cogs->type, true),
                'description' => 'COGS',
            ],
            [
                'account_id' => $inventory->id,
                'debit' => 0,
                'credit' => 200,
                'decrease' => AccountPostingRules::decreaseForSide($inventory->type, false),
                'description' => 'Inventory out',
            ],
        ],
        false,
    );

    $expectedEarnings = round($earningsBefore + 300, 2);

    $this->get('/report/balance-sheet?as_of='.$date)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/balance-sheet')
            ->where('sheet.is_balanced', true)
            ->where('sheet.sections', function ($sections) use ($expectedEarnings): bool {
                $equity = collect($sections)->firstWhere('slug', 'equity');
                $lines = collect($equity['lines'] ?? []);

                return $lines->contains(
                    fn (array $line) => $line['name'] === 'Current Year Earnings'
                        && $line['code'] === 'E002-01'
                        && (float) $line['balance'] === $expectedEarnings
                );
            }));

    $sheet = app(ReportService::class)->balanceSheet($date);
    expect($sheet['is_balanced'])->toBeTrue()
        ->and(abs($sheet['total_assets'] - $sheet['liabilities_plus_equity']))->toBeLessThan(0.02);
});

test('accounting close opening balance clearing command zeros residual clearing', function () {
    seedAccountingAccounts(branchId: Branch::MAIN_BRANCH_ID);

    $clearing = SystemAccountService::resolve(SystemAccountKey::OpeningBalanceClearing, Branch::MAIN_BRANCH_ID);
    $capital = SystemAccountService::resolve(SystemAccountKey::OwnersCapital, Branch::MAIN_BRANCH_ID);

    // Simulate legacy residual stuck in clearing (pre-fix data).
    TransactionService::recordJournalEntry(
        [
            'source_type' => ChartOfAccount::class,
            'source_id' => $clearing->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Legacy clearing residual',
        ],
        [
            [
                'account_id' => SystemAccountService::id(SystemAccountKey::CashInHand, Branch::MAIN_BRANCH_ID),
                'debit' => 5760,
                'credit' => 0,
                'decrease' => false,
                'description' => 'Cash',
            ],
            [
                'account_id' => $clearing->id,
                'debit' => 0,
                'credit' => 5760,
                'decrease' => false,
                'description' => 'Clearing',
            ],
        ],
        false,
    );

    expect(round((float) $clearing->fresh()->current_balance, 2))->toBeGreaterThan(0);

    $capitalBefore = round((float) $capital->fresh()->current_balance, 2);
    $clearingBalance = round((float) $clearing->fresh()->current_balance, 2);

    $this->artisan('accounting:close-opening-balance-clearing')->assertSuccessful();

    expect(round((float) $clearing->fresh()->current_balance, 2))->toBe(0.0)
        ->and(round((float) $capital->fresh()->current_balance, 2))->toBe(round($capitalBefore + $clearingBalance, 2));
});
