<?php

use App\Enums\SaleType;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\Sell;
use App\Models\User;
use App\Models\Voucher;
use App\Support\AdminNavigation;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function dashboardSuperAdmin(): User
{
    $user = User::factory()->create(['branch_id' => null]);
    Permission::findOrCreate('dashboard.view', 'web');
    $user->givePermissionTo('dashboard.view');

    return $user;
}

function dashboardBranchUser(?int $branchId = null, array $permissions = []): User
{
    $branch = $branchId !== null
        ? Branch::query()->find($branchId)
        : Branch::factory()->create();

    $user = User::factory()->create(['branch_id' => $branch->id]);

    if ($permissions !== []) {
        test()->artisan('permissions:sync');
        $role = Role::create(['name' => 'Dashboard Test '.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user->assignRole($role);
    }

    return $user;
}

test('super admin dashboard returns sell report with period filter', function () {
    $date = '2099-06-15';
    $branch = Branch::factory()->create(['name' => 'Sell Report Branch '.uniqid()]);
    $admin = dashboardSuperAdmin();

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
        'date' => '2099-06-10',
        'gross_amount' => 2000,
        'paid_amount' => 1500,
    ]);

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
        'date' => '2098-06-10',
        'gross_amount' => 9000,
        'paid_amount' => 9000,
    ]);

    Carbon::setTestNow($date);

    $this->actingAs($admin)
        ->get(route('dashboard', ['period' => 'current_month']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sellReport')
            ->where('sellReport.period', 'current_month')
            ->where('sellReport.summary.count', 1)
            ->where('sellReport.summary.gross', 2000)
            ->where('sellReport.summary.paid', 1500)
            ->where('sellReport.date_from', '2099-06-01')
            ->where('sellReport.date_to', $date));

    $this->actingAs($admin)
        ->get(route('dashboard', ['period' => 'last_year']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellReport.period', 'last_year')
            ->where('sellReport.summary.count', 1)
            ->where('sellReport.summary.gross', 9000)
            ->where('sellReport.date_from', '2098-01-01')
            ->where('sellReport.date_to', '2098-12-31'));

    Carbon::setTestNow();

    Sell::query()->where('branch_id', $branch->id)->delete();
    $branch->delete();
    $admin->delete();
});

test('super admin dashboard supports last 7 days and custom range period filters', function () {
    $date = '2188-08-15';
    $branch = Branch::factory()->create(['name' => 'Range Filter Branch '.uniqid()]);
    $admin = dashboardSuperAdmin();

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
        'date' => '2188-08-14',
        'gross_amount' => 700,
        'paid_amount' => 700,
    ]);

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
        'date' => '2188-08-05',
        'gross_amount' => 300,
        'paid_amount' => 300,
    ]);

    Carbon::setTestNow($date);

    $this->actingAs($admin)
        ->get(route('dashboard', ['period' => 'last_7_days']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellReport.period', 'last_7_days')
            ->where('sellReport.summary.count', 1)
            ->where('sellReport.summary.gross', 700)
            ->where('sellReport.date_from', '2188-08-09')
            ->where('sellReport.date_to', $date));

    $this->actingAs($admin)
        ->get(route('dashboard', ['period' => 'custom']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellReport.period', 'custom')
            ->where('sellReport.date_from', null)
            ->where('sellReport.date_to', null)
            ->where('sellReport.summary.count', 0));

    $this->actingAs($admin)
        ->get(route('dashboard', [
            'period' => 'custom',
            'date_from' => '2188-08-01',
            'date_to' => '2188-08-10',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sellReport.period', 'custom')
            ->where('sellReport.summary.count', 1)
            ->where('sellReport.summary.gross', 300)
            ->where('sellReport.date_from', '2188-08-01')
            ->where('sellReport.date_to', '2188-08-10'));

    Carbon::setTestNow();

    Sell::query()->where('branch_id', $branch->id)->delete();
    $branch->delete();
    $admin->delete();
});

test('super admin dashboard returns branch sales and trend props', function () {
    $today = Carbon::today()->toDateString();
    $branch = Branch::factory()->create(['name' => 'Dashboard Test Branch '.uniqid()]);
    $admin = dashboardSuperAdmin();

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
        'date' => $today,
        'gross_amount' => 1500,
        'paid_amount' => 1000,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('kpis')
            ->has('branchSales')
            ->has('salesTrend', 30)
            ->has('collection')
            ->has('sellReport')
            ->where('today', $today)
            ->where('kpis.today_sales.count', 1)
            ->where('kpis.today_sales.gross', 1500)
            ->where('kpis.today_sales.paid', 1000));

    Sell::query()->where('branch_id', $branch->id)->delete();
    $branch->delete();
});

test('super admin dashboard includes expense totals excluding main branch', function () {
    $date = '2099-03-15';
    $branch = Branch::factory()->create(['name' => 'Expense Branch '.uniqid()]);
    $admin = dashboardSuperAdmin();

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-DASH-'.uniqid(),
        'date' => $date,
        'total_amount' => 450,
        'branch_id' => $branch->id,
        'created_by' => $admin->id,
    ]);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-MAIN-'.uniqid(),
        'date' => $date,
        'total_amount' => 9999,
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'created_by' => $admin->id,
    ]);

    Carbon::setTestNow($date);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.today_expenses.count', 1)
            ->where('kpis.today_expenses.amount', 450)
            ->where('kpis.month_expenses.count', 1)
            ->where('kpis.month_expenses.amount', 450));

    Carbon::setTestNow();

    Voucher::query()->whereIn('branch_id', [$branch->id, Branch::MAIN_BRANCH_ID])->whereDate('date', $date)->delete();
    $branch->delete();
    $admin->delete();
});

test('branch user dashboard nav link points to branch panel', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $dashboard = collect(app(AdminNavigation::class)->build($user))->firstWhere('title', 'Dashboard');

    expect($dashboard['href'])->toBe(route('branch-panel.dashboard'));

    $user->delete();
    $branch->delete();
});

test('super admin dashboard nav link points to admin panel', function () {
    $admin = dashboardSuperAdmin();
    $dashboard = collect(app(AdminNavigation::class)->build($admin))->firstWhere('title', 'Dashboard');

    expect($dashboard['href'])->toBe(route('dashboard'));

    $admin->delete();
});

test('admin branch sales excludes main branch id 1', function () {
    $today = Carbon::today()->toDateString();
    $activeBranch = Branch::factory()->create(['name' => 'Selling Branch '.uniqid()]);
    $admin = dashboardSuperAdmin();

    Sell::factory()->create([
        'branch_id' => Branch::MAIN_BRANCH_ID,
        'type' => SaleType::Sale,
        'date' => $today,
        'gross_amount' => 9999,
        'paid_amount' => 9999,
    ]);

    Sell::factory()->create([
        'branch_id' => $activeBranch->id,
        'type' => SaleType::Sale,
        'date' => $today,
        'gross_amount' => 500,
        'paid_amount' => 500,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branchSales', 1)
            ->where('branchSales.0.branch_id', $activeBranch->id));

    Sell::query()->whereIn('branch_id', [Branch::MAIN_BRANCH_ID, $activeBranch->id])->delete();
    $activeBranch->delete();
    $admin->delete();
});

test('branch user cannot access admin dashboard url', function () {
    $user = dashboardBranchUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('branch-panel.dashboard'));

    $user->delete();
});

test('branch user legacy admin url redirects to branch panel', function () {
    $user = dashboardBranchUser();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('branch-panel.dashboard'));

    $user->delete();
});

test('branch dashboard includes sales section when user has permission', function () {
    $today = Carbon::today()->toDateString();
    $branch = Branch::factory()->create();
    $user = dashboardBranchUser($branch->id, ['inventory.sell.view']);

    Sell::factory()->create([
        'branch_id' => $branch->id,
        'type' => SaleType::Sale,
        'date' => $today,
        'gross_amount' => 800,
        'paid_amount' => 500,
    ]);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/dashboard')
            ->where('branchName', $branch->name)
            ->has('sections.sales')
            ->where('sections.sales.today.count', 1)
            ->where('sections.sales.today.gross', 800)
            ->has('sections.sales.trend', 30)
            ->has('sections.sales.report')
            ->where('sections.sales.report.period', 'current_month')
            ->where('sections.sales.report.summary.gross', 800));

    $this->actingAs($user)
        ->get('/branch-panel?period=previous_week')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.sales.report.period', 'previous_week'));

    Sell::query()->where('branch_id', $branch->id)->delete();
    $user->delete();
    $branch->delete();
});

test('branch dashboard omits sales section without permission', function () {
    $branch = Branch::factory()->create();
    $user = dashboardBranchUser($branch->id);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/dashboard')
            ->has('sections')
            ->missing('sections.sales'));

    $user->delete();
    $branch->delete();
});

test('branch dashboard only includes own branch sales data', function () {
    $today = Carbon::today()->toDateString();
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $userA = dashboardBranchUser($branchA->id, ['inventory.sell.view']);

    Sell::factory()->create([
        'branch_id' => $branchA->id,
        'type' => SaleType::Sale,
        'date' => $today,
        'gross_amount' => 1200,
        'paid_amount' => 1200,
    ]);

    Sell::factory()->create([
        'branch_id' => $branchB->id,
        'type' => SaleType::Sale,
        'date' => $today,
        'gross_amount' => 9000,
        'paid_amount' => 0,
    ]);

    $this->actingAs($userA)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.sales.today.count', 1)
            ->where('sections.sales.today.gross', 1200)
            ->where('sections.sales.today.paid', 1200));

    Sell::query()->whereIn('branch_id', [$branchA->id, $branchB->id])->delete();
    $userA->delete();
    $branchA->delete();
    $branchB->delete();
});
