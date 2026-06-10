<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function adminProfileSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('guests are redirected from admin profile', function () {
    $this->get('/setting/admin-profile')->assertRedirect(route('login'));
});

test('branch user cannot access admin profile', function () {
    $user = User::factory()->create(['branch_id' => 2]);

    $this->actingAs($user)
        ->get('/setting/admin-profile')
        ->assertForbidden();
});

test('super admin can view admin profile', function () {
    $user = adminProfileSuperAdmin();

    $this->actingAs($user)
        ->get('/setting/admin-profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/admin-profile/index')
            ->where('account.name', $user->name)
            ->where('account.email', $user->email)
            ->where('account.phone', $user->phone));
});

test('super admin can update admin profile and password', function () {
    $user = adminProfileSuperAdmin();
    $newEmail = fake()->unique()->safeEmail();
    $newPhone = fake()->unique()->numerify('01#########');

    $this->actingAs($user)
        ->put('/setting/admin-profile', [
            'name' => 'Updated Admin',
            'email' => $newEmail,
            'phone' => $newPhone,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('setting.admin-profile.edit'))
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->name)->toBe('Updated Admin')
        ->and($user->email)->toBe($newEmail)
        ->and($user->phone)->toBe($newPhone)
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('ecommerce branch name constant is Ecommerce Branch', function () {
    expect(Branch::ECOMMERCE_BRANCH_NAME)->toBe('Ecommerce Branch')
        ->and(EcommerceBranchService::BRANCH_NAME)->toBe('Ecommerce Branch');
});
