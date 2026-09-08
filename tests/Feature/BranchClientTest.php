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
    $user = User::factory()->create(['branch_id' => null]);
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
