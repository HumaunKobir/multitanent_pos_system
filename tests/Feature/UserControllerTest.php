<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function userManagementActor(array $permissions = []): User
{
    $user = User::factory()->create(['branch_id' => null]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('creating a branch user seeds default accounts for that branch', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.create']);

    $branch = Branch::factory()->create();

    ChartOfAccount::query()
        ->where('source_type', Branch::class)
        ->where('source_id', $branch->id)
        ->delete();

    $this->actingAs($actor)
        ->post('/user', [
            'branch_id' => $branch->id,
            'name' => 'Branch Manager '.fake()->unique()->word(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('01#########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertRedirect(route('user.index'))
        ->assertSessionHas('success');

    foreach (SystemAccountKey::defaultSeededCases() as $key) {
        expect(ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->where('source_type', Branch::class)
            ->where('source_id', $branch->id)
            ->exists())
            ->toBeTrue("Expected {$key->value} for branch {$branch->id}");
    }
});

test('only one user can be assigned to a branch', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.create']);

    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($actor)
        ->post('/user', [
            'branch_id' => $branch->id,
            'name' => 'Duplicate Branch User',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('01#########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertSessionHasErrors('branch_id');
});

test('user index exposes branches that already have a user assigned', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    $branch = Branch::factory()->create(['name' => 'Assigned Branch '.fake()->unique()->word()]);
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->has('assignedBranchIds')
            ->where('assignedBranchIds', fn ($ids) => collect($ids)->contains($branch->id)));
});
