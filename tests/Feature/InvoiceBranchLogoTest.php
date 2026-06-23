<?php

use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Sell;
use App\Models\User;
use App\Support\StorageUrl;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function invoiceBranchLogoUser(string $permission): User
{
    $branch = Branch::factory()->create([
        'logo' => 'branches/test-branch-logo.png',
    ]);

    $user = User::factory()->create(['branch_id' => $branch->id]);

    Permission::findOrCreate($permission, 'web');
    $user->givePermissionTo($permission);

    return $user;
}

test('sell show includes branch logo url for invoice display', function () {
    $user = invoiceBranchLogoUser('inventory.sell.view');
    $branch = Branch::query()->findOrFail($user->branch_id);

    $sell = Sell::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'type' => SaleType::Sale,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.sell.show', $sell))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/show')
            ->where('sell.branch.logo_url', StorageUrl::public($branch->logo)));
});

test('purchase show includes branch logo url for invoice display', function () {
    $user = invoiceBranchLogoUser('inventory.purchase.view');
    $branch = Branch::query()->findOrFail($user->branch_id);

    $purchase = Purchase::query()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'gross_amount' => 100,
        'paid_amount' => 100,
        'due_amount' => 0,
        'serial' => 'INVP'.fake()->unique()->numerify('########'),
    ]);

    $this->actingAs($user)
        ->get(route('inventory.purchase.show', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/purchase/show')
            ->where('purchase.branch.logo_url', StorageUrl::public($branch->logo)));
});
