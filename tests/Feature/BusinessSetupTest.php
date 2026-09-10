<?php

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\BusinessSettings;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    test()->artisan('permissions:sync');
});

function businessSetupSuperAdmin(): User
{
    $user = User::factory()->create([
        'branch_id' => null,
        'email' => 'bizadmin_'.uniqid().'@test.com',
    ]);
    $user->givePermissionTo(['business-setup.view', 'business-setup.update']);

    return $user;
}

test('superadmin can access business setup page', function () {
    $admin = businessSetupSuperAdmin();
    $branch = Branch::factory()->create(['name' => 'Client Test Branch']);

    $response = $this->actingAs($admin)
        ->get(route('setting.business-setup.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/business-setup/index')
            ->has('settings')
            ->has('settings.system_name')
            ->has('settings.multi_tenant_enabled')
            ->has('tenancyEnabled')
            ->has('billingCycles')
            ->has('overdueActions')
        );
});

test('superadmin can update business settings and policies', function () {
    $admin = businessSetupSuperAdmin();

    $response = $this->actingAs($admin)
        ->put(route('setting.business-setup.update'), [
            'subscription_billing_cycle' => 'quarterly',
            'subscription_billing_cycle_days' => 90,
            'subscription_payment_window_days' => 10,
            'subscription_warning_days' => 7,
            'subscription_grace_period_days' => 14,
            'subscription_overdue_action' => 'restrict_sales',
            'subscription_default_fee' => 2500,
            'subscription_payment_instructions' => 'Pay via bKash: 01700000000',
            'subscription_warning_message' => 'Custom warning {days_left} days left.',
            'subscription_policy_terms' => 'Terms and conditions apply.',
            'superadmin_contact_name' => 'Main Support',
            'superadmin_contact_phone' => '+8801800000000',
            'superadmin_contact_email' => 'support@coolness.com',
            'system_name' => 'Acme SaaS Platform',
            'multi_tenant_enabled' => true,
        ]);

    $response->assertRedirect(route('setting.business-setup.edit'))
        ->assertSessionHas('success');

    expect(BusinessSettings::get('subscription_billing_cycle'))->toBe('quarterly');
    expect(BusinessSettings::getInt('subscription_warning_days'))->toBe(7);
    expect(BusinessSettings::getInt('subscription_grace_period_days'))->toBe(14);
    expect(BusinessSettings::getFloat('subscription_default_fee'))->toBe(2500.0);
    expect(BusinessSettings::systemName())->toBe('Acme SaaS Platform');
    expect(BusinessSettings::getBool('multi_tenant_enabled'))->toBeTrue();
});

test('superadmin can update branch subscription config', function () {
    $admin = businessSetupSuperAdmin();
    $branch = Branch::factory()->create();
    BusinessSetting::set('subscription_billing_cycle_days', '30');

    $response = $this->actingAs($admin)
        ->put(route('setting.business-setup.branch.update', $branch->id), [
            'subscription_plan' => 'quarterly',
            'subscription_status' => 'active',
            'subscription_fee' => 3000,
            'subscription_starts_at' => '2026-09-01',
            'custom_grace_period_days' => 10,
            'custom_warning_days' => 6,
            'custom_overdue_action' => 'read_only',
            'subscription_notes' => 'VIP branch custom discount applied',
        ]);

    $response->assertRedirect(route('setting.business-setup.edit'))
        ->assertSessionHas('success');

    $branch->refresh();
    expect($branch->subscription_plan)->toBe('quarterly');
    expect((float) $branch->subscription_fee)->toBe(3000.0);
    expect($branch->custom_grace_period_days)->toBe(10);
    expect($branch->custom_overdue_action)->toBe('read_only');
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-11-30');
});

test('superadmin can set custom days cycle with custom duration', function () {
    $admin = businessSetupSuperAdmin();
    $branch = Branch::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('setting.business-setup.branch.update', $branch->id), [
            'subscription_plan' => 'custom_days',
            'subscription_status' => 'active',
            'subscription_fee' => 3500,
            'subscription_starts_at' => '2026-09-01',
            'custom_cycle_days' => 45,
        ]);

    $response->assertRedirect(route('setting.business-setup.edit'))
        ->assertSessionHas('success');

    $branch->refresh();
    expect($branch->subscription_plan)->toBe('custom_days');
    expect($branch->subscription_expires_at?->format('Y-m-d'))->toBe('2026-10-16');
});

test('superadmin can renew branch subscription and record payment', function () {
    $admin = businessSetupSuperAdmin();
    $branch = Branch::factory()->create([
        'subscription_expires_at' => '2026-09-01',
        'subscription_status' => 'active',
    ]);

    $response = $this->actingAs($admin)
        ->post(route('setting.business-setup.branch.renew', $branch->id), [
            'duration_days' => 60,
            'amount' => 3000,
            'payment_method' => 'bkash',
            'transaction_reference' => 'TRX12345678',
            'paid_at' => '2026-09-08',
            'notes' => 'Renewed for 2 months',
        ]);

    $response->assertRedirect(route('setting.business-setup.edit'))
        ->assertSessionHas('success');

    $branch->refresh();
    expect($branch->subscription_status)->toBe('active');
    expect($branch->subscription_last_paid_at?->format('Y-m-d'))->toBe('2026-09-08');

    expect($branch->subscriptionPayments()->count())->toBe(1);
    $payment = $branch->subscriptionPayments()->first();
    expect((float) $payment->amount)->toBe(3000.0);
    expect($payment->payment_method)->toBe('bkash');
    expect($payment->transaction_reference)->toBe('TRX12345678');
});

test('superadmin can fetch branch payment history json', function () {
    $admin = businessSetupSuperAdmin();
    $branch = Branch::factory()->create();

    $branch->subscriptionPayments()->create([
        'amount' => 1500,
        'payment_method' => 'cash',
        'paid_at' => '2026-09-08',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('setting.business-setup.branch.payments', $branch->id));

    $response->assertOk()
        ->assertJsonStructure([
            'branch' => ['id', 'name'],
            'payments' => [
                '*' => ['id', 'amount', 'payment_method', 'paid_at'],
            ],
        ]);
});

test('unauthorized user cannot access business setup', function () {
    $branchUser = User::factory()->create([
        'branch_id' => Branch::factory()->create()->id,
    ]);

    $response = $this->actingAs($branchUser)
        ->get(route('setting.business-setup.edit'));

    $response->assertForbidden();
});
