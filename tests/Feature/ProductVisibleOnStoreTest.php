<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Spatie\Permission\Models\Role;

function visibleOnStoreEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    $adminAttributes = User::factory()->make([
        'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
        'branch_id' => $branch->id,
    ])->getAttributes();

    User::query()->updateOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        [
            ...$adminAttributes,
            'password' => $adminAttributes['password'] ?? bcrypt('password'),
        ],
    );

    return $branch;
}

function visibleOnStoreEcommerceUser(): User
{
    return User::factory()->create(['branch_id' => visibleOnStoreEcommerceBranch()->id]);
}

function visibleOnStorePayload(Branch $branch, array $overrides = []): array
{
    return array_merge([
        'category_id' => (string) Category::factory()->create(['status' => 1, 'branch_id' => $branch->id])->id,
        'brand_id' => (string) Brand::factory()->create(['status' => 1, 'branch_id' => $branch->id])->id,
        'unit_id' => (string) Unit::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Unit '.fake()->unique()->numerify('####'),
            'status' => 1,
        ])->id,
        'name' => 'Store Product '.fake()->unique()->numerify('######'),
        'purchase_price' => '100',
        'sale_price' => '150',
        'visible' => 'yes',
        'status' => '1',
    ], $overrides);
}

test('permissions sync creates product visible on store permission', function () {
    $this->artisan('permissions:sync')->assertExitCode(0);

    expect(config('permissions.modules')['product.visible-on-store']['permissions'])
        ->toHaveKey('product.visible-on-store');
});

test('ecommerce branch user without permission cannot mark product visible on store', function () {
    $this->artisan('permissions:sync');

    $branch = visibleOnStoreEcommerceBranch();
    $user = visibleOnStoreEcommerceUser();
    $role = Role::create(['name' => 'Product Staff '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(['product.create', 'product.update']);
    $user->assignRole($role);

    $payload = visibleOnStorePayload($branch, ['branch_id' => (string) $branch->id]);

    $this->actingAs($user)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->visible)->toBe('no');
});

test('ecommerce branch user with permission can mark product visible on store', function () {
    $this->artisan('permissions:sync');

    $branch = visibleOnStoreEcommerceBranch();
    $user = visibleOnStoreEcommerceUser();
    $role = Role::create(['name' => 'Store Manager '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(['product.create', 'product.visible-on-store']);
    $user->assignRole($role);

    $payload = visibleOnStorePayload($branch, ['branch_id' => (string) $branch->id]);

    $this->actingAs($user)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $product = Product::query()->where('name', $payload['name'])->first();

    expect($product)->not->toBeNull()
        ->and($product->visible)->toBe('yes');
});

test('ecommerce branch user without permission cannot update product to visible on store', function () {
    $this->artisan('permissions:sync');

    $branch = visibleOnStoreEcommerceBranch();
    $user = visibleOnStoreEcommerceUser();
    $role = Role::create(['name' => 'Product Editor '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(['product.update']);
    $user->assignRole($role);

    $product = Product::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => Category::factory()->create(['status' => 1, 'branch_id' => $branch->id])->id,
        'brand_id' => Brand::factory()->create(['status' => 1, 'branch_id' => $branch->id])->id,
        'unit_id' => Unit::query()->create([
            'branch_id' => $branch->id,
            'name' => 'Unit '.fake()->unique()->numerify('####'),
            'status' => 1,
        ])->id,
        'visible' => 'no',
    ]);

    $this->actingAs($user)
        ->patch(route('product.update', $product), visibleOnStorePayload($branch, [
            'name' => $product->name,
            'code' => $product->code,
            'visible' => 'yes',
        ]))
        ->assertRedirect(route('product.index'));

    expect($product->fresh()->visible)->toBe('no');
});

test('visible products appear on storefront and hidden products do not', function () {
    $branch = visibleOnStoreEcommerceBranch();

    $visibleProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'status' => 1,
        'visible' => 'yes',
        'name' => 'Visible Storefront Product '.fake()->unique()->numerify('####'),
    ]);

    $hiddenProduct = Product::factory()->create([
        'branch_id' => $branch->id,
        'status' => 1,
        'visible' => 'no',
        'name' => 'Hidden Storefront Product '.fake()->unique()->numerify('####'),
    ]);

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('frontend/all-products')
            ->where('products.data', fn ($products) => collect($products)->contains('slug', $visibleProduct->slug))
            ->where('products.data', fn ($products) => ! collect($products)->contains('slug', $hiddenProduct->slug)));
});

test('all-branches create with visible yes publishes ecommerce copy on storefront', function () {
    $this->artisan('permissions:sync');

    $ecommerceBranch = visibleOnStoreEcommerceBranch();
    $mainBranch = Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    $admin = User::factory()->create(['branch_id' => null]);
    $role = Role::create(['name' => 'Catalog Admin '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(['product.create', 'product.visible-on-store']);
    $admin->assignRole($role);

    $payload = visibleOnStorePayload($mainBranch, [
        'branch_id' => '',
        'visible' => 'yes',
        'name' => 'All Branch Visible '.fake()->unique()->numerify('######'),
    ]);

    $this->actingAs($admin)
        ->post(route('product.store'), $payload)
        ->assertRedirect(route('product.index'));

    $ecommerceProduct = Product::query()
        ->where('name', $payload['name'])
        ->where('branch_id', $ecommerceBranch->id)
        ->first();

    expect($ecommerceProduct)->not->toBeNull()
        ->and($ecommerceProduct->visible)->toBe('yes');

    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products.data', fn ($products) => collect($products)->contains('slug', $ecommerceProduct->slug)));
});
