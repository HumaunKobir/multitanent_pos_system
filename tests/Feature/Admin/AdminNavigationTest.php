<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Support\AdminNavigation;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('guests receive empty admin navigation', function () {
    $navigation = app(AdminNavigation::class)->build(null);

    expect($navigation)->toBe([]);
});

test('authenticated users see the full navigation tree', function () {
    $user = User::factory()->create();

    $navigation = app(AdminNavigation::class)->build($user);

    expect($navigation)->toHaveCount(10)
        ->and(collect($navigation)->pluck('title'))->toContain(
            'Dashboard',
            'Branch',
            'User',
            'Settings',
            'Accounts',
            'Reports',
        );
});

test('settings section includes catalog child links only', function () {
    $user = User::factory()->create();

    $navigation = app(AdminNavigation::class)->build($user);
    $settings = collect($navigation)->firstWhere('title', 'Settings');

    expect($settings)->not->toBeNull()
        ->and(collect($settings['children'])->pluck('title')->all())->toBe([
            'Category',
            'Tag',
            'Brand',
            'Unit',
            'Color',
            'Size',
            'Warranty',
            'Product',
            'Barcode',
        ]);
});

test('website section groups all ecommerce frontend links', function () {
    $this->artisan('permissions:sync');

    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $role = Role::create(['name' => 'Website Manager '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo([
        'online-order.view',
        'online-customer.view',
        'setting.slider.view',
        'setting.productsection.view',
        'setting.website.view',
        'setting.page-content.view',
        'setting.faq.view',
    ]);
    $user->assignRole($role);

    $navigation = app(AdminNavigation::class)->build($user);
    $website = collect($navigation)->firstWhere('title', 'Website Manage');

    expect($website)->not->toBeNull()
        ->and(collect($website['children'])->pluck('title')->all())->toContain(
            'Online Orders',
            'Online Customers',
            'Contact Messages',
            'Subscribers',
            'Slider',
            'Product Section',
            'Website Setting',
            'About Us',
            'FAQ',
        );
});

test('branch profile appears last for branch users', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    $titles = collect(app(AdminNavigation::class)->build($user))->pluck('title')->toArray();

    expect($titles)->toContain('Branch Profile')
        ->and(end($titles))->toBe('Branch Profile');
});

test('authenticated admin dashboard shares navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('adminNavigation', 10)
            ->where('adminNavigation.0.title', 'Dashboard'));
});
