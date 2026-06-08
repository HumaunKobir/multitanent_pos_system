<?php

use App\Models\Color;
use App\Models\Size;
use App\Models\User;

function productCreateAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

test('superadmin can view product create page with color and size options', function () {
    $red = Color::query()->create(['name' => 'Red '.fake()->unique()->numerify('####'), 'status' => 1]);
    $blue = Color::query()->create(['name' => 'Blue '.fake()->unique()->numerify('####'), 'status' => 1]);
    Color::query()->create(['name' => 'Inactive '.fake()->unique()->numerify('####'), 'status' => 0]);

    $small = Size::query()->create(['name' => 'S '.fake()->unique()->numerify('####'), 'status' => 1]);
    $large = Size::query()->create(['name' => 'L '.fake()->unique()->numerify('####'), 'status' => 1]);

    $this->actingAs(productCreateAdmin())
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('colorOptions', fn ($options) => collect($options)->contains(['value' => $red->name, 'label' => $red->name])
                && collect($options)->contains(['value' => $blue->name, 'label' => $blue->name])
                && ! collect($options)->contains(fn ($option) => str_starts_with($option['value'], 'Inactive ')))
            ->where('sizeOptions', fn ($options) => collect($options)->contains(['value' => $small->name, 'label' => $small->name])
                && collect($options)->contains(['value' => $large->name, 'label' => $large->name])));
});

test('product create page only includes active colors and sizes', function () {
    $activeColor = Color::query()->create(['name' => 'Active Color '.fake()->unique()->numerify('####'), 'status' => 1]);
    $hiddenColor = Color::query()->create(['name' => 'Hidden Color '.fake()->unique()->numerify('####'), 'status' => 0]);
    $activeSize = Size::query()->create(['name' => 'Active Size '.fake()->unique()->numerify('####'), 'status' => 1]);
    $hiddenSize = Size::query()->create(['name' => 'Hidden Size '.fake()->unique()->numerify('####'), 'status' => 0]);

    $this->actingAs(productCreateAdmin())
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where('colorOptions', fn ($options) => collect($options)->contains(['value' => $activeColor->name, 'label' => $activeColor->name])
                && ! collect($options)->contains(['value' => $hiddenColor->name, 'label' => $hiddenColor->name]))
            ->where('sizeOptions', fn ($options) => collect($options)->contains(['value' => $activeSize->name, 'label' => $activeSize->name])
                && ! collect($options)->contains(['value' => $hiddenSize->name, 'label' => $hiddenSize->name])));
});
