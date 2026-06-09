<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function branchProfileUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

test('guests are redirected from branch profile', function () {
    $this->get('/setting/branch-profile')->assertRedirect(route('login'));
});

test('super admin without branch cannot access branch profile', function () {
    $user = User::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get('/setting/branch-profile')
        ->assertForbidden();
});

test('branch user can view branch profile without explicit permission', function () {
    $user = branchProfileUser();

    $this->actingAs($user)
        ->get('/setting/branch-profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/branch-profile/index')
            ->where('branch.id', $user->branch_id)
            ->where('branch.name', $user->branch->name)
            ->where('account.email', $user->email));
});

test('branch user can update branch profile and account without explicit permission', function () {
    $user = branchProfileUser();
    $branch = Branch::query()->findOrFail($user->branch_id);

    $this->actingAs($user)
        ->put('/setting/branch-profile', [
            'name' => 'Updated Branch Name',
            'phone' => '01711111111',
            'address' => '123 Branch Road',
            'email' => 'branch.updated@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('setting.branch-profile.edit'))
        ->assertSessionHas('success');

    $branch->refresh();
    $user->refresh();

    expect($branch->name)->toBe('Updated Branch Name')
        ->and($branch->phone)->toBe('01711111111')
        ->and($branch->address)->toBe('123 Branch Road')
        ->and($user->email)->toBe('branch.updated@example.com')
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('branch user only updates their own branch profile', function () {
    $user = branchProfileUser();
    $otherBranch = Branch::factory()->create(['name' => 'Other Branch']);

    $this->actingAs($user)
        ->put('/setting/branch-profile', [
            'name' => 'Updated Branch Name',
            'phone' => null,
            'address' => null,
            'email' => $user->email,
        ])
        ->assertRedirect(route('setting.branch-profile.edit'));

    expect(Branch::query()->findOrFail($otherBranch->id)->name)->toBe('Other Branch');
    expect(Branch::query()->findOrFail($user->branch_id)->name)->toBe('Updated Branch Name');
});
