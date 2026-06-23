<?php

use App\Models\Branch;
use App\Models\Color;
use App\Models\Size;
use App\Models\User;

function productCreateMainBranch(): Branch
{
    return Branch::query()->firstOrCreate(
        ['name' => 'Main Branch'],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );
}

function productCreateAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('superadmin can view product create page with color and size options', function () {
    $mainBranch = productCreateMainBranch();
    $red = Color::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Red '.fake()->unique()->numerify('####'), 'status' => 1]);
    $blue = Color::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Blue '.fake()->unique()->numerify('####'), 'status' => 1]);
    Color::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Inactive '.fake()->unique()->numerify('####'), 'status' => 0]);

    $small = Size::query()->create(['branch_id' => $mainBranch->id, 'name' => 'S '.fake()->unique()->numerify('####'), 'status' => 1]);
    $large = Size::query()->create(['branch_id' => $mainBranch->id, 'name' => 'L '.fake()->unique()->numerify('####'), 'status' => 1]);

    $this->actingAs(productCreateAdmin())
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('colorOptions', fn ($options) => collect($options)->contains(['value' => $red->name, 'label' => $red->name, 'id' => (string) $red->id])
                && collect($options)->contains(['value' => $blue->name, 'label' => $blue->name, 'id' => (string) $blue->id])
                && ! collect($options)->contains(fn ($option) => str_starts_with($option['value'], 'Inactive ')))
            ->where('sizeOptions', fn ($options) => collect($options)->contains(['value' => $small->name, 'label' => $small->name, 'id' => (string) $small->id])
                && collect($options)->contains(['value' => $large->name, 'label' => $large->name, 'id' => (string) $large->id])));
});

test('product create page only includes active colors and sizes', function () {
    $mainBranch = productCreateMainBranch();
    $activeColor = Color::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Active Color '.fake()->unique()->numerify('####'), 'status' => 1]);
    $hiddenColor = Color::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Hidden Color '.fake()->unique()->numerify('####'), 'status' => 0]);
    $activeSize = Size::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Active Size '.fake()->unique()->numerify('####'), 'status' => 1]);
    $hiddenSize = Size::query()->create(['branch_id' => $mainBranch->id, 'name' => 'Hidden Size '.fake()->unique()->numerify('####'), 'status' => 0]);

    $this->actingAs(productCreateAdmin())
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('colorOptions', fn ($options) => collect($options)->contains(['value' => $activeColor->name, 'label' => $activeColor->name, 'id' => (string) $activeColor->id])
                && ! collect($options)->contains(['value' => $hiddenColor->name, 'label' => $hiddenColor->name, 'id' => (string) $hiddenColor->id]))
            ->where('sizeOptions', fn ($options) => collect($options)->contains(['value' => $activeSize->name, 'label' => $activeSize->name, 'id' => (string) $activeSize->id])
                && ! collect($options)->contains(['value' => $hiddenSize->name, 'label' => $hiddenSize->name, 'id' => (string) $hiddenSize->id])));
});

test('product create page only includes catalog options for admin panel branch', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranch = productCreateMainBranch();
    $operatingBranch = Branch::factory()->create();
    $admin = productCreateAdmin();
    $admin->givePermissionTo('product.create');

    $mainColor = Color::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Main Form Color '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $otherColor = Color::query()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Other Form Color '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('showBranchField', true)
            ->where('branches', fn ($branches) => collect($branches)->has($mainBranch->id) && collect($branches)->has($operatingBranch->id))
            ->where('colorOptions', fn ($options) => collect($options)->contains(fn ($option) => $option['id'] === (string) $mainColor->id)
                && ! collect($options)->contains(fn ($option) => $option['id'] === (string) $otherColor->id)));
});

test('product create page hides branch field for operating branch users', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    productCreateMainBranch();
    $operatingBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $operatingBranch->id]);
    $user->givePermissionTo('product.create');

    $this->actingAs($user)
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('showBranchField', false)
            ->where('branches', []));
});

test('product create page shows branch field for main branch users', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranch = productCreateMainBranch();
    $operatingBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $mainBranch->id]);
    $user->givePermissionTo('product.create');

    $this->actingAs($user)
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('showBranchField', true)
            ->where('branches', fn ($branches) => collect($branches)->has($mainBranch->id) && collect($branches)->has($operatingBranch->id)));
});
