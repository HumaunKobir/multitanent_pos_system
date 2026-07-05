<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\PanelGuide;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('panel guide only includes sections the user can access', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $role = Role::create(['name' => 'Guide Test '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(['inventory.purchase.view', 'party.supplier.view']);
    $user->assignRole($role);

    $guide = app(PanelGuide::class)->build($user);
    $sectionTitles = collect($guide['sections'])->pluck('title')->all();
    $itemTitles = collect($guide['sections'])->flatMap(fn (array $section) => collect($section['items'])->pluck('title'))->all();

    expect($guide['panelType'])->toBe('branch');
    expect($sectionTitles)->toContain('Purchases');
    expect($sectionTitles)->toContain('Suppliers');
    expect($sectionTitles)->not->toContain('Sales');
    expect($sectionTitles)->not->toContain('Dashboard');
    expect($itemTitles)->toContain('Purchase');
    expect($itemTitles)->toContain('Supplier');

    $user->delete();
    $branch->delete();
});

test('panel guide uses admin panel wording for main branch users', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('dashboard.view', 'web');
    $user->givePermissionTo('dashboard.view');

    $guide = app(PanelGuide::class)->build($user);
    $dashboard = collect($guide['sections'])
        ->flatMap(fn (array $section) => $section['items'])
        ->firstWhere('title', 'Dashboard');

    expect($guide['panelType'])->toBe('admin');
    expect($dashboard['summary'])->toContain('all branches');

    $user->delete();
});

test('user without dashboard permission lands on welcome page', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $role = Role::create(['name' => 'Purchase Only '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo('inventory.purchase.view');
    $user->assignRole($role);

    expect($user->defaultLandingUrl())->toBe(url('/dashboard'));

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('limitedAccess', true)
            ->where('userName', $user->name)
            ->has('branchName')
            ->has('branchLogoUrl'));

    $user->delete();
});

test('panel guide page renders permission filtered content', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('dashboard.view', 'web');
    $user->givePermissionTo(['dashboard.view', 'inventory.purchase.view']);

    $this->actingAs($user)
        ->get(route('panel-guide'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/panel-guide')
            ->has('panelGuide.sections')
            ->where('panelGuide.panelType', 'admin'));

    $user->delete();
});

test('panel guide button shows on dashboard and landing page but not on guide page', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    Permission::findOrCreate('dashboard.view', 'web');
    $user->givePermissionTo(['dashboard.view', 'inventory.purchase.view']);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showPanelGuideButton', true)
            ->where('hasPanelGuide', true));

    $this->actingAs($user)
        ->get(route('panel-guide'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showPanelGuideButton', false)
            ->has('panelGuide.sections'));

    $role = Role::create(['name' => 'No Dashboard '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo('inventory.purchase.view');
    $limitedUser = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);
    $limitedUser->assignRole($role);

    $this->actingAs($limitedUser)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('showPanelGuideButton', true)
            ->where('hasPanelGuide', true)
            ->where('limitedAccess', true));

    $this->actingAs($limitedUser)
        ->get('/party/supplier')
        ->assertForbidden();

    $user->delete();
    $limitedUser->delete();
});

test('panel guide page is forbidden when user has no module access', function () {
    $this->artisan('permissions:sync');

    $user = User::factory()->create(['branch_id' => Branch::MAIN_BRANCH_ID]);

    $this->actingAs($user)
        ->get(route('panel-guide'))
        ->assertForbidden();

    $user->delete();
});
