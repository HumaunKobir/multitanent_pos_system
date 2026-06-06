<?php

use App\Models\Branch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

function branchDashboardUser(?int $branchId = null, array $permissions = []): User
{
    $branch = $branchId !== null
        ? Branch::query()->find($branchId)
        : Branch::factory()->create();

    $user = User::factory()->create(['branch_id' => $branch->id]);

    if ($permissions !== []) {
        test()->artisan('permissions:sync');
        $role = Role::create(['name' => 'Branch Dashboard '.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        $user->assignRole($role);
    }

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
    $user = branchDashboardUser($branch->id, ['inventory.purchase.view']);

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

test('branch dashboard includes reports link with permission', function () {
    $this->artisan('permissions:sync');
    $branch = Branch::factory()->create();
    $user = branchDashboardUser($branch->id, ['report.daily-summary.view']);

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('sections.reports')
            ->where('sections.reports.daily_summary_url', '/report/daily-summary'));

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
