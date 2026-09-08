<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\BranchSubscriptionService;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    test()->artisan('permissions:sync');

    Branch::firstOrCreate(['id' => Branch::MAIN_BRANCH_ID], [
        'name' => Branch::MAIN_BRANCH_NAME,
        'status' => 1,
        'subscription_status' => 'lifetime',
    ]);
});

function createOperatingBranch(array $attributes = []): Branch
{
    return Branch::factory()->create($attributes);
}

test('branch subscription calculates active, expiring soon, grace period, and suspended correctly', function () {
    $service = app(BranchSubscriptionService::class);
    $today = Carbon::today();

    // 1. Active branch (30 days remaining)
    $activeBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->addDays(30)->toDateString(),
        'subscription_status' => 'active',
    ]);
    $activeSummary = $service->getSubscriptionSummary($activeBranch);
    expect($activeSummary['computed_status'])->toBe('active');
    expect($activeSummary['is_active'])->toBeTrue();
    expect($activeSummary['is_expiring_soon'])->toBeFalse();
    expect($activeSummary['is_overdue'])->toBeFalse();
    expect($activeSummary['is_suspended'])->toBeFalse();

    // 2. Expiring soon (3 days remaining, warning threshold is 5 days)
    $expiringBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->addDays(3)->toDateString(),
        'subscription_status' => 'active',
        'custom_warning_days' => 5,
    ]);
    $expiringSummary = $service->getSubscriptionSummary($expiringBranch);
    expect($expiringSummary['computed_status'])->toBe('expiring_soon');
    expect($expiringSummary['is_active'])->toBeTrue();
    expect($expiringSummary['is_expiring_soon'])->toBeTrue();
    expect($expiringSummary['is_overdue'])->toBeFalse();
    expect($expiringSummary['is_suspended'])->toBeFalse();

    // 3. Overdue within grace period (2 days overdue, grace period is 7 days)
    $graceBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->subDays(2)->toDateString(),
        'subscription_status' => 'active',
        'custom_grace_period_days' => 7,
    ]);
    $graceSummary = $service->getSubscriptionSummary($graceBranch);
    expect($graceSummary['computed_status'])->toBe('grace_period');
    expect($graceSummary['is_active'])->toBeTrue();
    expect($graceSummary['is_in_grace_period'])->toBeTrue();
    expect($graceSummary['is_overdue'])->toBeTrue();
    expect($graceSummary['is_suspended'])->toBeFalse();
    expect($graceSummary['grace_days_remaining'])->toBe(5);

    // 4. Overdue past grace period (10 days overdue, grace period is 7 days, action suspend_branch)
    $suspendedBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->subDays(10)->toDateString(),
        'subscription_status' => 'active',
        'custom_grace_period_days' => 7,
        'custom_overdue_action' => 'suspend_branch',
    ]);
    $suspendedSummary = $service->getSubscriptionSummary($suspendedBranch);
    expect($suspendedSummary['computed_status'])->toBe('suspended');
    expect($suspendedSummary['is_active'])->toBeFalse();
    expect($suspendedSummary['is_suspended'])->toBeTrue();
    expect($suspendedSummary['is_overdue'])->toBeTrue();
});

test('suspended branch user is redirected to subscription-suspended landing page', function () {
    $today = Carbon::today();
    $suspendedBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->subDays(20)->toDateString(),
        'subscription_status' => 'active',
        'custom_grace_period_days' => 5,
        'custom_overdue_action' => 'suspend_branch',
    ]);

    $user = User::factory()->create(['branch_id' => $suspendedBranch->id]);

    $response = $this->actingAs($user)
        ->get(route('branch-panel.dashboard'));

    $response->assertRedirect(route('branch-panel.subscription-suspended'));
});

test('suspended landing page renders subscription details for branch user', function () {
    $today = Carbon::today();
    $suspendedBranch = createOperatingBranch([
        'name' => 'Suspended Store',
        'subscription_expires_at' => $today->copy()->subDays(20)->toDateString(),
        'subscription_status' => 'active',
        'subscription_fee' => 2000,
        'custom_grace_period_days' => 5,
        'custom_overdue_action' => 'suspend_branch',
    ]);

    $user = User::factory()->create(['branch_id' => $suspendedBranch->id]);

    $response = $this->actingAs($user)
        ->get(route('branch-panel.subscription-suspended'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/subscription-suspended')
            ->where('subscription.branch_name', 'Suspended Store')
            ->where('subscription.fee', 2000)
            ->where('subscription.is_suspended', true)
        );
});

test('overdue branch with expired grace period and restrict_sales action blocks sale creation and redirects pos screen', function () {
    $today = Carbon::today();
    $overdueBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->subDays(8)->toDateString(),
        'subscription_status' => 'active',
        'custom_grace_period_days' => 7,
        'custom_overdue_action' => 'restrict_sales',
    ]);

    $user = User::factory()->create(['branch_id' => $overdueBranch->id]);
    $user->givePermissionTo('inventory.sell.create');

    // Visiting POS create screen is redirected to dashboard with error flash
    $posResponse = $this->actingAs($user)
        ->get(route('inventory.sell.create'));
    $posResponse->assertRedirect(route('branch-panel.dashboard'))
        ->assertSessionHas('error');

    // Mutating sell endpoint is blocked
    $response = $this->actingAs($user)
        ->from(route('branch-panel.dashboard'))
        ->post('/inventory/sell', [
            'gross_amount' => 500,
        ], ['X-Inertia' => 'true']);

    $response->assertRedirect(route('branch-panel.dashboard'))
        ->assertSessionHas('error');
});

test('overdue branch within active grace period can still access pos screen', function () {
    $today = Carbon::today();
    $graceBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->subDays(2)->toDateString(),
        'subscription_status' => 'active',
        'custom_grace_period_days' => 7,
        'custom_overdue_action' => 'restrict_sales',
    ]);

    $user = User::factory()->create(['branch_id' => $graceBranch->id]);
    $user->givePermissionTo('inventory.sell.create');

    $response = $this->actingAs($user)
        ->get(route('inventory.sell.create'));

    $response->assertOk();
});

test('active branch user can access dashboard and is not redirected', function () {
    $today = Carbon::today();
    $activeBranch = createOperatingBranch([
        'subscription_expires_at' => $today->copy()->addDays(30)->toDateString(),
        'subscription_status' => 'active',
    ]);

    $user = User::factory()->create(['branch_id' => $activeBranch->id]);

    $response = $this->actingAs($user)
        ->get(route('branch-panel.dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/dashboard')
            ->where('branchSubscription.is_active', true)
            ->where('branchSubscription.is_suspended', false)
        );
});

test('main branch user is never restricted by subscription checks', function () {
    $mainBranch = Branch::query()->find(Branch::MAIN_BRANCH_ID);

    $admin = User::factory()->create([
        'branch_id' => $mainBranch->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('dashboard'));

    $response->assertOk();
});
