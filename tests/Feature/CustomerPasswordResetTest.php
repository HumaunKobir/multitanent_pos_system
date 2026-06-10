<?php

use App\Mail\PasswordResetOtpMail;
use App\Models\Customer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('customer forgot password page loads', function () {
    $this->get(route('customer.password.request'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('frontend/customer/forgot-password'));
});

test('customer can request a password reset otp', function () {
    Mail::fake();

    $customer = Customer::factory()->create([
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->post(route('customer.password.email'), ['email' => $customer->email])
        ->assertRedirect(route('customer.password.verify'))
        ->assertSessionHas('status');

    Mail::assertSent(PasswordResetOtpMail::class, fn (PasswordResetOtpMail $mail) => $mail->hasTo($customer->email));
});

test('customer can reset password with a valid otp', function () {
    Mail::fake();

    $customer = Customer::factory()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('old-password'),
    ]);

    $this->post(route('customer.password.email'), ['email' => $customer->email]);

    $otp = null;

    Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
        $otp = $mail->otp;

        return true;
    });

    $this->withSession(['password_reset.email' => strtolower($customer->email)])
        ->post(route('customer.password.verify.store'), [
            'email' => $customer->email,
            'otp' => $otp,
        ])
        ->assertRedirect(route('customer.password.reset'))
        ->assertSessionHas('status');

    $this->withSession([
        'password_reset.email' => strtolower($customer->email),
        'password_reset.verified_at' => now()->timestamp,
    ])
        ->post(route('customer.password.update'), [
            'email' => $customer->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('customer.login'))
        ->assertSessionHas('status');

    expect(Hash::check('new-password-123', $customer->fresh()->password))->toBeTrue();
});

test('customer password reset fails with invalid otp', function () {
    $customer = Customer::factory()->create([
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->withSession(['password_reset.email' => strtolower($customer->email)])
        ->post(route('customer.password.verify.store'), [
            'email' => $customer->email,
            'otp' => '000000',
        ])
        ->assertSessionHasErrors('otp');
});

test('customer new password page requires verified otp', function () {
    $customer = Customer::factory()->create([
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->withSession(['password_reset.email' => strtolower($customer->email)])
        ->get(route('customer.password.reset'))
        ->assertRedirect(route('customer.password.verify'));
});
