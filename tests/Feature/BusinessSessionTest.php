<?php

use App\Enums\AccountType;
use App\Enums\BusinessSessionOpeningMethod;
use App\Enums\BusinessSessionStatus;
use App\Enums\CommonStatus;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherType;
use App\Http\Controllers\Account\BusinessSessionController;
use App\Models\Branch;
use App\Models\BusinessSession;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BusinessSessionReportService;
use App\Services\BusinessSessionService;
use App\Services\InventoryAccountingService;
use App\Services\SystemAccountService;
use App\Services\TransactionService;
use App\Services\VoucherService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function businessSessionUser(?int $branchId = null, array $permissions = []): User
{
    $user = User::factory()->create(['branch_id' => $branchId]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

function grantBusinessSessionPermissions(User $user): void
{
    foreach ([
        BusinessSessionController::PERMISSION_VIEW,
        BusinessSessionController::PERMISSION_START,
        BusinessSessionController::PERMISSION_CLOSE,
        BusinessSessionController::PERMISSION_EXPORT,
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}

test('login page renders session mode selection flow', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
});

test('user can start business session during login when requested', function () {
    $user = businessSessionUser(null, ['business-session.start', 'dashboard.view']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'start_business_session' => '1',
    ])->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('business_sessions', [
        'started_by_user_id' => $user->id,
        'branch_id' => null,
        'status' => BusinessSessionStatus::Open->value,
        'opening_method' => BusinessSessionOpeningMethod::StartedDuringLogin->value,
    ]);
});

test('login without session flag does not create business session', function () {
    $user = businessSessionUser(null, ['business-session.start', 'dashboard.view']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'start_business_session' => '0',
    ])->assertRedirect(route('dashboard'));

    expect(BusinessSession::query()->where('started_by_user_id', $user->id)->count())->toBe(0);
});

test('authenticated user can manually start and close a business session', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($user);

    $this->actingAs($user)
        ->post(route('accounts.daily-sessions.store'))
        ->assertRedirect();

    $session = BusinessSession::query()->where('branch_id', $branch->id)->first();
    expect($session)->not->toBeNull()
        ->and($session->status)->toBe(BusinessSessionStatus::Open);

    $this->actingAs($user)
        ->getJson(route('accounts.daily-sessions.close-preview'))
        ->assertOk()
        ->assertJsonStructure([
            'session' => ['id', 'session_number'],
            'report' => ['session', 'account_balances', 'closing_summary'],
        ]);

    $this->actingAs($user)
        ->post(route('accounts.daily-sessions.close-confirm'))
        ->assertRedirect(route('accounts.daily-sessions.index'));

    $session->refresh();
    expect($session->status)->toBe(BusinessSessionStatus::Closed)
        ->and($session->report_snapshot)->not->toBeNull();
});

test('transactions created during active session receive business session id', function () {
    $branch = Branch::factory()->create();
    SystemAccountService::seed($branch->id);
    $user = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($user);

    $this->actingAs($user)->post(route('accounts.daily-sessions.store'));

    $cashAccount = ChartOfAccount::query()
        ->where('source_type', Branch::class)
        ->where('source_id', $branch->id)
        ->paymentAccount()
        ->first();

    $incomeParent = ChartOfAccount::query()
        ->where('source_type', Branch::class)
        ->where('source_id', $branch->id)
        ->whereNotNull('parent_id')
        ->where('type', AccountType::Income)
        ->first();

    expect($cashAccount)->not->toBeNull()
        ->and($incomeParent)->not->toBeNull();

    $transaction = TransactionService::recordTransaction([
        'source_type' => User::class,
        'source_id' => $user->id,
        'date' => now()->toDateString(),
        'amount' => 100,
        'debit_account_id' => $cashAccount->id,
        'credit_account_id' => $incomeParent->id,
        'description' => 'Test receipt',
    ], validateBalance: false);

    expect($transaction->business_session_id)->not->toBeNull();
    expect(Transaction::query()->whereKey($transaction->id)->value('business_session_id'))->not->toBeNull();
});

test('user cannot start second active session', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($user);

    app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $this->actingAs($user)
        ->post(route('accounts.daily-sessions.store'))
        ->assertSessionHasErrors();
});

test('two users in the same branch can each start their own session', function () {
    $branch = Branch::factory()->create();
    $userA = businessSessionUser($branch->id);
    $userB = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($userA);
    grantBusinessSessionPermissions($userB);

    app(BusinessSessionService::class)->start($userA, BusinessSessionOpeningMethod::ManualFromPanel);
    app(BusinessSessionService::class)->start($userB, BusinessSessionOpeningMethod::ManualFromPanel);

    expect(BusinessSession::query()
        ->where('branch_id', $branch->id)
        ->whereIn('status', [
            BusinessSessionStatus::Open,
            BusinessSessionStatus::Reopened,
            BusinessSessionStatus::ClosingPending,
        ])
        ->count())->toBe(2);

    expect(app(BusinessSessionService::class)->activeSessionForUser($userA))->not->toBeNull()
        ->and(app(BusinessSessionService::class)->activeSessionForUser($userB))->not->toBeNull()
        ->and(app(BusinessSessionService::class)->activeSessionForUser($userA)->id)
        ->not->toBe(app(BusinessSessionService::class)->activeSessionForUser($userB)->id);
});

test('user only sees their own sessions in history', function () {
    $branch = Branch::factory()->create();
    $userA = businessSessionUser($branch->id, [BusinessSessionController::PERMISSION_VIEW]);
    $userB = businessSessionUser($branch->id);

    BusinessSession::factory()->forBranch($branch)->create([
        'started_by_user_id' => $userA->id,
        'session_number' => 'BR-USER-A-'.fake()->unique()->numerify('######'),
    ]);

    BusinessSession::factory()->forBranch($branch)->create([
        'started_by_user_id' => $userB->id,
        'session_number' => 'BR-USER-B-'.fake()->unique()->numerify('######'),
    ]);

    $userASessionNumber = BusinessSession::query()
        ->where('started_by_user_id', $userA->id)
        ->value('session_number');

    $this->actingAs($userA)
        ->get(route('accounts.daily-sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/daily-sessions/index')
            ->has('sessions.data', 1)
            ->where('sessions.data.0.session_number', $userASessionNumber));
});

test('user cannot access another users session report', function () {
    $branch = Branch::factory()->create();
    $userA = businessSessionUser($branch->id, [BusinessSessionController::PERMISSION_VIEW]);
    $userB = businessSessionUser($branch->id, [BusinessSessionController::PERMISSION_VIEW]);

    $session = BusinessSession::factory()->forBranch($branch)->closed()->create([
        'started_by_user_id' => $userB->id,
        'session_number' => 'BR-OTHER-'.fake()->unique()->numerify('######'),
        'report_snapshot' => ['session' => ['session_number' => 'BR-OTHER-001']],
    ]);

    $this->actingAs($userA)
        ->getJson(route('accounts.daily-sessions.report', $session))
        ->assertForbidden();
});

test('daily sessions history page requires permission', function () {
    $user = businessSessionUser();

    $this->actingAs($user)
        ->get(route('accounts.daily-sessions.index'))
        ->assertForbidden();
});

test('authorized user can view daily sessions history', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, [BusinessSessionController::PERMISSION_VIEW]);

    BusinessSession::factory()->forBranch($branch)->create([
        'started_by_user_id' => $user->id,
        'session_number' => 'BR-TEST-'.fake()->unique()->numerify('######'),
    ]);

    $this->actingAs($user)
        ->get(route('accounts.daily-sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/accounts/daily-sessions/index')
            ->has('sessions.data', 1));
});

test('session start does not claim chart of account opening balance transactions from before session', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, ['business-session.start', 'business-session.close']);
    seedAccountingAccounts(branchId: $branch->id);

    $cash = SystemAccountService::resolve(SystemAccountKey::CashInHand, $branch->id);

    $openingTransaction = TransactionService::recordJournalEntry([
        'source_type' => ChartOfAccount::class,
        'source_id' => $cash->id,
        'date' => now()->format('Y-m-d'),
        'description' => 'Opening balance — Cash in Hand',
    ], [
        [
            'account_id' => $cash->id,
            'debit' => 1000,
            'credit' => 0,
            'decrease' => false,
        ],
        [
            'account_id' => SystemAccountService::resolve(SystemAccountKey::OpeningBalanceClearing, $branch->id)->id,
            'debit' => 0,
            'credit' => 1000,
            'decrease' => false,
        ],
    ], validateBalance: false);

    expect($openingTransaction->business_session_id)->toBeNull();

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $openingTransaction->refresh();
    expect($openingTransaction->business_session_id)->toBeNull();

    $report = app(BusinessSessionReportService::class)->buildReport($session);

    expect($report['transactions'])->toBe([]);
});

test('closed session report endpoint returns persisted snapshot', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, [
        BusinessSessionController::PERMISSION_VIEW,
        BusinessSessionController::PERMISSION_EXPORT,
    ]);

    $sessionNumber = 'BR-SNAPSHOT-'.fake()->unique()->numerify('######');

    $snapshot = [
        'session' => [
            'session_number' => $sessionNumber,
            'branch_name' => $branch->name,
            'started_at' => '2026-07-01 9:00 AM',
            'closed_at' => '2026-07-01 6:00 PM',
            'duration' => '9h 0m',
            'status' => 'Closed',
        ],
        'account_balances' => [
            [
                'account_id' => 1,
                'account_name' => 'Cash in Hand',
                'account_type' => 'Asset',
                'opening_balance' => 1000,
                'total_received' => 500,
                'total_paid' => 200,
                'closing_balance' => 1300,
            ],
        ],
        'transactions' => [
            [
                'id' => 99,
                'date' => '2026-07-01',
                'time' => '10:00 AM',
                'reference' => 'INC-1001',
                'type' => 'Income',
                'source_account' => '1001 — Cash in Hand',
                'destination_account' => '4001 — Sales',
                'description' => 'Snapshot income',
                'debit' => 500,
                'credit' => 500,
                'is_deleted' => false,
            ],
        ],
        'transfers' => [],
        'income_summary' => [],
        'expense_summary' => [],
        'closing_summary' => [
            'total_opening_balance' => 1000,
            'total_receipts' => 500,
            'total_payments' => 200,
            'total_closing_balance' => 1300,
        ],
    ];

    $session = BusinessSession::factory()->forBranch($branch)->closed()->create([
        'started_by_user_id' => $user->id,
        'session_number' => $sessionNumber,
        'report_snapshot' => $snapshot,
    ]);

    $this->actingAs($user)
        ->getJson(route('accounts.daily-sessions.report', $session))
        ->assertOk()
        ->assertJsonPath('report.transactions.0.reference', 'INC-1001')
        ->assertJsonPath('report.closing_summary.total_receipts', 500)
        ->assertJsonPath('report.account_balances.0.account_name', 'Cash in Hand');
});

test('closing pending session can reopen close modal', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($user);

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);
    app(BusinessSessionService::class)->buildClosingPreview($session, $user);

    $this->actingAs($user)
        ->get(route('branch-panel.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('businessSession.active', true)
            ->where('businessSession.can_close', false)
            ->where('businessSession.can_resume_close', true)
            ->where('businessSession.status', 'Closing Pending'));

    $this->actingAs($user)
        ->getJson(route('accounts.daily-sessions.close-preview'))
        ->assertOk()
        ->assertJsonStructure(['session', 'report', 'can_export']);
});

test('shared inertia props include business session state', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($user);

    $this->actingAs($user)
        ->get(route('accounts.daily-sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('businessSession.active', false)
            ->where('businessSession.can_start', true));

    app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $this->actingAs($user)
        ->get(route('accounts.daily-sessions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('businessSession.active', true)
            ->where('businessSession.can_close', true));
});

test('session start does not claim same day transactions created before session', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, ['accounts.create', 'business-session.start', 'business-session.close']);
    seedAccountingAccounts(branchId: $branch->id);

    $incomeAccount = ChartOfAccount::query()
        ->where('source_type', Branch::class)
        ->where('source_id', $branch->id)
        ->where('type', AccountType::Income)
        ->whereNotNull('parent_id')
        ->first();

    $paymentAccount = seedAccountingAccounts(branchId: $branch->id);

    expect($incomeAccount)->not->toBeNull();

    $this->actingAs($user);

    $voucher = app(VoucherService::class)->store([
        'type' => VoucherType::Income->value,
        'voucher_no' => 'INC-TEST-'.fake()->unique()->numerify('####'),
        'date' => now()->format('Y-m-d'),
        'payment_account_id' => $paymentAccount->id,
        'narration' => 'Morning income before session',
        'lines' => [
            [
                'account_id' => $incomeAccount->id,
                'amount' => 500,
            ],
        ],
    ], $user);

    $preSessionTransaction = Transaction::query()->findOrFail($voucher->transaction_id);
    expect($preSessionTransaction->business_session_id)->toBeNull();

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $preSessionTransaction->refresh();
    expect($preSessionTransaction->business_session_id)->toBeNull();

    $report = app(BusinessSessionReportService::class)->buildReport($session);

    expect(collect($report['income_summary'])->sum('total_amount'))->toEqual(0.0)
        ->and($report['transactions'])->toBe([]);
});

test('session report includes only transactions created after session start', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, ['accounts.create', 'business-session.start', 'business-session.close']);
    seedAccountingAccounts(branchId: $branch->id);

    $incomeAccount = ChartOfAccount::query()
        ->where('source_type', Branch::class)
        ->where('source_id', $branch->id)
        ->where('type', AccountType::Income)
        ->whereNotNull('parent_id')
        ->first();

    $paymentAccount = seedAccountingAccounts(branchId: $branch->id);

    $this->actingAs($user);

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $voucher = app(VoucherService::class)->store([
        'type' => VoucherType::Income->value,
        'voucher_no' => 'INC-TEST-'.fake()->unique()->numerify('####'),
        'date' => now()->format('Y-m-d'),
        'payment_account_id' => $paymentAccount->id,
        'narration' => 'Income during active session',
        'lines' => [
            [
                'account_id' => $incomeAccount->id,
                'amount' => 750,
            ],
        ],
    ], $user);

    $sessionTransaction = Transaction::query()->findOrFail($voucher->transaction_id);
    expect($sessionTransaction->business_session_id)->toBe($session->id);

    $report = app(BusinessSessionReportService::class)->buildReport($session);

    expect(collect($report['income_summary'])->sum('total_amount'))->toBe(750.0)
        ->and($report['transactions'])->not->toBeEmpty();
});

test('session report includes accounts created after session start', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, ['accounts.create', 'business-session.start', 'business-session.close']);
    seedAccountingAccounts(branchId: $branch->id);

    $this->actingAs($user);

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $cashBankParent = SystemAccountService::resolve(SystemAccountKey::CashAndBank, $branch->id);

    $bank = ChartOfAccount::query()->create([
        ...ChartOfAccount::panelSourceAttributes($branch->id),
        'parent_id' => $cashBankParent->id,
        'type' => AccountType::Asset,
        'name' => 'Dutch Bangla Bank',
        'status' => CommonStatus::Active,
        'current_balance' => 0,
    ]);

    app(InventoryAccountingService::class)->postAccountOpeningBalance(
        $bank,
        4000000,
        now()->format('Y-m-d'),
    );

    $report = app(BusinessSessionReportService::class)->buildReport($session->fresh());

    $bankRow = collect($report['account_balances'])->firstWhere('account_name', 'Dutch Bangla Bank');

    expect($bankRow)->not->toBeNull()
        ->and($bankRow['opening_balance'])->toBe(4000000.0)
        ->and($bankRow['total_received'])->toBe(0.0)
        ->and($bankRow['closing_balance'])->toBe(4000000.0);

    $unusedAccount = collect($report['account_balances'])->firstWhere('account_name', 'Cash in Hand');

    expect($unusedAccount)->toBeNull();
});

test('session report hides accounts with no session activity', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id, ['business-session.start', 'business-session.close']);
    seedAccountingAccounts(branchId: $branch->id);

    $this->actingAs($user);

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $report = app(BusinessSessionReportService::class)->buildReport($session);

    expect(collect($report['account_balances'])->pluck('account_name'))
        ->not->toContain('Cash in Hand')
        ->and($report['closing_summary']['total_opening_balance'])->toBeGreaterThan(0);
});

test('session report lists contra vouchers in transfers and excludes them from transactions', function () {
    $branch = Branch::factory()->create();
    $user = businessSessionUser($branch->id);
    grantBusinessSessionPermissions($user);
    seedAccountingAccounts(branchId: $branch->id);

    $cash = SystemAccountService::resolve(SystemAccountKey::CashInHand, $branch->id);
    $bankParentId = SystemAccountService::id(SystemAccountKey::CashAndBank, $branch->id);
    $bank = ChartOfAccount::query()
        ->where('source_type', Branch::class)
        ->where('source_id', $branch->id)
        ->where('parent_id', $bankParentId)
        ->whereKeyNot($cash->id)
        ->first();

    expect($bank)->not->toBeNull();

    $session = app(BusinessSessionService::class)->start($user, BusinessSessionOpeningMethod::ManualFromPanel);

    $this->actingAs($user);

    $voucher = app(VoucherService::class)->store([
        'type' => VoucherType::Contra->value,
        'voucher_no' => 'CONT-TEST-'.fake()->unique()->numerify('####'),
        'date' => now()->format('Y-m-d'),
        'from_account_id' => $cash->id,
        'to_account_id' => $bank->id,
        'total_amount' => 250,
        'narration' => 'Bank deposit',
    ], $user);

    $transaction = Transaction::query()->findOrFail($voucher->transaction_id);
    expect($transaction->business_session_id)->toBe($session->id);

    $report = app(BusinessSessionReportService::class)->buildReport($session);

    expect($report['transfers'])->toHaveCount(1)
        ->and($report['transfers'][0]['reference'])->toBe($voucher->voucher_no)
        ->and($report['transfers'][0]['amount'])->toBe(250.0)
        ->and(collect($report['transactions'])->pluck('reference'))->not->toContain($voucher->voucher_no);
});
