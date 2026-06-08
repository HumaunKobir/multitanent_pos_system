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

test('multiple users can be assigned to the same branch', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.create']);

    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($actor)
        ->post('/user', [
            'branch_id' => $branch->id,
            'name' => 'Second Branch User',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('01#########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertRedirect(route('user.index'))
        ->assertSessionHas('success');

    expect(User::query()->where('branch_id', $branch->id)->count())->toBe(2);
});

test('user index exposes all active branches for assignment', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    $branch = Branch::factory()->create(['name' => 'Assigned Branch '.fake()->unique()->word()]);
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->has('branches')
            ->where('branches', fn ($branches) => collect($branches)->has($branch->id)));
});
