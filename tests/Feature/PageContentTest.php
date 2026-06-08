<?php

use App\Models\Branch;
use App\Models\ConfigDictionary;
use App\Models\User;
use App\Services\EcommerceBranchService;

function pageContentEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function pageContentEcommerceUser(): User
{
    $branch = pageContentEcommerceBranch();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function pageContentBranchUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

test('guests are redirected from page content settings', function () {
    $this->get('/setting/page-content/about-us')->assertRedirect(route('login'));
});

test('non ecommerce branch users are redirected from page content settings', function () {
    $this->actingAs(pageContentBranchUser())
        ->get('/setting/page-content/about-us')
        ->assertRedirect(route('branch-panel.dashboard'));
});

test('ecommerce branch user without permission cannot view page content settings', function () {
    test()->artisan('permissions:sync');

    $this->actingAs(pageContentEcommerceUser())
        ->get('/setting/page-content/about-us')
        ->assertForbidden();
});

test('ecommerce branch user can view and update page content with permission', function () {
    test()->artisan('permissions:sync');

    $user = pageContentEcommerceUser();
    $user->givePermissionTo('setting.page-content.view', 'setting.page-content.update');

    $this->actingAs($user)
        ->get('/setting/page-content/refund-policy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/page-content/edit')
            ->where('page.slug', 'refund-policy')
            ->where('page.title', 'Refund Policy')
        );

    $this->actingAs($user)
        ->put('/setting/page-content/refund-policy', [
            'content' => '<p>Updated refund policy content.</p>',
        ])
        ->assertRedirect(route('setting.page-content.edit', ['page' => 'refund-policy']))
        ->assertSessionHas('success');

    expect(ConfigDictionary::get('refund_policy'))->toBe('<p>Updated refund policy content.</p>');
});

test('invalid page content slug returns not found', function () {
    test()->artisan('permissions:sync');

    $user = pageContentEcommerceUser();
    $user->givePermissionTo('setting.page-content.view');

    $this->actingAs($user)
        ->get('/setting/page-content/invalid-page')
        ->assertNotFound();
});

test('about page uses content page design with dynamic content', function () {
    ConfigDictionary::set('about_us', '<p>Custom about content.</p>');

    $this->get(route('about'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/content-page')
            ->where('title', 'About Us')
            ->where('content', '<p>Custom about content.</p>')
            ->where('showExtras', true)
            ->has('heroImage')
        );
});

test('refund policy page uses content page design with dynamic content', function () {
    ConfigDictionary::set('refund_policy', '<p>Custom refund policy.</p>');

    $this->get(route('refund-policy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/content-page')
            ->where('title', 'Refund Policy')
            ->where('content', '<p>Custom refund policy.</p>')
            ->where('showExtras', false)
            ->has('heroImage')
        );
});

test('cancellation policy page uses content page design', function () {
    $this->get(route('cancellation-policy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/content-page')
            ->where('title', 'Cancellation Policy')
        );
});

test('privacy policy page uses content page design', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/content-page')
            ->where('title', 'Privacy Policy')
        );
});

test('terms policy page uses content page design', function () {
    $this->get(route('terms-policy'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/content-page')
            ->where('title', 'Terms of Service')
        );
});
