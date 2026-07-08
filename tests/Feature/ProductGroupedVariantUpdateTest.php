<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function groupedVariantAdmin(): User
{
    Permission::findOrCreate('product.update', 'web');

    $admin = User::factory()->create(['branch_id' => null]);
    $admin->givePermissionTo('product.update');

    return $admin;
}

test('editing variants on a grouped all-branches product updates the edited branch copy', function () {
    $admin = groupedVariantAdmin();

    Branch::query()->firstOrCreate(
        ['id' => Branch::MAIN_BRANCH_ID],
        Branch::factory()->make(['name' => 'Main Branch'])->toArray(),
    );
    $mainBranchId = Branch::resolveMainBranchId();
    $otherBranch = Branch::factory()->create();

    $groupId = (string) Str::uuid();
    $name = 'Grouped Variant Product '.fake()->unique()->numerify('######');

    $categoryId = Category::factory()->create(['status' => 1])->id;
    $brandId = Brand::factory()->create(['status' => 1])->id;
    $unitId = Unit::query()->create(['name' => 'Unit '.fake()->unique()->numerify('####'), 'status' => 1])->id;

    $mainProduct = Product::factory()->create([
        'branch_id' => $mainBranchId,
        'product_group_id' => $groupId,
        'selected_branch_id' => null,
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'unit_id' => $unitId,
        'name' => $name,
        'purchase_price' => 0,
        'sale_price' => 0,
        'code' => null,
    ]);

    $mainVariation = ProductVariation::query()->create([
        'product_id' => $mainProduct->id,
        'branch_id' => $mainBranchId,
        'sku' => fake()->unique()->numerify('########'),
        'price' => 300,
        'purchase_price' => 2,
        'stock' => 0,
        'variation_data' => ['label' => 'Black-0', 'Color' => 'Black', 'Size' => '0'],
    ]);

    $branchProduct = Product::factory()->create([
        'branch_id' => $otherBranch->id,
        'product_group_id' => $groupId,
        'selected_branch_id' => null,
        'category_id' => $categoryId,
        'brand_id' => $brandId,
        'unit_id' => $unitId,
        'name' => $name.' / branch',
        'purchase_price' => 0,
        'sale_price' => 0,
        'code' => null,
    ]);

    $branchVariation = ProductVariation::query()->create([
        'product_id' => $branchProduct->id,
        'branch_id' => $otherBranch->id,
        'sku' => fake()->unique()->numerify('########'),
        'price' => 300,
        'purchase_price' => 2,
        'stock' => 0,
        'variation_data' => ['label' => 'Black-0', 'Color' => 'Black', 'Size' => '0'],
    ]);

    // Simulate the edit page for the branch product: formBranchId resolves to '' for a
    // grouped product with no stored selection, so the form submits branch_id=''.
    $payload = [
        'branch_id' => '',
        'category_id' => (string) $categoryId,
        'brand_id' => (string) $brandId,
        'unit_id' => (string) $unitId,
        'name' => $name.' / branch',
        'code' => '',
        'purchase_price' => '0',
        'sale_price' => '0',
        'has_variants' => true,
        'visible' => 'no',
        'status' => '1',
        'combinations' => [
            [
                'id' => $branchVariation->id,
                'variant' => 'Black-0',
                'variation_data' => ['label' => 'Black-0', 'Color' => 'Black', 'Size' => '0'],
                'sale_price' => '450',
                'purchase_price' => '5',
                'sku' => $branchVariation->sku,
                'stock' => '0',
            ],
        ],
    ];

    $this->actingAs($admin)
        ->patch(route('product.update', $branchProduct), $payload)
        ->assertRedirect(route('product.index'));

    $branchVariation->refresh();
    $mainVariation->refresh();

    // The edited branch copy is updated...
    expect((float) $branchVariation->price)->toBe(450.0)
        ->and((float) $branchVariation->purchase_price)->toBe(5.0)
        // ...and the shared price change propagates to the main sibling, whose own
        // variation row (and barcode) is preserved rather than deleted.
        ->and((float) $mainVariation->price)->toBe(450.0)
        ->and((float) $mainVariation->purchase_price)->toBe(5.0)
        ->and(ProductVariation::query()->where('product_id', $mainProduct->id)->count())->toBe(1);
});
