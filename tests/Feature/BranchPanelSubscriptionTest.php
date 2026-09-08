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

test('branch panel user can view subscription and billing page', function () {
    $branch = Branch::factory()->create([
        'name' => 'Branch Chittagong',
        'subscription_fee' => 1800,
        'subscription_expires_at' => '2026-09-25',
    ]);

    $user = User::factory()->create(['branch_id' => $branch->id]);

    $response = $this->actingAs($user)
        ->get(route('branch-panel.subscription.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('branch-panel/subscription/index')
            ->has('branch')
            ->has('subscription')
            ->has('payments')
            ->has('paymentMethods')
        );
});

test('branch panel user can submit payment with receipt screenshot', function () {
    Storage::fake('public');

    $branch = Branch::factory()->create([
        'name' => 'Branch Sylhet',
        'subscription_fee' => 1500,
        'subscription_expires_at' => '2026-09-10',
    ]);

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $receipt = UploadedFile::fake()->image('payment_screenshot.jpg');

    $response = $this->actingAs($user)
        ->post(route('branch-panel.subscription.pay'), [
            'duration_days' => 30,
            'amount' => 1500,
            'payment_method' => 'bkash',
            'transaction_reference' => 'BKASH-USER-TRX-9876',
            'paid_at' => '2026-09-08',
            'notes' => 'Branch manager self-renewal payment',
            'attachment' => $receipt,
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success');

    $branch->refresh();
    // Expiration date remains unchanged until SuperAdmin verifies and confirms
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-09-10');

    $payment = BranchSubscriptionPayment::where('branch_id', $branch->id)->latest('id')->first();
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe('pending');
    expect($payment->transaction_reference)->toBe('BKASH-USER-TRX-9876');
    expect($payment->recorded_by_user_id)->toBe($user->id);
    expect($payment->attachment_path)->not->toBeNull();
    Storage::disk('public')->assertExists($payment->attachment_path);
});

test('superadmin can approve and confirm branch client pending payment to renew subscription', function () {
    Storage::fake('public');

    $branch = Branch::factory()->create([
        'name' => 'Branch Sylhet',
        'subscription_fee' => 1500,
        'subscription_expires_at' => '2026-09-10',
    ]);

    $clientUser = User::factory()->create(['branch_id' => $branch->id]);
    $receipt = UploadedFile::fake()->image('payment_screenshot.jpg');

    // Client submits payment
    $this->actingAs($clientUser)
        ->post(route('branch-panel.subscription.pay'), [
            'duration_days' => 30,
            'amount' => 1500,
            'payment_method' => 'bkash',
            'transaction_reference' => 'BKASH-USER-TRX-9876',
            'paid_at' => '2026-09-08',
            'notes' => 'Branch manager self-renewal payment',
            'attachment' => $receipt,
        ]);

    $pendingPayment = BranchSubscriptionPayment::where('branch_id', $branch->id)->latest('id')->first();
    expect($pendingPayment->status)->toBe('pending');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['branch.view', 'branch.update']);

    // Admin verifies screenshot & confirms renewal
    $response = $this->actingAs($admin)
        ->post(route('branch-clients.renew', $branch->id), [
            'pending_payment_id' => $pendingPayment->id,
            'duration_days' => 30,
            'amount' => 1500,
            'payment_method' => 'bkash',
            'transaction_reference' => 'BKASH-USER-TRX-9876',
            'paid_at' => '2026-09-08',
            'notes' => 'Admin verified deposit screenshot and approved',
            'existing_attachment_path' => $pendingPayment->attachment_path,
        ]);

    $response->assertRedirect();

    $branch->refresh();
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-10-10');
    expect($branch->subscription_status)->toBe('active');

    $pendingPayment->refresh();
    expect($pendingPayment->status)->toBe('approved');
    expect($pendingPayment->notes)->toBe('Admin verified deposit screenshot and approved');
});
