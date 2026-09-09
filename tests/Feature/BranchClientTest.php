<?php

use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    test()->artisan('permissions:sync');

    Branch::firstOrCreate(['id' => Branch::MAIN_BRANCH_ID], [
        'name' => Branch::MAIN_BRANCH_NAME,
        'status' => 1,
        'subscription_status' => 'lifetime',
    ]);
});

function branchClientSuperAdmin(): User
{
    $user = User::factory()->create([
        'branch_id' => null,
        'email' => 'admin_'.uniqid().'@test.com',
    ]);
    $user->givePermissionTo(['branch.view', 'branch.update']);

    return $user;
}

test('superadmin can access branch clients hub with stats and filters', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create(['name' => 'Outlet Alpha', 'subscription_fee' => 2000]);

    $response = $this->actingAs($admin)
        ->get(route('branch-clients.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/branch-client/index')
            ->has('branches')
            ->has('stats', fn (Assert $stats) => $stats
                ->has('total_clients')
                ->has('pending_approvals')
                ->has('active_clients')
                ->has('expiring_soon')
                ->has('overdue_clients')
                ->has('suspended_clients')
                ->has('lifetime_clients')
                ->has('total_overdue_due')
            )
            ->has('filters')
            ->has('billingCycles')
            ->has('overdueActions')
            ->has('paymentMethods')
        );
});

test('superadmin can renew branch subscription with receipt upload and transaction reference', function () {
    Storage::fake('public');
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create([
        'subscription_fee' => 1500,
        'subscription_expires_at' => '2026-09-10',
    ]);

    $file = UploadedFile::fake()->image('receipt.png');

    $response = $this->actingAs($admin)
        ->post(route('branch-clients.renew', $branch->id), [
            'duration_days' => 30,
            'amount' => 1500,
            'payment_method' => 'bkash',
            'transaction_reference' => 'BKASH-TRX-12345',
            'paid_at' => '2026-09-08',
            'notes' => 'Renewed via SuperAdmin Client Hub',
            'attachment' => $file,
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success');

    $branch->refresh();
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-10-10');

    $payment = BranchSubscriptionPayment::where('branch_id', $branch->id)->latest('id')->first();
    expect($payment)->not->toBeNull();
    expect((float) $payment->amount)->toBe(1500.0);
    expect($payment->payment_method)->toBe('bkash');
    expect($payment->transaction_reference)->toBe('BKASH-TRX-12345');
    expect($payment->attachment_path)->not->toBeNull();
    Storage::disk('public')->assertExists($payment->attachment_path);
});

test('superadmin can fetch branch payment history json with attachment url', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create();

    BranchSubscriptionPayment::create([
        'branch_id' => $branch->id,
        'amount' => 1500,
        'payment_method' => 'bank',
        'transaction_reference' => 'BANK-SLIP-9988',
        'billing_period_starts_at' => '2026-09-01',
        'billing_period_ends_at' => '2026-10-01',
        'paid_at' => '2026-09-01',
        'recorded_by_user_id' => $admin->id,
        'notes' => 'Bank deposit',
        'attachment_path' => 'subscription-receipts/sample.png',
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('branch-clients.payments', $branch->id));

    $response->assertOk()
        ->assertJsonStructure([
            'branch' => ['id', 'name'],
            'payments' => [
                '*' => [
                    'id',
                    'amount',
                    'payment_method',
                    'transaction_reference',
                    'billing_period_starts_at',
                    'billing_period_ends_at',
                    'paid_at',
                    'recorded_by',
                    'notes',
                    'attachment_path',
                    'attachment_url',
                ],
            ],
        ]);
});

test('branch client list includes latest_payment with attachment url for admin review', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create(['name' => 'Outlet Beta']);

    BranchSubscriptionPayment::create([
        'branch_id' => $branch->id,
        'amount' => 3000,
        'payment_method' => 'bkash',
        'transaction_reference' => 'BKASH-BETA-7788',
        'billing_period_starts_at' => '2026-09-01',
        'billing_period_ends_at' => '2026-10-01',
        'paid_at' => '2026-09-08',
        'notes' => 'Submitted from branch panel',
        'attachment_path' => 'subscription-receipts/beta_receipt.png',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('branch-clients.index', ['search' => 'Outlet Beta']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/branch-client/index')
            ->has('branches.0.latest_payment', fn (Assert $lp) => $lp
                ->where('transaction_reference', 'BKASH-BETA-7788')
                ->where('payment_method', 'bkash')
                ->where('amount', 3000)
                ->where('notes', 'Submitted from branch panel')
                ->has('attachment_url')
                ->etc()
            )
        );
});

test('custom days cycle duration remains strictly unchanged when renewing multiple overdue bills', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create([
        'name' => 'Outlet Custom Cycle',
        'subscription_plan' => 'custom_days',
        'custom_cycle_days' => 2,
        'subscription_fee' => 500,
        'subscription_starts_at' => '2026-09-01',
        'subscription_expires_at' => '2026-09-03',
        'subscription_status' => 'active',
    ]);

    // Check summary before renewal - cycle is 2 days
    $summaryBefore = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryBefore['cycle_days'])->toBe(2);

    // Admin renews for 3 bill cycles (6 days) or 4 cycles (8 days)
    $this->actingAs($admin)
        ->post(route('branch-clients.renew', $branch->id), [
            'duration_days' => 8,
            'amount' => 2000,
            'payment_method' => 'cash',
            'paid_at' => '2026-09-08',
        ]);

    $branch->refresh();
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-09-11');
    expect($branch->custom_cycle_days)->toBe(2);

    // Summary must still have cycle_days = 2, NEVER 8 days
    $summaryAfter = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryAfter['cycle_days'])->toBe(2);
    expect($summaryAfter['plan_label'])->toBe('Custom (2 Days)');
});

test('updating branch subscription configuration strictly preserves overdue expiration date and pending dues', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create([
        'name' => 'Overdue Branch',
        'subscription_plan' => 'monthly',
        'subscription_fee' => 1000,
        'subscription_starts_at' => '2026-08-01',
        'subscription_expires_at' => '2026-09-01',
        'subscription_status' => 'active',
    ]);

    // Update branch config to 2 days custom cycle, new fee, new notes
    $response = $this->actingAs($admin)
        ->put(route('branch-clients.update', $branch->id), [
            'subscription_plan' => 'custom_days',
            'subscription_status' => 'active',
            'custom_cycle_days' => 2,
            'subscription_fee' => 800,
            'subscription_starts_at' => '2026-08-01',
            'subscription_notes' => 'Updated policy for client',
        ]);

    $response->assertRedirect(route('branch-clients.index'))
        ->assertSessionHas('success');

    $branch->refresh();
    // Expiration date MUST stay 2026-09-01 (not wiped or set to future!)
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-09-01');
    expect($branch->custom_cycle_days)->toBe(2);
    expect((float) $branch->subscription_fee)->toBe(800.0);

    // Summary reflects overdue status based on preserved 2026-09-01 expiry
    $summary = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summary['is_overdue'])->toBeTrue();
    expect($summary['expires_at'])->toBe('2026-09-01');
});

test('approving pending payment clears pending receipt and new client submission shows up', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create([
        'name' => 'Outlet Approval Flow',
        'subscription_fee' => 2500,
        'subscription_expires_at' => '2026-09-09',
    ]);

    // 1. Client submits a pending payment with receipt image
    $pendingPayment = BranchSubscriptionPayment::create([
        'branch_id' => $branch->id,
        'amount' => 7500,
        'payment_method' => 'bkash',
        'status' => 'pending',
        'transaction_reference' => 'BKASH-PENDING-99',
        'paid_at' => '2026-09-08',
        'attachment_path' => 'subscription-receipts/sample.png',
    ]);

    expect($pendingPayment->attachment_url)->toBe('/storage/subscription-receipts/sample.png');

    $summaryWithPending = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryWithPending['has_pending_payment'])->toBeTrue();
    expect($summaryWithPending['pending_payment'])->not->toBeNull();
    expect($summaryWithPending['pending_payment']['transaction_reference'])->toBe('BKASH-PENDING-99');

    // 2. Admin approves and confirms renewal
    $this->actingAs($admin)
        ->post(route('branch-clients.renew', $branch->id), [
            'pending_payment_id' => $pendingPayment->id,
            'duration_days' => 6,
            'amount' => 7500,
            'payment_method' => 'bkash',
            'transaction_reference' => 'BKASH-PENDING-99',
            'paid_at' => '2026-09-08',
        ]);

    $pendingPayment->refresh();
    expect($pendingPayment->status)->toBe('approved');

    // 3. After confirmation, pending payment is cleared
    $summaryAfterApproval = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryAfterApproval['has_pending_payment'])->toBeFalse();
    expect($summaryAfterApproval['pending_payment'])->toBeNull();

    // 4. Client submits another new payment later
    $newPayment = BranchSubscriptionPayment::create([
        'branch_id' => $branch->id,
        'amount' => 2500,
        'payment_method' => 'nagad',
        'status' => 'pending',
        'transaction_reference' => 'NAGAD-NEW-1122',
        'paid_at' => '2026-09-15',
        'attachment_path' => 'subscription-receipts/new_receipt.png',
    ]);

    $summaryWithNew = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryWithNew['has_pending_payment'])->toBeTrue();
    expect($summaryWithNew['pending_payment']['transaction_reference'])->toBe('NAGAD-NEW-1122');
    expect($summaryWithNew['pending_payment']['payment_method'])->toBe('nagad');
});

test('all billing cycles calculate expiry and due properly based on subscription starts at', function () {
    $admin = branchClientSuperAdmin();
    $branch = Branch::factory()->create([
        'name' => 'Multi-Cycle Branch',
        'subscription_fee' => 1500,
    ]);

    // Test Monthly (30 days) from 2026-08-01 -> Expiry 2026-08-31 (Overdue by 9 days as of 2026-09-09)
    $this->actingAs($admin)
        ->put(route('branch-clients.update', $branch->id), [
            'subscription_plan' => 'monthly',
            'subscription_status' => 'active',
            'subscription_fee' => 1500,
            'subscription_starts_at' => '2026-08-01',
        ]);

    $branch->refresh();
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-08-31');
    $summary = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summary['cycle_days'])->toBe(30);
    expect($summary['is_overdue'])->toBeTrue();
    expect($summary['pending_bills_count'])->toBe(1);
    expect((float) $summary['total_overdue_fee'])->toBe(1500.0);

    // Test Trial (14 days) from 2026-08-01 -> Expiry 2026-08-15 (Overdue by 25 days -> 2 pending bills)
    $this->actingAs($admin)
        ->put(route('branch-clients.update', $branch->id), [
            'subscription_plan' => 'trial',
            'subscription_status' => 'active',
            'subscription_fee' => 1500,
            'subscription_starts_at' => '2026-08-01',
        ]);

    $branch->refresh();
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-08-15');
    $summaryTrial = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryTrial['cycle_days'])->toBe(14);
    expect($summaryTrial['is_overdue'])->toBeTrue();
    expect($summaryTrial['pending_bills_count'])->toBe(2);
    expect((float) $summaryTrial['total_overdue_fee'])->toBe(3000.0);

    // Test Quarterly (90 days) from 2026-08-01 -> Expiry 2026-10-30 (Not overdue yet)
    $this->actingAs($admin)
        ->put(route('branch-clients.update', $branch->id), [
            'subscription_plan' => 'quarterly',
            'subscription_status' => 'active',
            'subscription_fee' => 4500,
            'subscription_starts_at' => '2026-08-01',
        ]);

    $branch->refresh();
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-10-30');
    $summaryQuarterly = app(\App\Services\BranchSubscriptionService::class)->getSubscriptionSummary($branch);
    expect($summaryQuarterly['cycle_days'])->toBe(90);
    expect($summaryQuarterly['is_overdue'])->toBeFalse();
    expect($summaryQuarterly['pending_bills_count'])->toBe(0);
});
