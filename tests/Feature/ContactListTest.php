<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function contactListSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function contactListBranchUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function contactListEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function contactListEcommerceUser(array $permissions = ['contact-list.view', 'contact-list.delete']): User
{
    $branch = contactListEcommerceBranch();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('ecommerce branch resolves by name when present', function () {
    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    expect(app(EcommerceBranchService::class)->resolveId())->toBe($branch->id);
    expect(EcommerceBranchService::isEcommerceBranchStatic($branch->id))->toBeTrue();
});

test('guests are redirected from contact list', function () {
    $this->get('/contact-list')->assertRedirect(route('login'));
});

test('non ecommerce branch users are redirected from contact list', function () {
    $this->actingAs(contactListBranchUser())
        ->get('/contact-list')
        ->assertRedirect(route('branch-panel.dashboard'));
});

test('ecommerce branch user can view contact messages for their branch', function () {
    $this->artisan('permissions:sync');

    $branch = contactListEcommerceBranch();
    $otherBranch = Branch::factory()->create();
    $prefix = 'cl-ec-'.uniqid();

    $visible = Contact::factory()->create([
        'branch_id' => $branch->id,
        'name' => "{$prefix} Visible",
        'message' => 'Visible ecommerce message',
    ]);
    Contact::factory()->create([
        'branch_id' => $otherBranch->id,
        'name' => "{$prefix} Hidden",
        'message' => 'Hidden ecommerce message',
    ]);

    $this->actingAs(contactListEcommerceUser())
        ->get('/contact-list?search='.$prefix)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/contact-list/index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $visible->id)
        );
});

test('superadmin is redirected from contact list', function () {
    $this->actingAs(contactListSuperAdmin())
        ->get('/contact-list')
        ->assertRedirect(route('dashboard'));
});

test('ecommerce branch user can view contact list with messages', function () {
    $prefix = 'cl-view-'.uniqid();
    $branch = contactListEcommerceBranch();
    $first = Contact::factory()->create([
        'branch_id' => $branch->id,
        'name' => "{$prefix} Alpha",
        'message' => 'First inquiry message',
    ]);
    $second = Contact::factory()->create([
        'branch_id' => $branch->id,
        'name' => "{$prefix} Beta",
        'message' => 'Second inquiry message',
    ]);

    $this->actingAs(contactListEcommerceUser())
        ->get('/contact-list?search='.$prefix)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/contact-list/index')
            ->where('filters.search', $prefix)
            ->has('contacts.data', 2)
            ->where('contacts.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all()
                === collect([$first->id, $second->id])->sort()->values()->all())
        );
});

test('ecommerce branch user can search contact list by message', function () {
    $branch = contactListEcommerceBranch();
    $match = Contact::factory()->create(['branch_id' => $branch->id, 'message' => 'findme-'.uniqid()]);
    Contact::factory()->create(['branch_id' => $branch->id, 'message' => 'other-'.uniqid()]);

    $this->actingAs(contactListEcommerceUser())
        ->get('/contact-list?search='.$match->message)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $match->id)
        );
});

test('ecommerce branch user can delete a contact message from list', function () {
    $branch = contactListEcommerceBranch();
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs(contactListEcommerceUser())
        ->delete("/contact-list/{$contact->id}")
        ->assertRedirect(route('contact-list.index'))
        ->assertSessionHas('success');

    expect(Contact::query()->find($contact->id))->toBeNull();
});

test('ecommerce branch user can delete their branch contact message', function () {
    $branch = contactListEcommerceBranch();
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs(contactListEcommerceUser())
        ->delete("/contact-list/{$contact->id}")
        ->assertRedirect(route('contact-list.index'))
        ->assertSessionHas('success');

    expect(Contact::query()->find($contact->id))->toBeNull();
});

test('ecommerce branch user cannot delete another branch contact message', function () {
    $contact = Contact::factory()->create(['branch_id' => Branch::factory()->create()->id]);

    $this->actingAs(contactListEcommerceUser())
        ->delete("/contact-list/{$contact->id}")
        ->assertForbidden();
});
