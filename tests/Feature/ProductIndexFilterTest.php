<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function productIndexAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function productIndexBranchUser(int $branchId): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => $branchId]);
    Permission::findOrCreate('product.view', 'web');
    $user->givePermissionTo('product.view');

    return $user;
}

function productIndexMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

test('product index page passes brands and tags props', function () {
    $admin = productIndexAdmin();

    $this->actingAs($admin)
        ->get(route('product.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->has('brands')
            ->has('tags')
            ->has('products.data')
            ->has('products.links')
            ->where('products.per_page', 10));
});

test('product index can filter by brand', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();
    $brand1 = Brand::factory()->create(['status' => 1]);
    $brand2 = Brand::factory()->create(['status' => 1]);
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'brand_id' => $brand1->id,
        'name' => 'Product A '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);
    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'brand_id' => $brand2->id,
        'name' => 'Product B '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['brand_id' => $brand1->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.brand_id', (string) $brand1->id)
            ->where('products.data', fn ($data) => collect($data)->every(fn ($p) => $p['brand_id'] === $brand1->id)));
});

test('product index can filter by tag', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'tags' => ['Casual'],
        'name' => 'Casual Shirt '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);
    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'tags' => ['Formal'],
        'name' => 'Formal Shirt '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['tag' => 'Casual']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.tag', 'Casual'));
});

test('product index search includes brand name', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();
    $brand = Brand::factory()->create(['name' => 'Nike Special '.fake()->unique()->numerify('####'), 'status' => 1]);
    $category = Category::factory()->create(['status' => 1]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'brand_id' => $brand->id,
        'name' => 'Running Shoe '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['search' => $brand->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('products.data', fn ($data) => count($data) >= 1));
});

test('product index search includes category name', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();
    $category = Category::factory()->create(['name' => 'Electronics Pro '.fake()->unique()->numerify('####'), 'status' => 1]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'name' => 'Widget Device '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['search' => $category->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('products.data', fn ($data) => count($data) >= 1));
});

test('product index search includes tag name', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();
    $category = Category::factory()->create(['status' => 1]);
    $tag = 'Summer Vibes '.fake()->unique()->numerify('####');

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'tags' => [$tag],
        'name' => 'Beach Towel '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['search' => $tag]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('products.data', fn ($data) => count($data) >= 1));
});

test('product index returns main branch as default filter for admin', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();

    $this->actingAs($admin)
        ->get(route('product.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.branch_id', (string) Branch::resolveMainBranchId())
            ->missing('filters.search'));
});

test('admin product index shows products from all branches when explicitly filtered', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();

    $otherBranch = Branch::factory()->create();
    $category = Category::factory()->create(['status' => 1, 'branch_id' => Branch::resolveMainBranchId()]);
    $sharedPrefix = 'Shared Prefix Product '.fake()->unique()->numerify('######');
    $mainName = $sharedPrefix.' Main';
    $otherName = $sharedPrefix.' Other';

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'name' => $mainName,
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'category_id' => $category->id,
        'name' => $otherName,
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => 'all', 'search' => $sharedPrefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.branch_id', 'all')
            ->has('products.data', 2));
});

test('admin product index defaults to main branch products only', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();

    $otherBranch = Branch::factory()->create();
    $category = Category::factory()->create(['status' => 1, 'branch_id' => Branch::resolveMainBranchId()]);
    $sharedPrefix = 'Default Branch Product '.fake()->unique()->numerify('######');
    $mainName = $sharedPrefix.' Main';
    $otherName = $sharedPrefix.' Other';

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'name' => $mainName,
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'category_id' => $category->id,
        'name' => $otherName,
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['search' => $sharedPrefix]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.branch_id', (string) Branch::resolveMainBranchId())
            ->has('products.data', 1)
            ->where('products.data.0.name', $mainName));
});

test('admin product index can filter to a single branch', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();

    $otherBranch = Branch::factory()->create();
    $category = Category::factory()->create(['status' => 1, 'branch_id' => Branch::resolveMainBranchId()]);
    $uniqueName = 'Main Only Product '.fake()->unique()->numerify('######');

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'name' => $uniqueName,
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'category_id' => $category->id,
        'name' => 'Other Branch Product '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => (string) Branch::resolveMainBranchId(), 'search' => $uniqueName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', $uniqueName));
});

test('admin product index can filter by specific branch', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();

    $otherBranch = Branch::factory()->create();
    $category = Category::factory()->create(['status' => 1, 'branch_id' => $otherBranch->id]);
    $uniqueName = 'Branch Two Product '.fake()->unique()->numerify('######');

    Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'category_id' => $category->id,
        'name' => $uniqueName,
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => $otherBranch->id, 'search' => $uniqueName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('filters.branch_id', (string) $otherBranch->id)
            ->has('products.data', 1)
            ->where('products.data.0.name', $uniqueName));
});

test('product index shows selected branch column only on main catalog views', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();
    $otherBranch = Branch::factory()->create();
    $branchUser = productIndexBranchUser($otherBranch->id);
    $mainBranchId = Branch::resolveMainBranchId();

    $this->actingAs($admin)
        ->get(route('product.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('showSelectedBranchColumn', true));

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('showSelectedBranchColumn', true));

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => (string) $mainBranchId]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('showSelectedBranchColumn', true));

    $this->actingAs($admin)
        ->get(route('product.index', ['branch_id' => (string) $otherBranch->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('showSelectedBranchColumn', false));

    $this->actingAs($branchUser)
        ->get(route('product.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->where('showSelectedBranchColumn', false));
});

test('branch user product index only shows own branch products', function () {
    productIndexMainBranch();

    $branch = Branch::factory()->create();
    $user = productIndexBranchUser($branch->id);
    $category = Category::factory()->create(['status' => 1, 'branch_id' => $branch->id]);
    $uniqueName = 'My Branch Product '.fake()->unique()->numerify('######');

    Product::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'name' => $uniqueName,
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'category_id' => $category->id,
        'name' => 'Main Branch Product '.fake()->unique()->numerify('######'),
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('product.index', ['search' => $uniqueName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', $uniqueName)
            ->missing('filters.branch_id'));
});

test('admin product index shows distributed branch stock for filtered branch', function () {
    productIndexMainBranch();
    $admin = productIndexAdmin();

    $targetBranch = Branch::factory()->create(['name' => 'Sell Branch '.fake()->unique()->numerify('###')]);
    $groupId = (string) Str::uuid();
    $uniqueName = 'Distributed Stock Product '.fake()->unique()->numerify('######');

    $branchProduct = Product::factory()->create([
        'branch_id' => $targetBranch->id,
        'product_group_id' => $groupId,
        'name' => $uniqueName,
        'status' => 1,
    ]);

    Product::factory()->create([
        'branch_id' => Branch::resolveMainBranchId(),
        'product_group_id' => $groupId,
        'name' => $uniqueName,
        'status' => 1,
    ]);

    Batch::factory()->for($branchProduct)->withStock(12)->create([
        'branch_id' => $targetBranch->id,
        'purchase_price' => 100,
    ]);

    $this->actingAs($admin)
        ->get(route('product.index', [
            'branch_id' => (string) $targetBranch->id,
            'search' => $uniqueName,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->has('products.data', 1)
            ->where('products.data.0.batches_sum_available', '12.00'));
});

test('branch user product index shows own branch distributed stock', function () {
    productIndexMainBranch();

    $targetBranch = Branch::factory()->create();
    $user = productIndexBranchUser($targetBranch->id);
    $uniqueName = 'Branch User Stock Product '.fake()->unique()->numerify('######');

    $product = Product::factory()->create([
        'branch_id' => $targetBranch->id,
        'name' => $uniqueName,
        'status' => 1,
    ]);

    Batch::factory()->for($product)->withStock(9)->create([
        'branch_id' => $targetBranch->id,
        'purchase_price' => 50,
    ]);

    $this->actingAs($user)
        ->get(route('product.index', ['search' => $uniqueName]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/product/index')
            ->has('products.data', 1)
            ->where('products.data.0.batches_sum_available', '9.00'));
});
