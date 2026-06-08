<?php

use App\Models\Branch;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Inertia\Testing\AssertableInertia as Assert;

function subscriberListSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function subscriberListBranchUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function subscriberListEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function subscriberListEcommerceUser(): User
{
    $branch = subscriberListEcommerceBranch();

    return User::factory()->create(['branch_id' => $branch->id]);
}

test('guests are redirected from subscriber list', function () {
    $this->get('/subscriber-list')->assertRedirect(route('login'));
});

test('non ecommerce branch users are redirected from subscriber list', function () {
    $this->actingAs(subscriberListBranchUser())
        ->get('/subscriber-list')
        ->assertRedirect(route('branch-panel.dashboard'));
});

test('ecommerce branch user can view subscribers', function () {
    $prefix = 'sub-ec-'.uniqid();

    $visible = Subscriber::factory()->create([
        'email' => "{$prefix}@example.com",
    ]);
    Subscriber::factory()->create([
        'email' => 'other-'.uniqid().'@example.com',
    ]);

    $this->actingAs(subscriberListEcommerceUser())
        ->get('/subscriber-list?search='.$prefix)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/subscriber-list/index')
            ->has('subscribers.data', 1)
            ->where('subscribers.data.0.id', $visible->id)
        );
});

test('superadmin is redirected from subscriber list', function () {
    $this->actingAs(subscriberListSuperAdmin())
        ->get('/subscriber-list')
        ->assertRedirect(route('dashboard'));
});

test('ecommerce branch user can delete a subscriber', function () {
    $subscriber = Subscriber::factory()->create();

    $this->actingAs(subscriberListEcommerceUser())
        ->delete("/subscriber-list/{$subscriber->id}")
        ->assertRedirect(route('subscriber-list.index'))
        ->assertSessionHas('success');

    expect(Subscriber::query()->find($subscriber->id))->toBeNull();
});
