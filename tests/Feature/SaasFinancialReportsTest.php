<?php

use App\Enums\SystemAccountKey;
use App\Http\Controllers\Reports\ReportController;
use App\Models\Branch;
use App\Models\User;
use App\Services\BranchSubscriptionAccountingService;
use App\Services\ReportService;
use App\Services\SystemAccountService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->artisan('permissions:sync');

    Branch::firstOrCreate(['id' => Branch::MAIN_BRANCH_ID], [
        'name' => Branch::MAIN_BRANCH_NAME,
        'status' => 1,
        'subscription_status' => 'lifetime',
    ]);

    SystemAccountService::seed(null);
});

test('main admin balance sheet includes subscription receivable after accrual', function () {
    $this->withoutVite();
    $this->travelTo('2026-09-10 12:00:00');

    $admin = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $admin->givePermissionTo([ReportController::PERMISSION_BALANCE_SHEET]);

    $branch = Branch::factory()->create([
        'subscription_fee' => 4500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);
    SystemAccountService::seed($branch->id);

    $receivable = SystemAccountService::resolve(SystemAccountKey::SubscriptionReceivable, null);
    $this->actingAs($admin);

    $beforeSheet = app(ReportService::class)->balanceSheet('2026-09-10');
    $beforeLine = collect(collect($beforeSheet['sections'])->firstWhere('slug', 'asset')['lines'] ?? [])
        ->firstWhere('code', $receivable->code);
    $beforeBalance = (float) ($beforeLine['balance'] ?? 0);

    app(BranchSubscriptionAccountingService::class)
        ->recordCycleAccrual($branch, '2026-09-01', '2026-10-01', 4500);

    $sheet = app(ReportService::class)->balanceSheet('2026-09-10');
    $receivableLine = collect(collect($sheet['sections'])->firstWhere('slug', 'asset')['lines'] ?? [])
        ->firstWhere('code', $receivable->code);

    expect($receivableLine)->not->toBeNull()
        ->and((float) $receivableLine['balance'])->toBe($beforeBalance + 4500.0)
        ->and($sheet['total_assets'])->toBeGreaterThan(0);

    $this->get('/report/balance-sheet?as_of=2026-09-10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/balance-sheet')
            ->where('sheet.total_assets', fn ($v) => (float) $v > 0));
});

test('saas profit and loss shows subscription income without branch filter', function () {
    $this->withoutVite();

    $admin = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $admin->givePermissionTo([ReportController::PERMISSION_PROFIT_LOSS]);

    $branch = Branch::factory()->create([
        'subscription_fee' => 3000,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);
    SystemAccountService::seed($branch->id);

    app(BranchSubscriptionAccountingService::class)
        ->recordCycleAccrual($branch, '2026-09-01', '2026-10-01', 3000);

    $this->actingAs($admin);

    $report = app(ReportService::class)->profitAndLoss('2026-09-01', '2026-09-10');

    expect($report['variant'])->toBe('saas')
        ->and((float) $report['subscription_income'])->toBeGreaterThanOrEqual(3000.0)
        ->and((float) $report['subscription_expense'])->toBe(0.0)
        ->and(collect($report['sections'])->pluck('slug')->all())->toBe([
            'subscription_income',
            'operating_expenses',
        ]);

    $this->get('/report/profit-loss?date_from=2026-09-01&date_to=2026-09-10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/profit-loss')
            ->where('panelVariant', 'saas')
            ->where('report.variant', 'saas')
            ->missing('branches')
            ->where('report.subscription_income', fn ($v) => (float) $v >= 3000.0));
});

test('branch profit and loss shows subscription expense not subscription income', function () {
    $this->withoutVite();

    $branch = Branch::factory()->create([
        'subscription_fee' => 2200,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-10-01',
    ]);
    SystemAccountService::seed($branch->id);

    $client = User::factory()->create(['branch_id' => $branch->id]);
    $client->givePermissionTo([ReportController::PERMISSION_PROFIT_LOSS]);

    app(BranchSubscriptionAccountingService::class)
        ->recordCycleAccrual($branch, '2026-09-01', '2026-10-01', 2200);

    $this->actingAs($client);

    $report = app(ReportService::class)->profitAndLoss('2026-09-01', '2026-09-10');

    expect($report['variant'])->toBe('branch')
        ->and((float) $report['subscription_income'])->toBe(0.0)
        ->and((float) $report['subscription_expense'])->toBe(2200.0)
        ->and(collect($report['sections'])->pluck('slug')->contains('subscription_income'))->toBeFalse()
        ->and(collect($report['sections'])->pluck('slug')->contains('subscription_expense'))->toBeTrue();

    $this->get('/report/profit-loss?date_from=2026-09-01&date_to=2026-09-10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/profit-loss')
            ->where('panelVariant', 'branch')
            ->where('report.variant', 'branch')
            ->where('report.subscription_expense', 2200)
            ->where('report.subscription_income', 0));
});
