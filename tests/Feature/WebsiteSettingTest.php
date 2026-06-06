<?php

use App\Models\Branch;
use App\Models\ConfigDictionary;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\EcommerceBranchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function websiteSettingSuperAdmin(): User
{
    return User::factory()->create(['branch_id' => null]);
}

function websiteSettingBranchUser(): User
{
    $branch = Branch::factory()->create();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function websiteSettingEcommerceBranch(): Branch
{
    EcommerceBranchService::resetResolvedId();

    return Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );
}

function websiteSettingEcommerceUser(): User
{
    $branch = websiteSettingEcommerceBranch();

    return User::factory()->create(['branch_id' => $branch->id]);
}

function websiteSettingPayload(array $overrides = []): array
{
    return array_merge([
        'website_name' => 'Coolness Point Test',
        'phone' => '01700000000',
        'email' => 'hello@coolness.test',
        'address' => 'Dhaka, Bangladesh',
        'fb_share_for_withdraw' => 'https://facebook.com/coolness',
        'youtube' => 'https://youtube.com/coolness',
        'twit' => 'https://x.com/coolness',
        'linkend' => 'https://linkedin.com/company/coolness',
        'topnotice1' => 'Free shipping this week',
        'footer_description' => 'Premium fashion delivered nationwide.',
        'support_time' => 'Sat–Thu, 9AM–9PM',
        'delivery_charge_inside_dhaka' => 80,
        'delivery_charge_outside_dhaka' => 150,
        'meta_tags' => 'fashion, apparel',
        'meta_description' => 'Shop quality fashion online.',
        'newsletter_enabled' => '1',
        'newsletter_title' => 'Join our list',
        'newsletter_description' => 'Get offers in your inbox.',
        'newsletter_placeholder' => 'Email here',
        'newsletter_button' => 'Join',
    ], $overrides);
}

test('guests are redirected from website settings', function () {
    $this->get('/setting/website')->assertRedirect(route('login'));
});

test('non ecommerce branch users are redirected from website settings', function () {
    $this->actingAs(websiteSettingBranchUser())
        ->get('/setting/website')
        ->assertRedirect(route('branch-panel.dashboard'));
});

test('superadmin can view website settings page', function () {
    ConfigDictionary::set('website_name', 'Existing Store');

    $this->actingAs(websiteSettingSuperAdmin())
        ->get('/setting/website')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/setting/website/index')
            ->where('settings.website_name', 'Existing Store')
        );
});

test('superadmin can update website settings', function () {
    $this->actingAs(websiteSettingSuperAdmin())
        ->put('/setting/website', websiteSettingPayload())
        ->assertRedirect(route('setting.website.edit'))
        ->assertSessionHas('success');

    expect(ConfigDictionary::get('website_name'))->toBe('Coolness Point Test');
    expect(ConfigDictionary::get('delivery_charge_inside_dhaka'))->toBe('80');
    expect(ConfigDictionary::get('footer_description'))->toBe('Premium fashion delivered nationwide.');
    expect(ConfigDictionary::get('support_time'))->toBe('Sat–Thu, 9AM–9PM');
});

test('superadmin can upload logo and favicon', function () {
    Storage::fake('public');

    $logo = UploadedFile::fake()->image('logo.png');
    $favicon = UploadedFile::fake()->image('favicon.png');

    $this->actingAs(websiteSettingSuperAdmin())
        ->put('/setting/website', array_merge(websiteSettingPayload(), [
            'logo' => $logo,
            'fav_icon' => $favicon,
        ]))
        ->assertRedirect(route('setting.website.edit'));

    $logoPath = ConfigDictionary::get('logo');
    $faviconPath = ConfigDictionary::get('fav_icon');

    expect($logoPath)->not->toBeNull();
    expect($faviconPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);
    Storage::disk('public')->assertExists($faviconPath);
});

test('website settings are shared on storefront pages', function () {
    ConfigDictionary::setMany([
        'footer_description' => 'Shared footer copy',
        'support_time' => 'Daily, 10AM–6PM',
        'delivery_charge_inside_dhaka' => '99',
        'delivery_charge_outside_dhaka' => '199',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('footerDescription', 'Shared footer copy')
            ->where('supportTime', 'Daily, 10AM–6PM')
            ->where('deliveryCharges.inside_dhaka', 99)
            ->where('deliveryCharges.outside_dhaka', 199)
        );
});

test('checkout uses configured delivery charges', function () {
    ConfigDictionary::setMany([
        'delivery_charge_inside_dhaka' => '75',
        'delivery_charge_outside_dhaka' => '140',
    ]);

    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['sale_price' => 1200, 'discount_price' => 0]);
    $cartKey = $product->id.'-0';
    $cartItem = [
        $cartKey => [
            'product_id' => $product->id,
            'name' => $product->name,
            'image' => null,
            'price' => 1200.0,
            'quantity' => 1,
            'variation_id' => null,
            'sku' => null,
        ],
    ];

    session(['cart' => $cartItem]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Test',
            'phone' => '01700000004',
            'address' => 'Dhaka',
            'payment_method' => 'cod',
            'delivery_zone' => 1,
        ]);

    $this->assertDatabaseHas('online_orders', [
        'phone' => '01700000004',
        'delivery_charge' => 75,
    ]);

    session(['cart' => $cartItem]);

    $this->actingAs($customer, 'customer')
        ->post(route('checkout.store'), [
            'name' => 'Test',
            'phone' => '01700000005',
            'address' => 'Chittagong',
            'payment_method' => 'cod',
            'delivery_zone' => 2,
        ]);

    $this->assertDatabaseHas('online_orders', [
        'phone' => '01700000005',
        'delivery_charge' => 140,
    ]);
});

test('ecommerce branch user with permission can update website settings', function () {
    test()->artisan('permissions:sync');

    $user = websiteSettingEcommerceUser();
    $user->givePermissionTo('setting.website.update');

    $this->actingAs($user)
        ->put('/setting/website', websiteSettingPayload(['website_name' => 'Ecommerce Branch Store']))
        ->assertRedirect(route('setting.website.edit'))
        ->assertSessionHas('success');

    expect(ConfigDictionary::get('website_name'))->toBe('Ecommerce Branch Store');
});
