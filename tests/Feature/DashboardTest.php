<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can access the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});

test('authenticated users see the central admin navigation on dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('adminNavigation')
            ->where('adminNavigation', fn ($navigation) => collect($navigation)->pluck('title')->contains('Branch')
                && collect($navigation)->pluck('title')->doesntContain('Settings')));
});

test('legacy admin url redirects to dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('dashboard'));
});
