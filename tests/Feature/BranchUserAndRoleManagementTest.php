<?php

use App\Models\Branch;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('permissions:sync');
});

test('branch users with user.view permission can see User in navigation', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('user.view');

    $navigation = app(\App\Support\AdminNavigation::class)->build($user);
    $titles = collect($navigation)->pluck('title');

    expect($titles)->toContain('User');
});

test('branch users with role.view permission can see Roles in navigation', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('role.view');

    $navigation = app(\App\Support\AdminNavigation::class)->build($user);
    $titles = collect($navigation)->pluck('title');

    expect($titles)->toContain('Roles');
});

test('branch users without permissions cannot see User or Roles in navigation', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $navigation = app(\App\Support\AdminNavigation::class)->build($user);
    $titles = collect($navigation)->pluck('title');

    expect($titles)->not->toContain('User', 'Roles');
});

test('branch user can only view users belonging to their branch', function () {
    $branch1 = Branch::factory()->create(['name' => 'Branch One']);
    $branch2 = Branch::factory()->create(['name' => 'Branch Two']);

    $branchUser = User::factory()->create(['branch_id' => $branch1->id]);
    $branchUser->givePermissionTo('user.view');

    $userInBranch1 = User::factory()->create(['branch_id' => $branch1->id, 'name' => 'Branch 1 Staff']);
    $userInBranch2 = User::factory()->create(['branch_id' => $branch2->id, 'name' => 'Branch 2 Staff']);

    $this->actingAs($branchUser)
        ->get(route('user.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/user/index')
            ->has('users.data', fn (Assert $users) => $users
                ->each(fn (Assert $item) => $item->where('branch_id', $branch1->id)->etc())
            )
            ->where('branches', [$branch1->id => $branch1->name])
        );
});

test('branch user creates user automatically scoped to their branch', function () {
    $branch1 = Branch::factory()->create();
    $branch2 = Branch::factory()->create();

    $branchUser = User::factory()->create(['branch_id' => $branch1->id]);
    $branchUser->givePermissionTo(['user.view', 'user.create']);

    $email = 'newstaff_'.uniqid().'@test.com';
    $phone = '017'.rand(10000000, 99999999);

    $response = $this->actingAs($branchUser)->post(route('user.store'), [
        'name' => 'New Staff',
        'email' => $email,
        'phone' => $phone,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'status' => '1',
        'branch_id' => $branch2->id,
    ]);

    $response->assertRedirect(route('user.index'));

    $createdUser = User::where('email', $email)->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->branch_id)->toBe($branch1->id);
});

test('branch user cannot edit or delete users from other branches', function () {
    $branch1 = Branch::factory()->create();
    $branch2 = Branch::factory()->create();

    $branchUser = User::factory()->create(['branch_id' => $branch1->id]);
    $branchUser->givePermissionTo(['user.view', 'user.update', 'user.delete']);

    $otherBranchUser = User::factory()->create(['branch_id' => $branch2->id]);

    $this->actingAs($branchUser)
        ->patch(route('user.update', $otherBranchUser), [
            'name' => 'Hacked Name',
            'email' => $otherBranchUser->email,
            'phone' => $otherBranchUser->phone,
            'status' => '1',
        ])
        ->assertForbidden();

    $this->actingAs($branchUser)
        ->delete(route('user.destroy', $otherBranchUser))
        ->assertForbidden();
});

test('branch user only sees their own permissions when creating or editing a role', function () {
    $branch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $branch->id]);
    $branchUser->givePermissionTo([
        'role.view',
        'role.create',
        'role.update',
        'inventory.sell.view',
        'inventory.sell.create',
    ]);

    $this->actingAs($branchUser)
        ->get(route('role.create'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            $page->component('admin/role/create');
            $groups = $page->toArray()['props']['permissionGroups'] ?? [];

            $allOfferedPermissions = collect($groups)
                ->flatMap(fn ($group) => collect($group['modules'])->flatMap(fn ($m) => collect($m['permissions'])->pluck('name')))
                ->all();

            expect($allOfferedPermissions)->toContain('inventory.sell.view', 'inventory.sell.create')
                ->and($allOfferedPermissions)->not->toContain('branch.view', 'business-setup.view', 'setting.slider.view');
        });
});

test('branch user cannot assign permissions they do not possess', function () {
    $branch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $branch->id]);
    $branchUser->givePermissionTo(['role.create', 'inventory.sell.view']);

    $response = $this->actingAs($branchUser)->post(route('role.store'), [
        'name' => 'Escalated Role '.uniqid(),
        'permissions' => ['branch.create'],
    ]);

    $response->assertSessionHasErrors('permissions.0');
});

test('branch user only sees roles created by their branch and not superadmin roles', function () {
    $branch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $branch->id]);
    $branchUser->givePermissionTo(['role.view', 'inventory.sell.view']);

    $superAdminRole = Role::create([
        'name' => 'Super Admin Role '.uniqid(),
        'guard_name' => 'web',
        'branch_id' => null,
    ]);
    $superAdminRole->givePermissionTo('branch.view');

    $branchAllowedRole = Role::create([
        'name' => 'Branch Staff Role '.uniqid(),
        'guard_name' => 'web',
        'branch_id' => $branch->id,
    ]);
    $branchAllowedRole->givePermissionTo('inventory.sell.view');

    $this->actingAs($branchUser)
        ->get(route('role.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($branchAllowedRole, $superAdminRole) {
            $page->component('admin/role/index');
            $roleNames = collect($page->toArray()['props']['roles'])->pluck('name')->all();
            expect($roleNames)->toContain($branchAllowedRole->name)
                ->and($roleNames)->not->toContain($superAdminRole->name);
        });
});

