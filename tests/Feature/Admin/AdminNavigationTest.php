<?php

use App\Models\User;
use App\Support\AdminNavigation;
use Inertia\Testing\AssertableInertia as Assert;

test('guests receive empty admin navigation', function () {
    $navigation = app(AdminNavigation::class)->build(null);

    expect($navigation)->toBe([]);
});

test('authenticated users see the full navigation tree', function () {
    $user = User::factory()->create();

    $navigation = app(AdminNavigation::class)->build($user);

    expect($navigation)->toHaveCount(9)
        ->and(collect($navigation)->pluck('title'))->toContain(
            'Dashboard',
            'Branch',
            'User',
            'Settings',
            'Accounts',
            'Reports',
        );
});

test('settings section includes all child links', function () {
    $user = User::factory()->create();

    $navigation = app(AdminNavigation::class)->build($user);
    $settings = collect($navigation)->firstWhere('title', 'Settings');

    expect($settings)->not->toBeNull()
        ->and(collect($settings['children'])->pluck('title')->all())->toBe([
            'Category',
            'Tag',
            'Brand',
            'Unit',
            'Size',
            'Tailor Measurement',
            'Color',
            'Warranty',
            'Product',
            'Barcode',
            'Product Section',
            'Slider',
            'Membership',
            'Website Setting',
        ]);
});

test('authenticated admin dashboard shares navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('adminNavigation', 9)
            ->where('adminNavigation.0.title', 'Dashboard'));
});
