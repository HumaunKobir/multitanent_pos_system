<?php

use App\Models\Branch;
use App\Models\Subscriber;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function contactListSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function contactListBranchUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

test('guests are redirected from contact list', function () {
    $this->get('/contact-list')->assertRedirect(route('login'));
});

test('branch users are redirected from contact list', function () {
    $this->actingAs(contactListBranchUser())
        ->get('/contact-list')
        ->assertRedirect(route('branch-panel.dashboard'));
});

test('superadmin can view contact list with subscribers', function () {
    $prefix = 'cl-view-'.uniqid();
    $active = Subscriber::factory()->create(['email' => "{$prefix}-active@example.com"]);
    $inactive = Subscriber::factory()->inactive()->create(['email' => "{$prefix}-inactive@example.com"]);

    $this->actingAs(contactListSuperAdmin())
        ->get('/contact-list?search='.$prefix)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/contact-list/index')
            ->where('filters.search', $prefix)
            ->has('subscribers.data', 2)
            ->where('subscribers.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                === collect([$active->id, $inactive->id])->sort()->values()->all())
        );
});

test('superadmin can search contact list by email', function () {
    $match = Subscriber::factory()->create(['email' => 'findme-'.uniqid().'@example.com']);
    Subscriber::factory()->create(['email' => 'other-'.uniqid().'@example.com']);

    $this->actingAs(contactListSuperAdmin())
        ->get('/contact-list?search='.$match->email)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.id', $match->id)
        );
});

test('superadmin can delete a subscriber', function () {
    $subscriber = Subscriber::factory()->create();

    $this->actingAs(contactListSuperAdmin())
        ->delete("/contact-list/{$subscriber->id}")
        ->assertRedirect(route('contact-list.index'))
        ->assertSessionHas('success');

    expect(Subscriber::query()->find($subscriber->id))->toBeNull();
});
