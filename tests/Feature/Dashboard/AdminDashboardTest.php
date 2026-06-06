<?php

use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Sell;
use App\Models\User;
use App\Support\AdminNavigation;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function dashboardSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
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
            ->where('today', $today)
            ->where('kpis.today_sales.count', 1)
            ->where('kpis.today_sales.gross', 1500)
            ->where('kpis.today_sales.paid', 1000));

    Sell::query()->where('branch_id', $branch->id)->delete();
    $branch->delete();
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
            ->has('sections.sales.trend', 30));

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
