<?php

use App\Models\Branch;
use App\Models\Color;
use App\Models\Size;
use App\Models\User;
use App\Support\AdminNavigation;
use Spatie\Permission\Models\Role;

function colorSizeSettingAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function colorSizeSettingStaff(): User
{
    return User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
}

test('superadmin can view color and size settings pages', function () {
    $this->artisan('permissions:sync');

    $admin = colorSizeSettingAdmin();

    $this->actingAs($admin)
        ->get(route('setting.color.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/setting/color/index'));

    $this->actingAs($admin)
        ->get(route('setting.size.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/setting/size/index'));
});

test('superadmin can create update and delete colors and sizes', function () {
    $this->artisan('permissions:sync');

    $admin = colorSizeSettingAdmin();
    $colorName = 'Color '.fake()->unique()->numerify('####');
    $updatedColorName = 'Updated Color '.fake()->unique()->numerify('####');
    $sizeName = 'Size '.fake()->unique()->numerify('####');
    $updatedSizeName = 'Updated Size '.fake()->unique()->numerify('####');

    $this->actingAs($admin)
        ->post(route('setting.color.store'), ['name' => $colorName, 'status' => '1'])
        ->assertRedirect(route('setting.color.index'));

    $color = Color::query()->where('name', $colorName)->first();
    expect($color)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('setting.color.update', $color), ['name' => $updatedColorName, 'status' => '1'])
        ->assertRedirect(route('setting.color.index'));

    expect($color->fresh()->name)->toBe($updatedColorName);

    $this->actingAs($admin)
        ->post(route('setting.size.store'), ['name' => $sizeName, 'status' => '1'])
        ->assertRedirect(route('setting.size.index'));

    $size = Size::query()->where('name', $sizeName)->first();
    expect($size)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('setting.size.update', $size), ['name' => $updatedSizeName, 'status' => '1'])
        ->assertRedirect(route('setting.size.index'));

    expect($size->fresh()->name)->toBe($updatedSizeName);

    $this->actingAs($admin)
        ->delete(route('setting.color.destroy', $color))
        ->assertRedirect(route('setting.color.index'));

    $this->actingAs($admin)
        ->delete(route('setting.size.destroy', $size))
        ->assertRedirect(route('setting.size.index'));

    expect(Color::query()->whereKey($color->id)->exists())->toBeFalse()
        ->and(Size::query()->whereKey($size->id)->exists())->toBeFalse();
});

test('user without color and size permissions cannot access settings pages', function () {
    $this->artisan('permissions:sync');

    $user = colorSizeSettingStaff();
    $role = Role::create(['name' => 'Color Size Restricted '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo('setting.category.view');
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('setting.color.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('setting.size.index'))
        ->assertForbidden();
});

test('settings navigation shows color and size when user has view permission', function () {
    $this->artisan('permissions:sync');

    $user = colorSizeSettingStaff();
    $role = Role::create(['name' => 'Color Size Viewer '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(['setting.color.view', 'setting.size.view']);
    $user->assignRole($role);

    $settings = collect(app(AdminNavigation::class)->build($user))
        ->firstWhere('title', 'Settings');

    $childTitles = collect($settings['children'] ?? [])->pluck('title')->all();

    expect($childTitles)->toContain('Color')
        ->and($childTitles)->toContain('Size');
});

test('permissions sync creates color and size permissions', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    $modules = config('permissions.modules');

    expect($modules['setting.color']['permissions'])->toHaveKeys([
        'setting.color.view',
        'setting.color.create',
        'setting.color.update',
        'setting.color.delete',
    ])->and($modules['setting.size']['permissions'])->toHaveKeys([
        'setting.size.view',
        'setting.size.create',
        'setting.size.update',
        'setting.size.delete',
    ]);
});
