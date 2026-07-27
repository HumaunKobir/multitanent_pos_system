<?php

use App\Enums\PurchaseType;
use App\Enums\VoucherType;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function branchDashboardUser(?int $branchId = null, array $permissions = []): User
{
    $branch = $branchId !== null
        ? Branch::query()->find($branchId)
        : Branch::factory()->create();

    $user = User::factory()->create(['branch_id' => $branch->id]);

    test()->artisan('permissions:sync');

    $permissions = $permissions === [] ? ['dashboard.view'] : $permissions;

    $role = Role::create(['name' => 'Branch Dashboard '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permissions);
    $user->assignRole($role);

    return $user;
}

test('branch user can visit branch dashboard', function () {
    $user = branchDashboardUser();

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/dashboard')
            ->has('today')
            ->has('branchName')
            ->has('sections')
            ->has('adminNavigation'));

    $user->delete();
});

test('super admin is redirected from branch dashboard', function () {
    $admin = User::factory()->create(['branch_id' => null]);

    $this->actingAs($admin)
        ->get('/branch-panel')
        ->assertRedirect(route('dashboard'));
});

test('branch dashboard includes purchases section with permission', function () {
    $this->artisan('permissions:sync');
    $branch = Branch::factory()->create();
    $user = branchDashboardUser($branch->id, ['dashboard.view', 'inventory.purchase.view']);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.purchases')
            ->has('sections.purchases.today')
            ->has('sections.purchases.month'));

    $user->delete();
    $branch->delete();
});

test('branch dashboard month purchases include initial stock purchases', function () {
    $this->artisan('permissions:sync');

    $date = '2099-05-10';
    $branch = Branch::factory()->create();
    $supplier = Supplier::factory()->create(['branch_id' => $branch->id]);
    $user = branchDashboardUser($branch->id, ['dashboard.view', 'inventory.purchase.view']);

    Purchase::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'supplier_id' => $supplier->id,
        'gross_amount' => 500,
        'paid_amount' => 200,
        'due_amount' => 300,
        'purchase_type' => PurchaseType::Purchase,
        'date' => $date,
    ]);

    Purchase::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'supplier_id' => $supplier->id,
        'gross_amount' => 1200,
        'paid_amount' => 0,
        'due_amount' => 1200,
        'purchase_type' => PurchaseType::InitialStock,
        'date' => $date,
        'comment' => 'Initial stock — Test Product',
    ]);

    Carbon::setTestNow($date);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sections.purchases.month.count', 2)
            ->where('sections.purchases.month.gross', 1700)
            ->where('sections.purchases.month.paid', 200)
            ->where('sections.purchases.month.due', 1500));

    Carbon::setTestNow();

    Purchase::query()->where('branch_id', $branch->id)->delete();
    $supplier->delete();
    $user->delete();
    $branch->delete();
});

test('branch dashboard includes reports link with permission', function () {
    $this->artisan('permissions:sync');
    $branch = Branch::factory()->create();
    $user = branchDashboardUser($branch->id, ['dashboard.view', 'report.daily-summary.view']);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.reports')
            ->where('sections.reports.daily_summary_url', '/report/daily-summary'));

    $user->delete();
    $branch->delete();
});

test('branch dashboard includes expenses section with permission', function () {
    $this->artisan('permissions:sync');

    $date = '2099-04-20';
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = branchDashboardUser($branch->id, ['dashboard.view', 'accounts.view']);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-BR-'.uniqid(),
        'date' => $date,
        'total_amount' => 600,
        'branch_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    Voucher::query()->create([
        'type' => VoucherType::Expense,
        'voucher_no' => 'EXP-OTHER-'.uniqid(),
        'date' => $date,
        'total_amount' => 2000,
        'branch_id' => $otherBranch->id,
        'created_by' => $user->id,
    ]);

    Carbon::setTestNow($date);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.expenses')
            ->where('sections.expenses.today.count', 1)
            ->where('sections.expenses.today.amount', 600)
            ->where('sections.expenses.month.count', 1)
            ->where('sections.expenses.month.amount', 600));

    Carbon::setTestNow();

    Voucher::query()->whereIn('branch_id', [$branch->id, $otherBranch->id])->whereDate('date', $date)->delete();
    $user->delete();
    $branch->delete();
    $otherBranch->delete();
});

test('branch user without dashboard permission sees welcome page', function () {
    $branch = Branch::factory()->create();
    $user = branchDashboardUser($branch->id, ['inventory.purchase.view']);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/dashboard')
            ->where('limitedAccess', true)
            ->where('userName', $user->name)
            ->where('branchName', $branch->name)
            ->has('branchLogoUrl'));

    $user->delete();
    $branch->delete();
});

test('branch user is redirected from admin dashboard route', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('branch-panel.dashboard'));

    $user->delete();
    $branch->delete();
});
