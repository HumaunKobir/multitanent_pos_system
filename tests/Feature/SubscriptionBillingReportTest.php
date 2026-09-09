<?php

use App\Models\Branch;
use App\Models\BranchSubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    BranchSubscriptionPayment::query()->delete();
});

test('guests are redirected from subscription billing report', function () {
    $this->get(route('report.subscription-billing'))
        ->assertRedirect(route('login'));
});

test('superadmin can access subscription billing report with system wide metrics', function () {
    $superAdmin = User::factory()->create(['branch_id' => null]);

    $branchA = Branch::factory()->create(['name' => 'Branch Alpha '.uniqid()]);
    $branchB = Branch::factory()->create(['name' => 'Branch Beta '.uniqid()]);

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $branchA->id,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'status' => 'approved',
        'transaction_reference' => 'TRX-101',
        'billing_period_starts_at' => '2026-01-01',
        'billing_period_ends_at' => '2026-01-31',
        'paid_at' => '2026-01-01',
        'recorded_by_user_id' => $superAdmin->id,
    ]);

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $branchB->id,
        'amount' => 2000,
        'payment_method' => 'Nagad',
        'status' => 'pending',
        'transaction_reference' => 'TRX-102',
        'billing_period_starts_at' => '2026-02-01',
        'billing_period_ends_at' => '2026-02-28',
        'paid_at' => '2026-02-01',
        'recorded_by_user_id' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/subscription-billing')
            ->where('isBranchScoped', false)
            ->has('payments.data', 2)
            ->where('summary.total_collected', 1500)
            ->where('summary.pending_amount', 2000)
            ->where('summary.total_amount', 3500)
            ->where('summary.approved_count', 1)
            ->where('summary.pending_count', 1));
});

test('superadmin can filter subscription billing report by branch and status', function () {
    $superAdmin = User::factory()->create(['branch_id' => null]);

    $branchA = Branch::factory()->create(['name' => 'Alpha '.uniqid()]);
    $branchB = Branch::factory()->create(['name' => 'Beta '.uniqid()]);

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $branchA->id,
        'amount' => 1200,
        'payment_method' => 'bKash',
        'status' => 'approved',
        'paid_at' => '2026-03-01',
    ]);

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $branchB->id,
        'amount' => 2500,
        'payment_method' => 'Cash in Hand',
        'status' => 'approved',
        'paid_at' => '2026-03-05',
    ]);

    // Filter by branchA
    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing', ['branch_id' => $branchA->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.branch_name', $branchA->name)
            ->where('summary.total_collected', 1200));

    // Filter by payment method
    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing', ['payment_method' => 'Cash in Hand']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.branch_name', $branchB->name)
            ->where('summary.total_collected', 2500));
});

test('client branch user is scoped strictly to own branch subscription payments', function () {
    $ownBranch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $ownBranch->id]);
    $otherBranch = Branch::factory()->create();

    $this->artisan('permissions:sync');
    $branchUser->givePermissionTo('report.subscription-billing.view');

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $branchUser->branch_id,
        'amount' => 1800,
        'payment_method' => 'bKash',
        'status' => 'approved',
        'paid_at' => '2026-04-01',
    ]);

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $otherBranch->id,
        'amount' => 9999,
        'payment_method' => 'Nagad',
        'status' => 'approved',
        'paid_at' => '2026-04-01',
    ]);

    $this->actingAs($branchUser)
        ->get(route('report.subscription-billing'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports/subscription-billing')
            ->where('isBranchScoped', true)
            ->has('payments.data', 1)
            ->where('payments.data.0.amount', 1800)
            ->where('summary.total_collected', 1800));
});

test('subscription billing report exports excel, csv, pdf, and print', function () {
    $superAdmin = User::factory()->create(['branch_id' => null]);
    $branch = Branch::factory()->create();

    BranchSubscriptionPayment::query()->create([
        'branch_id' => $branch->id,
        'amount' => 1500,
        'payment_method' => 'bKash',
        'status' => 'approved',
        'transaction_reference' => 'TRX-EXP-1',
        'paid_at' => '2026-05-01',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing.export-excel'))
        ->assertOk()
        ->assertDownload();

    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing.export-csv'))
        ->assertOk()
        ->assertDownload();

    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing.export-pdf'))
        ->assertOk();

    $this->actingAs($superAdmin)
        ->get(route('report.subscription-billing.export-print'))
        ->assertOk();
});
