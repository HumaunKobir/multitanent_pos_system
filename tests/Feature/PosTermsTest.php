<?php

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sell;
use App\Models\SellProduct;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function posTermsUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function posTermsUserWithPermissions(): User
{
    $user = posTermsUser();

    Permission::findOrCreate('setting.pos-terms.view', 'web');
    Permission::findOrCreate('setting.pos-terms.update', 'web');
    $user->givePermissionTo(['setting.pos-terms.view', 'setting.pos-terms.update']);

    return $user;
}

function posTermsUserWithSellAccess(): User
{
    $user = posTermsUser();

    Permission::findOrCreate('inventory.sell.create', 'web');
    Permission::findOrCreate('inventory.sell.view', 'web');
    $user->givePermissionTo(['inventory.sell.create', 'inventory.sell.view']);

    return $user;
}

function posTermsSellProduct(float $available = 20, ?int $branchId = null): array
{
    $product = Product::factory()->create(['branch_id' => $branchId]);
    $batch = Batch::factory()->for($product)->withStock($available)->create(['branch_id' => $branchId]);

    return compact('product', 'batch');
}

test('guests are redirected from pos terms settings', function () {
    $this->get('/setting/pos-terms')->assertRedirect(route('login'));
});

test('super admin without branch cannot access pos terms settings', function () {
    $user = User::factory()->create(['branch_id' => null]);

    $this->actingAs($user)
        ->get('/setting/pos-terms')
        ->assertForbidden();
});

test('branch user without permission cannot access pos terms settings', function () {
    $user = posTermsUser();

    $this->actingAs($user)
        ->get('/setting/pos-terms')
        ->assertForbidden();
});

test('branch user can view pos terms settings', function () {
    $user = posTermsUserWithPermissions();
    $branch = Branch::query()->findOrFail($user->branch_id);
    $branch->update(['pos_terms_and_conditions' => '<p>Return within 7 days.</p>']);

    $this->actingAs($user)
        ->get('/setting/pos-terms')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/pos-terms/edit')
            ->where('posTerms.content', '<p>Return within 7 days.</p>')
            ->where('posTerms.branch_name', $branch->name));
});

test('branch user can update pos terms for their branch only', function () {
    $user = posTermsUserWithPermissions();
    $otherBranch = Branch::factory()->create(['name' => 'Other Branch']);
    $content = '<p>Warranty is non-transferable.</p>';

    $this->actingAs($user)
        ->put('/setting/pos-terms', [
            'content' => $content,
        ])
        ->assertRedirect(route('setting.pos-terms.edit'))
        ->assertSessionHas('success');

    $branch = Branch::query()->findOrFail($user->branch_id);

    expect($branch->pos_terms_and_conditions)->toBe($content)
        ->and(Branch::query()->findOrFail($otherBranch->id)->pos_terms_and_conditions)->toBeNull();
});

test('branch user without update permission cannot save pos terms', function () {
    $user = posTermsUser();
    Permission::findOrCreate('setting.pos-terms.view', 'web');
    $user->givePermissionTo('setting.pos-terms.view');

    $this->actingAs($user)
        ->put('/setting/pos-terms', [
            'content' => '<p>Blocked update.</p>',
        ])
        ->assertForbidden();
});

test('branch user can clear pos terms content', function () {
    $user = posTermsUserWithPermissions();

    Branch::query()
        ->whereKey($user->branch_id)
        ->update(['pos_terms_and_conditions' => '<p>Old terms</p>']);

    $this->actingAs($user)
        ->put('/setting/pos-terms', [
            'content' => '',
        ])
        ->assertRedirect(route('setting.pos-terms.edit'));

    expect(Branch::query()->findOrFail($user->branch_id)->pos_terms_and_conditions)->toBeNull();
});

test('sell create includes pos terms when branch has content', function () {
    $user = posTermsUserWithSellAccess();
    $content = '<p>No cash refund.</p>';

    Branch::query()
        ->whereKey($user->branch_id)
        ->update(['pos_terms_and_conditions' => $content]);

    $this->actingAs($user)
        ->get('/inventory/sell/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/create')
            ->where('posTerms', $content));
});

test('sell create omits pos terms when branch content is empty', function () {
    $user = posTermsUserWithSellAccess();

    Branch::query()
        ->whereKey($user->branch_id)
        ->update(['pos_terms_and_conditions' => '<p> </p>']);

    $this->actingAs($user)
        ->get('/inventory/sell/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/create')
            ->where('posTerms', null));
});

test('sell show loads branch pos terms for printing', function () {
    $user = posTermsUserWithSellAccess();
    ['product' => $product] = posTermsSellProduct(10, $user->branch_id);
    $content = '<p>Exchange within 3 days.</p>';

    Branch::query()
        ->whereKey($user->branch_id)
        ->update(['pos_terms_and_conditions' => $content]);

    $sell = Sell::factory()->create([
        'branch_id' => $user->branch_id,
        'user_id' => $user->id,
        'gross_amount' => 100,
        'paid_amount' => 100,
    ]);

    SellProduct::query()->create([
        'sell_id' => $sell->id,
        'branch_id' => $user->branch_id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.sell.show', $sell))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/inventory/sell/show')
            ->where('sell.branch.pos_terms_and_conditions', $content)
            ->has('sell.created_at'));
});
