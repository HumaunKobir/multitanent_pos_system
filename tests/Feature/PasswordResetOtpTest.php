<?php

use App\Enums\PasswordResetContext;
use App\Mail\PasswordResetOtpMail;
use App\Models\Branch;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('forgot password page can be rendered', function () {
    $this->get(route('password.request'))->assertOk();
});

test('branch user can request a password reset otp', function () {
    Mail::fake();

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect(route('password.verify'))
        ->assertSessionHas('status');

    Mail::assertSent(PasswordResetOtpMail::class, fn (PasswordResetOtpMail $mail) => $mail->hasTo($user->email));

    expect(PasswordResetOtp::query()->where('context', PasswordResetContext::User)->where('email', strtolower($user->email))->exists())
        ->toBeTrue();
});

test('branch user can reset password with a valid otp', function () {
    Mail::fake();

    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('old-password'),
    ]);

    $this->post(route('password.email'), ['email' => $user->email]);

    $otp = null;

    Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
        $otp = $mail->otp;

        return true;
    });

    $this->withSession(['password_reset.email' => strtolower($user->email)])
        ->post(route('password.verify.store'), [
            'email' => $user->email,
            'otp' => $otp,
        ])
        ->assertRedirect(route('password.reset'))
        ->assertSessionHas('status');

    $this->withSession([
        'password_reset.email' => strtolower($user->email),
        'password_reset.verified_at' => now()->timestamp,
    ])
        ->post(route('password.update'), [
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('password reset fails with invalid otp', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->withSession(['password_reset.email' => strtolower($user->email)])
        ->post(route('password.verify.store'), [
            'email' => $user->email,
            'otp' => '000000',
        ])
        ->assertSessionHasErrors('otp');
});

test('new password page requires verified otp', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->withSession(['password_reset.email' => strtolower($user->email)])
        ->get(route('password.reset'))
        ->assertRedirect(route('password.verify'));
});

test('superadmin user cannot request password reset otp', function () {
    Mail::fake();

    $superAdmin = User::query()->find(User::SUPER_ADMIN_ID);

    if ($superAdmin === null) {
        $superAdmin = User::factory()->create([
            'id' => User::SUPER_ADMIN_ID,
            'branch_id' => null,
            'email' => fake()->unique()->safeEmail(),
        ]);
    }

    $this->post(route('password.email'), ['email' => $superAdmin->email])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('main branch user cannot request password reset otp', function () {
    Mail::fake();

    $mainBranch = Branch::query()->find(Branch::MAIN_BRANCH_ID)
        ?? Branch::factory()->create(['id' => Branch::MAIN_BRANCH_ID]);

    $user = User::factory()->create([
        'branch_id' => $mainBranch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('password reset otp email contains the verification code', function () {
    $mail = new PasswordResetOtpMail(
        recipientName: 'Test User',
        otp: '123456',
        expiryMinutes: 10,
    );

    $html = $mail->render();

    expect($html)->toContain('123456')
        ->and($html)->toContain('Password Reset Code');
});
