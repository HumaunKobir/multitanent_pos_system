<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\AdminNavigation;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// ── Helpers ───────────────────────────────────────────────────────────────────

function superAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function branchStaffUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function testRoleName(string $label): string
{
    return "{$label} ".uniqid();
}

// ── Permissions sync command ──────────────────────────────────────────────────

test('permissions:sync creates all permissions from config', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    $modules = config('permissions.modules', []);
    foreach ($modules as $module) {
        foreach (array_keys($module['permissions']) as $name) {
            expect(Permission::where('name', $name)->exists())->toBeTrue("Permission [{$name}] should exist");
        }
    }
});

test('permissions:sync is idempotent', function () {
    $this->artisan('permissions:sync');
    $countAfterFirst = Permission::count();

    $this->artisan('permissions:sync');
    expect(Permission::count())->toBe($countAfterFirst);
});

test('permissions:sync --cleanup removes orphaned permissions', function () {
    $orphan = 'orphan.manage.'.uniqid();
    Permission::create(['name' => $orphan, 'guard_name' => 'web']);

    $this->artisan('permissions:sync --cleanup')->assertExitCode(0);

    expect(Permission::where('name', $orphan)->exists())->toBeFalse();
});

test('permissions:sync without --cleanup keeps orphaned permissions', function () {
    $orphan = 'orphan.manage.'.uniqid();
    Permission::create(['name' => $orphan, 'guard_name' => 'web']);

    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(Permission::where('name', $orphan)->exists())->toBeTrue();
});

// ── Gate bypass for SuperAdmin ────────────────────────────────────────────────

test('superadmin bypasses all permission checks', function () {
    $this->artisan('permissions:sync');

    $admin = superAdmin();
    expect($admin->can('product.view'))->toBeTrue();
    expect($admin->can('product.create'))->toBeTrue();
    expect($admin->can('role.delete'))->toBeTrue();
    expect($admin->can('any.nonexistent.permission'))->toBeTrue();
});

test('branch user without role is denied all permissions', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    expect($user->can('product.view'))->toBeFalse();
    expect($user->can('inventory.purchase.create'))->toBeFalse();
});

test('branch user with role only gets assigned permissions', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Cashier'), 'guard_name' => 'web']);
    $role->givePermissionTo(['inventory.sell.view', 'inventory.sell.create']);
    $user->assignRole($role);

    expect($user->can('inventory.sell.view'))->toBeTrue();
    expect($user->can('inventory.sell.create'))->toBeTrue();
    expect($user->can('inventory.sell.update'))->toBeFalse();
    expect($user->can('inventory.sell.delete'))->toBeFalse();
    expect($user->can('product.view'))->toBeFalse();
});

// ── Role CRUD ─────────────────────────────────────────────────────────────────

test('superadmin can view roles index', function () {
    $this->actingAs(superAdmin())->get('/role')->assertOk();
});

test('superadmin can view the create role page', function () {
    $this->actingAs(superAdmin())->get('/role/create')->assertOk();
});

test('superadmin can view the edit role page', function () {
    $role = Role::create(['name' => testRoleName('Existing Role'), 'guard_name' => 'web']);
    $this->actingAs(superAdmin())->get("/role/{$role->id}/edit")->assertOk();
});

test('superadmin can create a role and is redirected to role index', function () {
    $this->artisan('permissions:sync');
    $name = testRoleName('Sales Staff');

    $this->actingAs(superAdmin())
        ->post('/role', ['name' => $name])
        ->assertRedirect('/role');

    expect(Role::where('name', $name)->exists())->toBeTrue();
});

test('superadmin can update a role name', function () {
    $role = Role::create(['name' => testRoleName('Old Name'), 'guard_name' => 'web']);
    $newName = testRoleName('New Name');

    $this->actingAs(superAdmin())
        ->patch("/role/{$role->id}", ['name' => $newName])
        ->assertRedirect('/role');

    expect($role->fresh()->name)->toBe($newName);
});

test('superadmin can delete a role with no users', function () {
    $name = testRoleName('Temp Role');
    $role = Role::create(['name' => $name, 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->delete("/role/{$role->id}")
        ->assertRedirect('/role');

    expect(Role::where('name', $name)->exists())->toBeFalse();
});

test('cannot delete role assigned to users', function () {
    $name = testRoleName('In Use');
    $role = Role::create(['name' => $name, 'guard_name' => 'web']);
    branchStaffUser()->assignRole($role);

    $this->actingAs(superAdmin())
        ->delete("/role/{$role->id}")
        ->assertRedirect('/role')
        ->assertSessionHas('error');

    expect(Role::where('name', $name)->exists())->toBeTrue();
});

// ── Permissions assignment (dedicated page) ───────────────────────────────────

test('superadmin can view the permissions page for a role', function () {
    $this->artisan('permissions:sync');
    $role = Role::create(['name' => testRoleName('Viewer'), 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->get("/role/{$role->id}/permissions")
        ->assertOk();
});

test('superadmin can assign permissions to a role', function () {
    $this->artisan('permissions:sync');
    $role = Role::create(['name' => testRoleName('Sales Staff'), 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->put("/role/{$role->id}/permissions", [
            'permissions' => ['inventory.sell.view', 'inventory.sell.create'],
        ])
        ->assertRedirect("/role/{$role->id}/permissions");

    expect($role->fresh()->hasPermissionTo('inventory.sell.view'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('inventory.sell.create'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('inventory.sell.update'))->toBeFalse();
});

test('superadmin can replace permissions on a role', function () {
    $this->artisan('permissions:sync');
    $role = Role::create(['name' => testRoleName('Buyer'), 'guard_name' => 'web']);
    $role->givePermissionTo('inventory.sell.view');

    $this->actingAs(superAdmin())
        ->put("/role/{$role->id}/permissions", [
            'permissions' => ['product.view', 'product.create'],
        ]);

    expect($role->fresh()->hasPermissionTo('product.view'))->toBeTrue();
    expect($role->fresh()->hasPermissionTo('inventory.sell.view'))->toBeFalse();
});

test('superadmin can clear all permissions from a role', function () {
    $this->artisan('permissions:sync');
    $role = Role::create(['name' => testRoleName('Empty'), 'guard_name' => 'web']);
    $role->givePermissionTo('product.view');

    $this->actingAs(superAdmin())
        ->put("/role/{$role->id}/permissions", ['permissions' => []]);

    expect($role->fresh()->permissions)->toBeEmpty();
});

// ── Navigation filtering ──────────────────────────────────────────────────────

test('superadmin sees full navigation', function () {
    $this->artisan('permissions:sync');

    $titles = collect(app(AdminNavigation::class)->build(superAdmin()))->pluck('title')->toArray();
    expect($titles)->toContain('Branch');
    expect($titles)->toContain('User');
    expect($titles)->toContain('Roles');
    expect($titles)->toContain('Dashboard');
});

test('branch user with no role sees no permission-gated menu items', function () {
    $this->artisan('permissions:sync');

    $titles = collect(app(AdminNavigation::class)->build(branchStaffUser()))->pluck('title')->toArray();
    expect($titles)->not->toContain('Branch');
    expect($titles)->not->toContain('User');
    expect($titles)->not->toContain('Roles');
    expect($titles)->toContain('Dashboard');
});

test('branch user sees only nav items their role permits', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Buyer'), 'guard_name' => 'web']);
    $role->givePermissionTo(['inventory.purchase.view', 'inventory.purchase.create']);
    $user->assignRole($role);

    $titles = collect(app(AdminNavigation::class)->build($user))->pluck('title')->toArray();
    expect($titles)->toContain('Dashboard');
    expect($titles)->toContain('Purchases');
    expect($titles)->not->toContain('Branch');
});

// ── Shared Inertia permissions ────────────────────────────────────────────────

test('authenticated user receives permissions in shared inertia props', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Viewer'), 'guard_name' => 'web']);
    $role->givePermissionTo(['party.supplier.view', 'setting.category.create']);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/party/supplier')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', ['party.supplier.view', 'setting.category.create'])
        );
});

test('superadmin receives wildcard permissions in shared inertia props', function () {
    $this->actingAs(superAdmin())
        ->get('/role')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.permissions', ['*']));
});

// ── Controller authorization ──────────────────────────────────────────────────

test('branch user without permission is denied supplier index', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(branchStaffUser())
        ->get('/party/supplier')
        ->assertForbidden();
});

test('branch user with supplier view permission can access supplier index', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Supplier Clerk'), 'guard_name' => 'web']);
    $role->givePermissionTo('party.supplier.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/party/supplier')
        ->assertOk();
});

test('branch user without permission is denied customer index', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(branchStaffUser())
        ->get('/party/customer')
        ->assertForbidden();
});

test('branch user without permission is denied daily summary report', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(branchStaffUser())
        ->get('/report/daily-summary')
        ->assertForbidden();
});

test('branch user with daily summary permission can access daily summary report', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Report Clerk'), 'guard_name' => 'web']);
    $role->givePermissionTo('report.daily-summary.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/report/daily-summary')
        ->assertOk();
});

test('branch user with customer view permission can access customer index', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Cashier'), 'guard_name' => 'web']);
    $role->givePermissionTo('party.customer.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/party/customer')
        ->assertOk();
});

test('branch user without permission is denied category settings', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(branchStaffUser())
        ->get('/setting/category')
        ->assertForbidden();
});

test('branch user with category view permission can access category settings', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Settings'), 'guard_name' => 'web']);
    $role->givePermissionTo('setting.category.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/setting/category')
        ->assertOk();
});

test('branch user without permission is denied accounts index', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(branchStaffUser())
        ->get('/accounts')
        ->assertForbidden();
});

test('branch user with accounts view permission can access accounts index', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Accountant'), 'guard_name' => 'web']);
    $role->givePermissionTo('accounts.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/accounts')
        ->assertOk();
});

test('branch user without permission is denied product exchange index', function () {
    $this->artisan('permissions:sync');

    $this->actingAs(branchStaffUser())
        ->get('/inventory/product-exchange')
        ->assertForbidden();
});

test('branch user with product exchange view permission can access product exchange index', function () {
    $this->artisan('permissions:sync');

    $user = branchStaffUser();
    $role = Role::create(['name' => testRoleName('Exchange'), 'guard_name' => 'web']);
    $role->givePermissionTo('inventory.product-exchange.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/inventory/product-exchange')
        ->assertOk();
});
