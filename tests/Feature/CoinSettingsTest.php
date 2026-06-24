<?php

use App\Models\Branch;
use App\Models\CoinSettings;
use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function coinSettingsUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function coinSettingsUserWithPermissions(): User
{
    $user = coinSettingsUser();

    Permission::findOrCreate('setting.coin-settings.view', 'web');
    Permission::findOrCreate('setting.coin-settings.create', 'web');
    Permission::findOrCreate('setting.coin-settings.update', 'web');
    $user->givePermissionTo([
        'setting.coin-settings.view',
        'setting.coin-settings.create',
        'setting.coin-settings.update',
    ]);

    return $user;
}

test('guests are redirected from coin settings', function () {
    $this->get('/setting/coin-settings')->assertRedirect(route('login'));
});

test('branch user without permission cannot view coin settings', function () {
    $user = coinSettingsUser();

    $this->actingAs($user)
        ->get('/setting/coin-settings')
        ->assertForbidden();
});

test('authorized branch user can view coin settings index without existing settings', function () {
    $user = coinSettingsUserWithPermissions();

    $this->actingAs($user)
        ->get('/setting/coin-settings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/coin-settings/index')
            ->where('coinSettings', null)
            ->has('branchName'));
});

test('authorized branch user can open create coin settings page', function () {
    $user = coinSettingsUserWithPermissions();

    $this->actingAs($user)
        ->get('/setting/coin-settings/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/coin-settings/edit')
            ->where('isCreating', true)
            ->has('coinSettings'));
});

test('authorized branch user can store coin settings', function () {
    $user = coinSettingsUserWithPermissions();

    $this->actingAs($user)
        ->post('/setting/coin-settings', [
            'enabled' => true,
            'earn_spend_amount' => '100',
            'earn_coins' => '2',
            'coin_value' => '1.5',
            'min_redeem_coins' => '5',
            'max_redeem_percent' => '40',
        ])
        ->assertRedirect(route('setting.coin-settings.index'));

    $settings = CoinSettings::query()->where('branch_id', $user->branch_id)->first();

    expect($settings)->not->toBeNull();
    expect($settings->enabled)->toBeTrue();
    expect((float) $settings->earn_spend_amount)->toBe(100.0);
    expect((float) $settings->earn_coins)->toBe(2.0);
    expect((float) $settings->coin_value)->toBe(1.5);
});

test('create page redirects to edit when settings already exist', function () {
    $user = coinSettingsUserWithPermissions();

    CoinSettings::query()->create([
        'branch_id' => $user->branch_id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 50,
    ]);

    $this->actingAs($user)
        ->get('/setting/coin-settings/create')
        ->assertRedirect(route('setting.coin-settings.edit'));
});

test('edit page redirects to create when settings do not exist', function () {
    $user = coinSettingsUserWithPermissions();

    $this->actingAs($user)
        ->get('/setting/coin-settings/edit')
        ->assertRedirect(route('setting.coin-settings.create'));
});

test('authorized branch user can update coin settings', function () {
    $user = coinSettingsUserWithPermissions();

    CoinSettings::query()->create([
        'branch_id' => $user->branch_id,
        'enabled' => false,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 50,
    ]);

    $this->actingAs($user)
        ->put('/setting/coin-settings', [
            'enabled' => true,
            'earn_spend_amount' => '200',
            'earn_coins' => '3',
            'coin_value' => '2',
            'min_redeem_coins' => '10',
            'max_redeem_percent' => '30',
        ])
        ->assertRedirect(route('setting.coin-settings.index'));

    $settings = CoinSettings::query()->where('branch_id', $user->branch_id)->first();

    expect($settings->enabled)->toBeTrue();
    expect((float) $settings->earn_spend_amount)->toBe(200.0);
    expect((float) $settings->earn_coins)->toBe(3.0);
    expect((float) $settings->coin_value)->toBe(2.0);
});

test('customer coins api returns balance and settings', function () {
    $user = coinSettingsUser();
    Permission::findOrCreate('party.customer.view', 'web');
    $user->givePermissionTo('party.customer.view');

    CoinSettings::query()->create([
        'branch_id' => $user->branch_id,
        'enabled' => true,
        'earn_spend_amount' => 100,
        'earn_coins' => 1,
        'coin_value' => 1,
        'min_redeem_coins' => 0,
        'max_redeem_percent' => 50,
    ]);

    $customer = Customer::factory()->create([
        'branch_id' => $user->branch_id,
        'point' => 75,
        'is_default' => false,
    ]);

    $this->actingAs($user)
        ->getJson("/api/customers/{$customer->id}/coins")
        ->assertSuccessful()
        ->assertJson([
            'balance' => 75.0,
            'is_default' => false,
            'settings' => [
                'enabled' => true,
                'earn_spend_amount' => 100,
                'earn_coins' => 1,
                'coin_value' => 1,
            ],
        ]);
});
