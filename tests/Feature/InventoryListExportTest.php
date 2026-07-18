<?php

use App\Models\Branch;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function inventoryListExportAdmin(array $permissions): User
{
    Artisan::call('permissions:sync');

    $user = User::factory()->create(['branch_id' => null]);
    $user->givePermissionTo($permissions);

    return $user;
}

function inventoryListExportMainBranch(): int
{
    return Branch::query()->firstOrCreate(
        ['name' => Branch::MAIN_BRANCH_NAME],
        Branch::factory()->make(['name' => Branch::MAIN_BRANCH_NAME])->toArray(),
    )->id;
}

test('purchase index accepts date filters and exports', function () {
    inventoryListExportMainBranch();
    $admin = inventoryListExportAdmin(['inventory.purchase.view']);

    Purchase::factory()->create([
        'date' => now()->subDay()->toDateString(),
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('inventory.purchase.index', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inventory/purchase/index')
            ->has('filters')
            ->where('filters.date_from', now()->subDays(3)->toDateString()));

    $this->actingAs($admin)
        ->get(route('inventory.purchase.export-excel'))
        ->assertOk()
        ->assertHeader('content-disposition');

    $this->actingAs($admin)
        ->get(route('inventory.purchase.export-pdf'))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($admin)
        ->get(route('inventory.purchase.export-print'))
        ->assertOk()
        ->assertSee('Purchases', false);

    $admin->delete();
});

test('purchase return damage stock distribution and supplier payment export routes respond', function () {
    inventoryListExportMainBranch();
    $admin = inventoryListExportAdmin([
        'inventory.purchase-return.view',
        'inventory.damage.view',
        'inventory.stock-distribution.view',
        'party.supplier-payment.view',
    ]);

    $this->actingAs($admin)
        ->get(route('inventory.purchase-return.export-print'))
        ->assertOk()
        ->assertSee('Purchase Returns', false);

    $this->actingAs($admin)
        ->get(route('inventory.damage.export-print'))
        ->assertOk()
        ->assertSee('Damage', false);

    $this->actingAs($admin)
        ->get(route('inventory.stock-distribution.export-print'))
        ->assertOk()
        ->assertSee('Stock Distributions', false);

    $this->actingAs($admin)
        ->get(route('party.supplier-payment.export-print'))
        ->assertOk()
        ->assertSee('Supplier Payments', false);

    $admin->delete();
});
