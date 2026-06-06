<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from legacy admin dashboard url', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('authenticated users can visit the admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('adminNavigation')
            ->has('kpis')
            ->has('branchSales')
            ->has('salesTrend')
            ->has('collection'));
});

test('legacy admin url redirects to dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('dashboard'));
});
