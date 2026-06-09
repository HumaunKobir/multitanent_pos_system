<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;

function branchDestroyAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('superadmin can delete an unused branch', function () {
    $admin = branchDestroyAdmin();
    $branch = Branch::factory()->create();

    $this->actingAs($admin)
        ->delete(route('branch.destroy', $branch))
        ->assertRedirect(route('branch.index'));

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeFalse();
});

test('main branch cannot be deleted', function () {
    $admin = branchDestroyAdmin();
    $mainBranch = Branch::query()->find(Branch::MAIN_BRANCH_ID);

    expect($mainBranch)->not->toBeNull();

    $this->actingAs($admin)
        ->delete(route('branch.destroy', $mainBranch))
        ->assertRedirect(route('branch.index'));

    expect(Branch::query()->whereKey($mainBranch->id)->exists())->toBeTrue();
});

test('branch with assigned users cannot be deleted', function () {
    $admin = branchDestroyAdmin();
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->delete(route('branch.destroy', $branch))
        ->assertRedirect(route('branch.index'));

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});

test('branch with customers cannot be deleted', function () {
    $admin = branchDestroyAdmin();
    $branch = Branch::factory()->create();
    Customer::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->delete(route('branch.destroy', $branch))
        ->assertRedirect(route('branch.index'));

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});

test('branch with suppliers cannot be deleted', function () {
    $admin = branchDestroyAdmin();
    $branch = Branch::factory()->create();
    Supplier::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->delete(route('branch.destroy', $branch))
        ->assertRedirect(route('branch.index'));

    expect(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});
