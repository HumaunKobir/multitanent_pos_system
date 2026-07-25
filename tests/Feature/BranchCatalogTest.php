<?php

use App\Enums\CommonStatus;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Illuminate\Support\Str;

/**
 * @return array{ecommerceBranch: Branch, mainBranch: Branch}
 */
function branchCatalogEcommerceSetup(): array
{
    EcommerceBranchService::resetResolvedId();

    $ecommerceBranch = Branch::query()->firstOrCreate(
        ['name' => Branch::ECOMMERCE_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::ECOMMERCE_BRANCH_NAME])->toArray(),
    );

    User::query()->firstOrCreate(
        ['email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL],
        User::factory()->make([
            'branch_id' => $ecommerceBranch->id,
            'email' => User::ECOMMERCE_BRANCH_ADMIN_EMAIL,
        ])->toArray(),
    );

    EcommerceBranchService::resetResolvedId();

    $mainBranch = Branch::query()->firstOrCreate(
        ['name' => 'Main Branch'],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );

    return compact('ecommerceBranch', 'mainBranch');
}

function ensureMainBranchForCatalog(): Branch
{
    return Branch::query()->firstOrCreate(
        ['name' => 'Main Branch'],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );
}

test('superadmin creating category stores only on main branch', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $categoryName = 'Catalog Category '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('setting.category.store'), ['name' => $categoryName, 'status' => '1'])
        ->assertRedirect(route('setting.category.index'));

    $categories = Category::query()->where('name', $categoryName)->get();

    expect($categories)->toHaveCount(1)
        ->and((int) $categories->first()->branch_id)->toBe($mainBranch->id);

    expect(
        Category::query()->where('name', $categoryName)->where('branch_id', $operatingBranch->id)->exists(),
    )->toBeFalse();
});

test('superadmin category index only lists main branch catalog records', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('setting.category.view');

    $mainName = 'Main Panel Category '.fake()->unique()->numerify('###');
    $otherName = 'Other Panel Category '.fake()->unique()->numerify('###');

    Category::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => $mainName,
        'status' => 1,
    ]);
    Category::query()->create([
        'branch_id' => $operatingBranch->id,
        'name' => $otherName,
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('setting.category.index', ['search' => $mainName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/category/index')
            ->has('categories.data', 1)
            ->where('categories.data.0.name', $mainName));

    $this->actingAs($admin)
        ->get(route('setting.category.index', ['search' => $otherName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/category/index')
            ->has('categories.data', 0));
});

test('branch user only sees own branch categories in settings index', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('setting.category.view');

    Category::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Own Category '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);
    Category::query()->create([
        'branch_id' => $otherBranch->id,
        'name' => 'Other Category '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('setting.category.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/category/index')
            ->has('categories.data', 1));
});

test('product form quick create stores catalog only on selected branch', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('setting.category.create');

    $categoryName = 'Quick Category '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->postJson(route('setting.category.store'), [
            'name' => $categoryName,
            'status' => '1',
            'branch_id' => $operatingBranch->id,
        ])
        ->assertCreated()
        ->assertJsonPath('label', $categoryName);

    $categories = Category::query()->where('name', $categoryName)->get();

    expect($categories)->toHaveCount(1)
        ->and((int) $categories->first()->branch_id)->toBe($operatingBranch->id);

    expect(
        Category::query()->where('name', $categoryName)->where('branch_id', $mainBranch->id)->exists(),
    )->toBeFalse();
});

test('product form quick create without branch stores only on main branch', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('setting.category.create');

    $categoryName = 'Main Quick Category '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->postJson(route('setting.category.store'), [
            'name' => $categoryName,
            'status' => '1',
        ])
        ->assertCreated();

    expect(Category::query()->where('name', $categoryName)->count())->toBe(1)
        ->and(
            Category::query()->where('name', $categoryName)->where('branch_id', $mainBranch->id)->exists(),
        )->toBeTrue()
        ->and(
            Category::query()->where('name', $categoryName)->where('branch_id', $operatingBranch->id)->exists(),
        )->toBeFalse();
});

test('single branch product does not replicate quick created catalog to other branches', function () {
    $this->artisan('permissions:sync');

    ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['product.create', 'setting.category.create', 'setting.brand.create']);

    $categoryName = 'Single Branch Category '.fake()->unique()->numerify('######');
    $brandName = 'Single Branch Brand '.fake()->unique()->numerify('######');

    $categoryResponse = $this->actingAs($admin)
        ->postJson(route('setting.category.store'), [
            'name' => $categoryName,
            'status' => '1',
            'branch_id' => $operatingBranch->id,
        ])
        ->assertCreated();

    $brandResponse = $this->actingAs($admin)
        ->postJson(route('setting.brand.store'), [
            'name' => $brandName,
            'status' => '1',
            'branch_id' => $operatingBranch->id,
        ])
        ->assertCreated();

    $unit = Unit::query()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Single Branch Unit '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);

    $productName = 'Single Branch Product '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => (string) $operatingBranch->id,
            'category_id' => (string) $categoryResponse->json('value'),
            'brand_id' => (string) $brandResponse->json('value'),
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    expect(Category::query()->where('name', $categoryName)->count())->toBe(2)
        ->and(Brand::query()->where('name', $brandName)->count())->toBe(2)
        ->and(Product::query()->where('name', $productName)->count())->toBe(2);
});

test('all branches product maps branch specific catalog ids', function () {
    $this->artisan('permissions:sync');

    ensureMainBranchForCatalog();
    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    $groupId = (string) Str::uuid();
    $category = Category::query()->create([
        'branch_id' => $mainBranch->id,
        'catalog_group_id' => $groupId,
        'name' => 'Mapped Category '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);
    Category::query()->create([
        'branch_id' => $operatingBranch->id,
        'catalog_group_id' => $groupId,
        'name' => $category->name,
        'status' => 1,
    ]);

    $brandGroupId = (string) Str::uuid();
    $brand = Brand::query()->create([
        'branch_id' => $mainBranch->id,
        'catalog_group_id' => $brandGroupId,
        'name' => 'Mapped Brand '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);
    Brand::query()->create([
        'branch_id' => $operatingBranch->id,
        'catalog_group_id' => $brandGroupId,
        'name' => $brand->name,
        'status' => 1,
    ]);

    $unitGroupId = (string) Str::uuid();
    $unit = Unit::query()->create([
        'branch_id' => $mainBranch->id,
        'catalog_group_id' => $unitGroupId,
        'name' => 'Mapped Unit '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);
    Unit::query()->create([
        'branch_id' => $operatingBranch->id,
        'catalog_group_id' => $unitGroupId,
        'name' => $unit->name,
        'status' => 1,
    ]);

    $productName = 'Mapped Product '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => 'all',
            'category_id' => (string) $category->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'yes',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $mainProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', $mainBranch->id)
        ->first();

    $branchProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', $operatingBranch->id)
        ->first();

    expect($mainProduct)->not->toBeNull()
        ->and($branchProduct)->not->toBeNull()
        ->and($mainProduct->category_id)->toBe($category->id)
        ->and($branchProduct->category_id)->not->toBe($category->id)
        ->and($branchProduct->brand_id)->not->toBe($brand->id)
        ->and($branchProduct->unit_id)->not->toBe($unit->id);
});

test('main branch settings catalog is isolated from ecommerce branch panel', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    ['ecommerceBranch' => $ecommerceBranch, 'mainBranch' => $mainBranch] = branchCatalogEcommerceSetup();

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('setting.category.create');

    $categoryName = 'Admin Only Category '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('setting.category.store'), ['name' => $categoryName, 'status' => '1'])
        ->assertRedirect(route('setting.category.index'));

    expect(Category::query()->where('name', $categoryName)->count())->toBe(1)
        ->and((int) Category::query()->where('name', $categoryName)->value('branch_id'))->toBe($mainBranch->id)
        ->and(
            Category::query()->where('name', $categoryName)->where('branch_id', $ecommerceBranch->id)->exists(),
        )->toBeFalse();

    $ecommerceUser = User::factory()->create(['branch_id' => $ecommerceBranch->id]);
    $ecommerceUser->givePermissionTo('setting.category.view');

    $this->actingAs($ecommerceUser)
        ->get(route('setting.category.index', ['search' => $categoryName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/setting/category/index')
            ->has('categories.data', 0));
});

test('all branches product replicates catalog to ecommerce branch', function () {
    $this->artisan('permissions:sync');

    ['ecommerceBranch' => $ecommerceBranch, 'mainBranch' => $mainBranch] = branchCatalogEcommerceSetup();

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['product.create', 'setting.category.create', 'setting.brand.create']);

    $categoryName = 'Ecom Sync Category '.fake()->unique()->numerify('######');
    $brandName = 'Ecom Sync Brand '.fake()->unique()->numerify('######');

    $categoryResponse = $this->actingAs($admin)
        ->postJson(route('setting.category.store'), ['name' => $categoryName, 'status' => '1'])
        ->assertCreated();

    $brandResponse = $this->actingAs($admin)
        ->postJson(route('setting.brand.store'), ['name' => $brandName, 'status' => '1'])
        ->assertCreated();

    $unit = Unit::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Ecom Sync Unit '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);

    $productName = 'Ecom Sync Product '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => 'all',
            'category_id' => (string) $categoryResponse->json('value'),
            'brand_id' => (string) $brandResponse->json('value'),
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'yes',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $ecommerceCategory = Category::query()
        ->where('name', $categoryName)
        ->where('branch_id', $ecommerceBranch->id)
        ->first();

    $ecommerceBrand = Brand::query()
        ->where('name', $brandName)
        ->where('branch_id', $ecommerceBranch->id)
        ->first();

    $ecommerceProduct = Product::query()
        ->where('name', $productName)
        ->where('branch_id', $ecommerceBranch->id)
        ->first();

    expect($ecommerceCategory)->not->toBeNull()
        ->and($ecommerceBrand)->not->toBeNull()
        ->and($ecommerceProduct)->not->toBeNull()
        ->and($ecommerceProduct->category_id)->toBe($ecommerceCategory->id)
        ->and($ecommerceProduct->brand_id)->toBe($ecommerceBrand->id);
});

test('all branches product maps existing catalog by name instead of duplicating', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    $categoryName = 'Shared Category '.Str::uuid();

    $mainCategory = Category::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => $categoryName,
        'status' => 1,
    ]);

    $existingBranchCategory = Category::query()->create([
        'branch_id' => $operatingBranch->id,
        'name' => $categoryName,
        'status' => 1,
    ]);

    $brand = Brand::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Shared Brand '.Str::uuid(),
        'status' => 1,
    ]);

    $unit = Unit::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Shared Unit '.Str::uuid(),
        'status' => 1,
    ]);

    $productName = 'Shared Catalog Product '.Str::uuid();

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => 'all',
            'category_id' => (string) $mainCategory->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    expect(
        Category::query()
            ->where('name', $categoryName)
            ->where('branch_id', $operatingBranch->id)
            ->count(),
    )->toBe(1)
        ->and(
            Product::query()
                ->where('name', $productName)
                ->where('branch_id', $operatingBranch->id)
                ->value('category_id'),
        )->toBe($existingBranchCategory->id);
});

test('main branch product does not replicate settings catalog to other branches', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo(['product.create', 'setting.category.create', 'setting.brand.create']);

    $categoryName = 'Main Only Category '.fake()->unique()->numerify('######');
    $brandName = 'Main Only Brand '.fake()->unique()->numerify('######');

    $categoryResponse = $this->actingAs($admin)
        ->postJson(route('setting.category.store'), ['name' => $categoryName, 'status' => '1'])
        ->assertCreated();

    $brandResponse = $this->actingAs($admin)
        ->postJson(route('setting.brand.store'), ['name' => $brandName, 'status' => '1'])
        ->assertCreated();

    $unit = Unit::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Main Only Unit '.fake()->unique()->numerify('###'),
        'status' => 1,
    ]);

    $productName = 'Main Only Product '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => (string) $mainBranch->id,
            'category_id' => (string) $categoryResponse->json('value'),
            'brand_id' => (string) $brandResponse->json('value'),
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    expect(Category::query()->where('name', $categoryName)->count())->toBe(1)
        ->and(Brand::query()->where('name', $brandName)->count())->toBe(1)
        ->and(Product::query()->where('name', $productName)->count())->toBe(1)
        ->and(
            Category::query()->where('name', $categoryName)->where('branch_id', $operatingBranch->id)->exists(),
        )->toBeFalse();
});

test('superadmin creating tag stores only on main branch', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('setting.tag.create');

    $tagName = 'Main Panel Tag '.fake()->unique()->numerify('######');

    $this->actingAs($admin)
        ->postJson(route('setting.tag.store'), [
            'name' => $tagName,
            'status' => (string) CommonStatus::Active->value,
        ])
        ->assertCreated();

    expect(Tag::query()->where('name', $tagName)->count())->toBe(1)
        ->and((int) Tag::query()->where('name', $tagName)->value('branch_id'))->toBe($mainBranch->id)
        ->and(
            Tag::query()->where('name', $tagName)->where('branch_id', $operatingBranch->id)->exists(),
        )->toBeFalse();
});

test('all branches product creates one product per active branch with correct branch id', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    $category = Category::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'All Branch Category '.Str::uuid(),
        'status' => 1,
    ]);

    $brand = Brand::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'All Branch Brand '.Str::uuid(),
        'status' => 1,
    ]);

    $unit = Unit::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'All Branch Unit '.Str::uuid(),
        'status' => 1,
    ]);

    $productName = 'All Branch Product '.Str::uuid();
    $activeBranchIds = Branch::query()->active()->orderBy('id')->pluck('id');

    $this->actingAs($admin)
        ->post(route('product.store'), [
            'branch_id' => 'all',
            'category_id' => (string) $category->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $products = Product::query()->where('name', $productName)->get();

    expect($products)->toHaveCount($activeBranchIds->count())
        ->and($products->pluck('branch_id')->sort()->values()->all())
        ->toBe($activeBranchIds->sort()->values()->all())
        ->and($products->pluck('product_group_id')->unique())->toHaveCount(1)
        ->and($products->pluck('branch_id')->contains($mainBranch->id))->toBeTrue();
});

test('branch user product submission creates branch and pending main copies', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('product.create');

    $category = Category::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Branch Scoped Category '.Str::uuid(),
        'status' => 1,
    ]);

    $brand = Brand::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Branch Scoped Brand '.Str::uuid(),
        'status' => 1,
    ]);

    $unit = Unit::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Branch Scoped Unit '.Str::uuid(),
        'status' => 1,
    ]);

    $productName = 'Branch Scoped Product '.Str::uuid();

    $this->actingAs($user)
        ->post(route('product.store'), [
            'category_id' => (string) $category->id,
            'brand_id' => (string) $brand->id,
            'unit_id' => (string) $unit->id,
            'name' => $productName,
            'purchase_price' => '100',
            'sale_price' => '150',
            'visible' => 'no',
            'status' => '1',
        ])
        ->assertRedirect(route('product.index'));

    $products = Product::query()->where('name', $productName)->get();

    expect($products)->toHaveCount(2)
        ->and(
            $products->firstWhere('branch_id', $branch->id),
        )->not->toBeNull()
        ->and(
            $products->firstWhere('branch_id', $mainBranch->id),
        )->not->toBeNull()
        ->and(
            $products->firstWhere('branch_id', $mainBranch->id)?->source_branch_id,
        )->toBe($branch->id)
        ->and(
            $products->firstWhere('branch_id', $mainBranch->id)?->received_at,
        )->toBeNull();
});

test('product form only lists catalog options for admin panel branch not product branch field', function () {
    $this->artisan('permissions:sync');
    $this->withoutVite();

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    $mainCategory = Category::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Main Form Category '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $otherCategory = Category::query()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Other Form Category '.fake()->unique()->numerify('####'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/create')
            ->where("categories.{$mainCategory->id}", $mainCategory->name)
            ->missing("categories.{$otherCategory->id}"));
});

test('product catalog options api returns records for selected admin branch only', function () {
    $this->artisan('permissions:sync');

    $mainBranch = ensureMainBranchForCatalog();
    $operatingBranch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.create');

    $mainCategory = Category::query()->create([
        'branch_id' => $mainBranch->id,
        'name' => 'Main API Category '.Str::uuid(),
        'status' => 1,
    ]);

    $operatingCategory = Category::query()->create([
        'branch_id' => $operatingBranch->id,
        'name' => 'Operating API Category '.Str::uuid(),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.products.catalog-options', ['branch_id' => $operatingBranch->id]))
        ->assertOk()
        ->assertJsonPath('branch_id', $operatingBranch->id)
        ->assertJsonPath("categories.{$operatingCategory->id}", $operatingCategory->name)
        ->assertJsonMissingPath("categories.{$mainCategory->id}");
});

test('product catalog options api ignores requested branch for branch user', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo('product.create');

    $ownCategory = Category::query()->create([
        'branch_id' => $branch->id,
        'name' => 'Own API Category '.Str::uuid(),
        'status' => 1,
    ]);

    $otherCategory = Category::query()->create([
        'branch_id' => $otherBranch->id,
        'name' => 'Other API Category '.Str::uuid(),
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.products.catalog-options', ['branch_id' => $otherBranch->id]))
        ->assertOk()
        ->assertJsonPath('branch_id', $branch->id)
        ->assertJsonPath("categories.{$ownCategory->id}", $ownCategory->name)
        ->assertJsonMissingPath("categories.{$otherCategory->id}");
});
