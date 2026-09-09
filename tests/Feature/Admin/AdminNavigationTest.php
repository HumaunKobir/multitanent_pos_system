<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\EcommerceBranchService;
use App\Support\AdminNavigation;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('guests receive empty admin navigation', function () {
    $navigation = app(AdminNavigation::class)->build(null);

    expect($navigation)->toBe([]);
});

test('authenticated users see the central admin navigation tree', function () {
    $user = User::factory()->create();

    $navigation = app(AdminNavigation::class)->build($user);
    $titles = collect($navigation)->pluck('title');

    expect($titles)->toContain(
        'Dashboard',
        'Parties',
        'Branch',
        'User',
        'Roles',
        'Website Manage',
        'Accounts',
        'Reports',
    )->and($titles)->not->toContain(
        'Sales',
        'Purchases',
        'Suppliers',
        'Customers',
        'Settings',
    );

    $reports = collect($navigation)->firstWhere('title', 'Reports');
    expect($reports)->not->toBeNull()
        ->and(collect($reports['children'])->pluck('title')->all())->toContain(
            'Profit & Loss',
            'Balance Sheet',
        );
});

test('settings section is available to branch panel users', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo([
        'setting.category.view',
        'setting.tag.view',
        'setting.brand.view',
        'setting.unit.view',
        'setting.color.view',
        'setting.size.view',
        'setting.warranty.view',
        'product.view',
        'barcode.view',
    ]);

    $navigation = app(AdminNavigation::class)->build($user);
    $settings = collect($navigation)->firstWhere('title', 'Settings');

    expect($settings)->not->toBeNull()
        ->and(collect($settings['children'])->pluck('title')->all())->toBe([
            'Category',
            'Tag',
            'Brand',
            'Unit',
            'Color',
            'Size',
            'Warranty',
            'Product',
            'Barcode',
        ]);
});

test('website section groups all ecommerce frontend links', function () {
    $this->artisan('permissions:sync');

    EcommerceBranchService::resetResolvedId();

    $branch = Branch::query()->firstOrCreate(
        ['name' => EcommerceBranchService::BRANCH_NAME],
        Branch::factory()->make(['name' => EcommerceBranchService::BRANCH_NAME])->toArray(),
    );

    $user = User::factory()->create(['branch_id' => $branch->id]);
    $role = Role::create(['name' => 'Website Manager '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo([
        'online-order.view',
        'online-customer.view',
        'contact-list.view',
        'subscriber-list.view',
        'setting.slider.view',
        'setting.productsection.view',
        'setting.website.view',
        'setting.page-content.view',
        'setting.faq.view',
    ]);
    $user->assignRole($role);

    $navigation = app(AdminNavigation::class)->build($user);
    $website = collect($navigation)->firstWhere('title', 'Website Manage');

    expect($website)->not->toBeNull()
        ->and(collect($website['children'])->pluck('title')->all())->toContain(
            'Online Orders',
            'Online Customers',
            'Contact Messages',
            'Subscribers',
            'Slider',
            'Product Section',
            'Website Setting',
            'About Us',
            'FAQ',
        );
});

test('branch profile appears last for branch users', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('setting.branch-profile.view', 'web');
    $user->givePermissionTo('setting.branch-profile.view');

    $titles = collect(app(AdminNavigation::class)->build($user))->pluck('title')->toArray();

    expect($titles)->toContain('Branch Profile')
        ->and(end($titles))->toBe('Branch Profile');
});

test('branch users see coin settings only under sales not settings', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('setting.coin-settings.view', 'web');
    Permission::findOrCreate('inventory.sell.view', 'web');
    $user->givePermissionTo(['setting.coin-settings.view', 'inventory.sell.view']);

    $navigation = app(AdminNavigation::class)->build($user);

    $salesChildren = collect($navigation)->firstWhere('title', 'Sales')['children'] ?? [];
    $settingsChildren = collect($navigation)->firstWhere('title', 'Settings')['children'] ?? [];

    expect(collect($salesChildren)->pluck('title')->all())->toContain('Coin Settings')
        ->and(collect($settingsChildren)->pluck('title')->all())->not->toContain('Coin Settings');
});

test('branch users see pos terms only under sales not settings', function () {
    $this->artisan('permissions:sync');

    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    Permission::findOrCreate('setting.pos-terms.view', 'web');
    Permission::findOrCreate('inventory.sell.view', 'web');
    $user->givePermissionTo(['setting.pos-terms.view', 'inventory.sell.view']);

    $navigation = app(AdminNavigation::class)->build($user);

    $salesChildren = collect($navigation)->firstWhere('title', 'Sales')['children'] ?? [];
    $settingsChildren = collect($navigation)->firstWhere('title', 'Settings')['children'] ?? [];

    expect(collect($salesChildren)->pluck('title')->all())->toContain('POS Terms & Conditions')
        ->and(collect($settingsChildren)->pluck('title')->all())->not->toContain('POS Terms & Conditions');
});

test('authenticated admin dashboard shares central navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->has('adminNavigation')
            ->where('adminNavigation.0.title', 'Dashboard')
            ->where('adminNavigation', fn ($navigation) => collect($navigation)->pluck('title')->doesntContain('Settings')
                && collect($navigation)->pluck('title')->contains('Reports')
                && collect($navigation)->pluck('title')->contains('Branch')));
});

test('superadmin and main branch users have profit loss and balance sheet in navigation', function () {
    $superAdmin = User::factory()->create(['branch_id' => null]);
    $mainBranch = Branch::query()->find(Branch::MAIN_BRANCH_ID) ?? Branch::factory()->create(['id' => Branch::MAIN_BRANCH_ID]);
    $mainBranchUser = User::factory()->create(['branch_id' => $mainBranch->id]);

    $this->artisan('permissions:sync');
    $mainBranchUser->givePermissionTo([
        'report.profit-loss.view',
        'report.balance-sheet.view',
        'report.subscription-billing.view',
    ]);

    $superNav = app(AdminNavigation::class)->build($superAdmin);
    $mainNav = app(AdminNavigation::class)->build($mainBranchUser);

    $superReports = collect($superNav)->firstWhere('title', 'Reports');
    $mainReports = collect($mainNav)->firstWhere('title', 'Reports');

    expect($superReports)->not->toBeNull();
    expect(collect($superReports['children'])->pluck('title')->all())->toContain('Profit & Loss', 'Balance Sheet', 'Subscription Billing')
        ->and(collect($superReports['children'])->pluck('title')->all())->not->toContain(
            'Customer Ledger',
            'Date Wise Stock',
            'Stock Ledger',
            'Inventory Stock',
            'Opening Stock',
            'Stock Valuation',
            'Stock Aging',
            'Daily Summary',
            'Sales Summary',
            'Sales Report',
            'Sales Profit Trend',
            'Purchase Report',
        );
    expect(collect($superReports['children'])->firstWhere('title', 'Profit & Loss')['href'])->toBe('/report/profit-loss');
    expect(collect($superReports['children'])->firstWhere('title', 'Balance Sheet')['href'])->toBe('/report/balance-sheet');
    expect(collect($superReports['children'])->firstWhere('title', 'Subscription Billing')['href'])->toBe('/report/subscription-billing');

    expect($mainReports)->not->toBeNull();
    expect(collect($mainReports['children'])->pluck('title')->all())->toContain('Profit & Loss', 'Balance Sheet', 'Subscription Billing')
        ->and(collect($mainReports['children'])->pluck('title')->all())->not->toContain(
            'Customer Ledger',
            'Date Wise Stock',
            'Stock Ledger',
            'Inventory Stock',
            'Opening Stock',
            'Stock Valuation',
            'Stock Aging',
            'Daily Summary',
            'Sales Summary',
            'Sales Report',
            'Sales Profit Trend',
            'Purchase Report',
        );
    expect(collect($mainReports['children'])->firstWhere('title', 'Profit & Loss')['href'])->toBe('/report/profit-loss');
    expect(collect($mainReports['children'])->firstWhere('title', 'Balance Sheet')['href'])->toBe('/report/balance-sheet');
    expect(collect($mainReports['children'])->firstWhere('title', 'Subscription Billing')['href'])->toBe('/report/subscription-billing');
});
