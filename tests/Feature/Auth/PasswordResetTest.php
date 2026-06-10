<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('verify password reset page can be rendered after requesting otp', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    Mail::fake();

    $this->post(route('password.email'), ['email' => $user->email]);

    $this->withSession(['password_reset.email' => strtolower($user->email)])
        ->get(route('password.verify'))
        ->assertOk();
});

test('password reset cannot proceed without requesting otp first', function () {
    $this->get(route('password.verify'))
        ->assertRedirect(route('password.request'));
});

test('new password page can be rendered after otp verification', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create([
        'branch_id' => $branch->id,
        'email' => fake()->unique()->safeEmail(),
    ]);

    $this->withSession([
        'password_reset.email' => strtolower($user->email),
        'password_reset.verified_at' => now()->timestamp,
    ])
        ->get(route('password.reset'))
        ->assertOk();
});

test('password cannot be reset without requesting otp first', function () {
    $this->get(route('password.reset'))
        ->assertRedirect(route('password.request'));
});
