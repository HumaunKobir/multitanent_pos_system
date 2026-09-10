<?php

use App\Enums\SystemAccountKey;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Support\AdminNavigation;
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

function clearManagedUsersForBranch(int $branchId): void
{
    User::query()
        ->where('branch_id', $branchId)
        ->managedInUserList()
        ->delete();
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

    foreach (SystemAccountKey::defaultSeededCases($branch->id) as $key) {
        expect(ChartOfAccount::query()
            ->where('account_number', $key->accountNumber())
            ->where('source_type', Branch::class)
            ->where('source_id', $branch->id)
            ->exists())
            ->toBeTrue("Expected {$key->value} for branch {$branch->id}");
    }
});

test('a branch can have multiple users', function () {
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

test('user index does not include the main super admin account', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    $superAdmin = User::query()->find(User::SUPER_ADMIN_ID)
        ?? User::factory()->create(['id' => User::SUPER_ADMIN_ID, 'branch_id' => null]);

    $branch = Branch::factory()->create();
    $branchUser = User::factory()->create(['branch_id' => $branch->id]);
    $otherSuperStyleUser = User::factory()->create(['branch_id' => null]);

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($branchUser->id)
                && ! collect($users)->pluck('id')->contains($superAdmin->id)
                && ! collect($users)->pluck('id')->contains($otherSuperStyleUser->id)));
});

test('user index hides system ecommerce admin and exposes assignable branches', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    $unassignedBranch = Branch::factory()->create();
    $assignedBranch = Branch::factory()->create();
    $assignedUser = User::factory()->create(['branch_id' => $assignedBranch->id]);

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );
    clearManagedUsersForBranch($ecommerceBranch->id);
    $systemEcommerceAdmin = User::query()->firstOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'branch_id' => $ecommerceBranch->id,
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
        ])->makeVisible('password')->toArray(),
    );

    $mainBranch = Branch::query()->find(Branch::MAIN_BRANCH_ID);
    $mainBranchUser = null;

    if ($mainBranch !== null && $mainBranch->name !== Branch::ECOMMERCE_BRANCH_NAME) {
        $mainBranchUser = User::factory()->create(['branch_id' => $mainBranch->id]);
    }

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($assignedUser->id)
                && ! collect($users)->pluck('id')->contains($systemEcommerceAdmin->id)
                && ($mainBranchUser === null || collect($users)->pluck('id')->contains($mainBranchUser->id)))
            ->where('branches', fn ($branches) => collect($branches)->has($unassignedBranch->id)
                && collect($branches)->has($assignedBranch->id)
                && collect($branches)->has($ecommerceBranch->id)
                && ($mainBranch === null || $mainBranch->name === Branch::ECOMMERCE_BRANCH_NAME || collect($branches)->has($mainBranch->id))));
});

test('ecommerce branch is available in user form when only the system admin exists', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.create']);

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    clearManagedUsersForBranch($ecommerceBranch->id);

    User::query()->firstOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'branch_id' => $ecommerceBranch->id,
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
        ])->makeVisible('password')->toArray(),
    );

    $this->actingAs($actor)
        ->post('/user', [
            'branch_id' => $ecommerceBranch->id,
            'name' => 'Ecommerce Staff',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('01#########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertRedirect(route('user.index'))
        ->assertSessionHas('success');
});

test('ecommerce branch can receive multiple managed users from user form', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.create']);

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    User::query()->firstOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'branch_id' => $ecommerceBranch->id,
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
        ])->makeVisible('password')->toArray(),
    );

    User::factory()->create(['branch_id' => $ecommerceBranch->id]);

    $this->actingAs($actor)
        ->post('/user', [
            'branch_id' => $ecommerceBranch->id,
            'name' => 'Second Ecommerce Staff',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('01#########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertRedirect(route('user.index'))
        ->assertSessionHas('success');
});

test('system ecommerce admin cannot be updated or deleted from user list', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.update', 'user.delete']);

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );
    $systemEcommerceAdmin = User::query()->firstOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'branch_id' => $ecommerceBranch->id,
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
        ])->makeVisible('password')->toArray(),
    );

    $this->actingAs($actor)
        ->patch("/user/{$systemEcommerceAdmin->id}", [
            'branch_id' => $ecommerceBranch->id,
            'name' => 'Updated Name',
            'email' => $systemEcommerceAdmin->email,
            'phone' => $systemEcommerceAdmin->phone,
            'status' => 1,
        ])
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete("/user/{$systemEcommerceAdmin->id}")
        ->assertForbidden();
});

test('user index paginates results', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    foreach (range(1, 21) as $index) {
        $branch = Branch::factory()->create();
        User::factory()->create(['branch_id' => $branch->id]);
    }

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->has('users.data', 20)
            ->where('users.total', fn ($total) => $total >= 21)
            ->where('users.per_page', 20));

    $this->actingAs($actor)
        ->get('/user?page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->has('users.data')
            ->where('users.current_page', 2));
});

test('main branch is available in user form and can receive managed users', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view', 'user.create']);

    $mainBranch = Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->where('branches', fn ($branches) => collect($branches)->has($mainBranch->id)));

    $this->actingAs($actor)
        ->post('/user', [
            'branch_id' => $mainBranch->id,
            'name' => 'Main Branch Staff',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('01#########'),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 1,
        ])
        ->assertRedirect(route('user.index'))
        ->assertSessionHas('success');
});

test('main branch user logs into admin panel with role permissions', function () {
    $this->artisan('permissions:sync');

    $mainBranchId = ensureMainBranch();

    $user = User::factory()->create(['branch_id' => $mainBranchId]);
    Permission::findOrCreate('dashboard.view', 'web');
    Permission::findOrCreate('product.view', 'web');
    $user->givePermissionTo('dashboard.view');
    $user->givePermissionTo('product.view');

    expect($user->usesAdminPanel())->toBeTrue();
    expect($user->usesBranchPanel())->toBeFalse();

    $dashboard = collect(app(AdminNavigation::class)->build($user))->firstWhere('title', 'Dashboard');
    expect($dashboard['href'])->toBe(route('dashboard'));

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->get('/product')
        ->assertOk();
});

test('user index filters users by search, branch, and status', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    $suffix = fake()->unique()->lexify('??????');
    $branchA = Branch::factory()->create(['name' => 'Branch Alpha '.$suffix]);
    $branchB = Branch::factory()->create(['name' => 'Branch Beta '.$suffix]);

    $userA = User::factory()->create([
        'name' => 'John Filter Doe '.$suffix,
        'email' => "john.filter.{$suffix}@example.com",
        'branch_id' => $branchA->id,
        'status' => 1,
    ]);

    $userB = User::factory()->create([
        'name' => 'Jane Filter Smith '.$suffix,
        'email' => "jane.filter.{$suffix}@example.com",
        'branch_id' => $branchB->id,
        'status' => 0,
    ]);

    // Test Search
    $res1 = $this->actingAs($actor)->get('/user?search=John+Filter+Doe+'.$suffix);
    $res1->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/user/index')
        ->where('users.data', function ($users) use ($userA, $userB) {
            $ids = collect($users)->pluck('id');
            expect($ids)->toContain($userA->id);
            expect($ids)->not->toContain($userB->id);
            return true;
        }));

    // Test Branch Filter
    $res2 = $this->actingAs($actor)->get('/user?search='.$suffix.'&branch_id='.$branchB->id);
    $res2->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/user/index')
        ->where('users.data', function ($users) use ($userA, $userB) {
            $ids = collect($users)->pluck('id');
            expect($ids)->toContain($userB->id);
            expect($ids)->not->toContain($userA->id);
            return true;
        }));

    // Test Status Filter
    $res3 = $this->actingAs($actor)->get('/user?search='.$suffix.'&status=0');
    $res3->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/user/index')
        ->where('users.data', function ($users) use ($userA, $userB) {
            $ids = collect($users)->pluck('id');
            expect($ids)->toContain($userB->id);
            expect($ids)->not->toContain($userA->id);
            return true;
        }));
});

test('branch id 1 users use admin panel even when another branch is named main branch', function () {
    $this->artisan('permissions:sync');

    $headOffice = Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'POS SYSTEM'])->toArray(),
    );

    Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    );

    expect(Branch::resolveMainBranchId())->toBe(Branch::MAIN_BRANCH_ID);
    expect(Branch::isMainBranch($headOffice->id))->toBeTrue();

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('product.view', 'web');
    $user->givePermissionTo('product.view');

    expect($user->usesAdminPanel())->toBeTrue();
    expect($user->usesBranchPanel())->toBeFalse();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    $this->actingAs($user)
        ->get('/branch-panel')
        ->assertRedirect(route('dashboard'));
});

test('user index exposes all assignable active branches', function () {
    $this->artisan('permissions:sync');

    $actor = userManagementActor(['user.view']);

    $availableBranch = Branch::factory()->create(['name' => 'Available Branch '.fake()->unique()->word()]);
    $assignedBranch = Branch::factory()->create(['name' => 'Assigned Branch '.fake()->unique()->word()]);
    User::factory()->create(['branch_id' => $assignedBranch->id]);

    $this->actingAs($actor)
        ->get('/user')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/user/index')
            ->has('branches')
            ->where('branches', fn ($branches) => collect($branches)->has($availableBranch->id)
                && collect($branches)->has($assignedBranch->id)));
});
