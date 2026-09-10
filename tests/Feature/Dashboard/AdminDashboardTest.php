<?php

use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\Sell;
use App\Models\User;
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

test('super admin dashboard returns saas subscription kpis', function () {
    $date = '2099-06-15';

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    $client = Branch::factory()->create([
        'name' => 'SaaS Client '.uniqid(),
        'subscription_status' => 'active',
        'subscription_fee' => 1500,
        'subscription_expires_at' => '2099-07-01',
    ]);
    $admin = dashboardSuperAdmin();

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $client->id,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'status' => 'approved',
        'paid_at' => $date,
        'billing_period_starts_at' => '2099-06-01',
        'billing_period_ends_at' => '2099-07-01',
    ]);

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $client->id,
        'amount' => 900,
        'payment_method' => 'Nagad',
        'status' => 'pending',
        'paid_at' => $date,
    ]);

    Carbon::setTestNow($date);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('kpis')
            ->has('statusBreakdown')
            ->has('recentPending')
            ->has('links')
            ->missing('sellReport')
            ->where('today', $date)
            ->where('kpis.total_clients', fn ($v) => (int) $v >= 1)
            ->where('kpis.month_collected', fn ($v) => (float) $v >= 1500)
            ->where('kpis.pending_approvals', fn ($v) => (int) $v >= 1)
            ->where('kpis.pending_amount', fn ($v) => (float) $v >= 900));

    Carbon::setTestNow();

    BranchSubscriptionPayment::query()->where('branch_id', $client->id)->delete();
    $admin->delete();
});

test('super admin dashboard excludes main branch from client totals and exposes system name', function () {
    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    Branch::factory()->create(['name' => 'Operating Client '.uniqid()]);
    $admin = dashboardSuperAdmin();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis.total_clients', fn ($count) => (int) $count >= 1)
            ->where('kpis.system_name', fn ($name) => is_string($name) && $name !== ''));

    $admin->delete();
});

test('branch user dashboard nav link points to branch panel', function () {
    $user = dashboardBranchUser(null, ['dashboard.view']);

    $nav = app(AdminNavigation::class)->build($user);
    $dashboard = collect($nav)->firstWhere('title', 'Dashboard');

    expect($dashboard)->not->toBeNull();
    expect($dashboard['href'] ?? '')->toContain('branch-panel');

    $user->delete();
});

test('branch user is redirected away from admin dashboard url', function () {
    $user = dashboardBranchUser(null, ['dashboard.view']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('branch-panel.dashboard'));

    $user->delete();
});

test('branch panel dashboard is scoped to the authenticated branch', function () {
    $today = Carbon::today()->toDateString();
    $branchA = Branch::factory()->create(['name' => 'Dash Branch A '.uniqid()]);
    $branchB = Branch::factory()->create(['name' => 'Dash Branch B '.uniqid()]);
    $userA = dashboardBranchUser($branchA->id, ['dashboard.view', 'inventory.sell.view']);

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
});
