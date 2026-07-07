<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function variationStockUser(array $permissions = []): User
{
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    return $user;
}

test('purchase increments the acting branch variation stock instead of the main warehouse', function () {
    $this->artisan('permissions:sync');

    $user = variationStockUser(['inventory.purchase.create']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create([
        'branch_id' => $user->branch_id,
        'balance' => 0,
    ]);

    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'branch_id' => $user->branch_id,
        'sku' => '64197058',
        'variation_data' => ['Color' => 'Blue', 'Size' => 'L', 'label' => 'Blue-L'],
        'price' => 40,
        'purchase_price' => 50,
        'stock' => 0,
        'status' => 1,
    ]);

    $this->actingAs($user)
        ->post('/inventory/purchase', [
            'supplier_id' => $supplier->id,
            'date' => now()->format('Y-m-d'),
            'discount' => '0',
            'vat' => '0',
            'paid_amount' => '0',
            'payment_account_id' => $cash->id,
            'items' => [[
                'product_id' => $product->id,
                'variation_id' => $variation->id,
                'unit_price' => '50',
                'quantity' => '100',
                'free_quantity' => '0',
            ]],
        ])
        ->assertRedirect(route('inventory.purchase.index'));

    $variation->refresh();

    expect((float) $variation->stock)->toBe(100.0)
        ->and(ProductVariation::query()->where('product_id', $product->id)->count())->toBe(1);
});

test('deleting a variant purchase restores stock on the acting branch variation', function () {
    $this->artisan('permissions:sync');

    $user = variationStockUser(['inventory.purchase.create', 'inventory.purchase.delete']);
    $cash = seedAccountingAccounts(branchId: $user->branch_id);

    $supplier = Supplier::factory()->create(['branch_id' => $user->branch_id]);
    $product = Product::factory()->create(['branch_id' => $user->branch_id]);

    $variation = ProductVariation::create([
        'product_id' => $product->id,
        'branch_id' => $user->branch_id,
        'sku' => '99887766',
        'variation_data' => ['Color' => 'Red', 'label' => 'Red'],
        'price' => 40,
        'purchase_price' => 50,
        'stock' => 0,
        'status' => 1,
    ]);

    $this->actingAs($user)->post('/inventory/purchase', [
        'supplier_id' => $supplier->id,
        'date' => now()->format('Y-m-d'),
        'discount' => '0',
        'vat' => '0',
        'paid_amount' => '0',
        'payment_account_id' => $cash->id,
        'items' => [[
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'unit_price' => '50',
            'quantity' => '10',
            'free_quantity' => '0',
        ]],
    ])->assertRedirect(route('inventory.purchase.index'));

    expect((float) $variation->fresh()->stock)->toBe(10.0);

    $purchase = Purchase::query()->latest('id')->first();

    $this->actingAs($user)
        ->delete("/inventory/purchase/{$purchase->id}")
        ->assertRedirect(route('inventory.purchase.index'));

    expect((float) $variation->fresh()->stock)->toBe(0.0);
});
