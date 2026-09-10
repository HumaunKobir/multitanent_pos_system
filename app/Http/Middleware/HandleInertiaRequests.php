<?php

namespace App\Http\Middleware;

use App\Enums\CommonStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ConfigDictionary;
use App\Models\Tag;
use App\Models\User;
use App\Services\BranchSubscriptionService;
use App\Services\BusinessSessionService;
use App\Support\AdminNavigation;
use App\Support\BusinessSettings;
use App\Support\StorageUrl;
use App\Support\WebsiteSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cart = $request->session()->get('cart', []);

        $user = $request->user('web');
        $customer = $request->user('customer');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'systemName' => BusinessSettings::systemName(),
            'multiTenantEnabled' => BusinessSettings::getBool('multi_tenant_enabled', (bool) config('tenancy.enabled')),
            'auth' => [
                'user' => $user instanceof User ? $user->loadMissing('branch') : null,
                'customer' => $customer,
                'permissions' => $user instanceof User
                    ? ($user->hasUnrestrictedPermissions()
                        ? ['*']
                        : $user->getAllPermissions()->pluck('name')->values()->all())
                    : [],
            ],
            'adminNavigation' => $user instanceof User
                ? app(AdminNavigation::class)->build($user)
                : [],
            'panelType' => $user instanceof User && $user->usesBranchPanel() ? 'branch' : 'admin',
            'businessSession' => $user instanceof User
                ? app(BusinessSessionService::class)->sharedPanelState($user)
                : ['active' => false, 'can_start' => false, 'can_close' => false, 'can_resume_close' => false],
            'branchSubscription' => $user instanceof User && $user->branch !== null
                ? app(BranchSubscriptionService::class)->getSubscriptionSummary($user->branch)
                : null,
            'businessPolicies' => [
                'allow_negative_stock' => BusinessSettings::getBool('policy_allow_negative_stock', false),
                'max_discount_percent' => BusinessSettings::getFloat('policy_max_discount_percent', 50),
                'require_daily_business_session' => BusinessSettings::getBool('policy_require_daily_business_session', true),
                'require_customer_phone' => BusinessSettings::getBool('policy_require_customer_phone', false),
                'feature_ecommerce_enabled' => BusinessSettings::getBool('feature_ecommerce_enabled', true),
                'feature_loyalty_coins_enabled' => BusinessSettings::getBool('feature_loyalty_coins_enabled', true),
                'feature_accounts_vouchers_enabled' => BusinessSettings::getBool('feature_accounts_vouchers_enabled', true),
                'feature_stock_distribution_enabled' => BusinessSettings::getBool('feature_stock_distribution_enabled', true),
                'feature_damage_tracking_enabled' => BusinessSettings::getBool('feature_damage_tracking_enabled', true),
                'feature_special_discounts_enabled' => BusinessSettings::getBool('feature_special_discounts_enabled', true),
                'superadmin_contact' => [
                    'name' => BusinessSettings::get('superadmin_contact_name'),
                    'phone' => BusinessSettings::get('superadmin_contact_phone'),
                    'email' => BusinessSettings::get('superadmin_contact_email'),
                ],
            ],
            'hasPanelGuide' => $user instanceof User
                && count(app(AdminNavigation::class)->build($user)) > 0,
            'showPanelGuideButton' => $user instanceof User
                ? $this->shouldShowPanelGuideButton($request, $user)
                : false,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cart' => $cart,
            'cartCount' => count($cart),
            'categories' => Category::forStorefront()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'image'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'image' => StorageUrl::public($c->image),
                ]),
            'brands' => Brand::forStorefront()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn ($brand) => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ]),
            'tags' => Tag::forStorefront()
                ->where('status', CommonStatus::Active)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
            'logo' => StorageUrl::public(ConfigDictionary::get('logo')),
            'siteName' => WebsiteSettings::get('website_name', config('app.name')),
            'topNotice' => WebsiteSettings::get('topnotice1'),
            'footerDescription' => WebsiteSettings::get('footer_description'),
            'supportTime' => WebsiteSettings::get('support_time'),
            'contact' => [
                'phone' => WebsiteSettings::get('phone'),
                'email' => WebsiteSettings::get('email'),
                'address' => WebsiteSettings::get('address'),
            ],
            'social' => [
                'facebook' => WebsiteSettings::get('fb_share_for_withdraw'),
                'youtube' => WebsiteSettings::get('youtube'),
                'twitter' => WebsiteSettings::get('twit'),
                'linkedin' => WebsiteSettings::get('linkend'),
            ],
            'deliveryCharges' => [
                'inside_dhaka' => (float) WebsiteSettings::get('delivery_charge_inside_dhaka', '60'),
                'outside_dhaka' => (float) WebsiteSettings::get('delivery_charge_outside_dhaka', '120'),
            ],
            'newsletter' => [
                'enabled' => filter_var(WebsiteSettings::get('newsletter_enabled', '1'), FILTER_VALIDATE_BOOLEAN),
                'title' => WebsiteSettings::get('newsletter_title', 'Sign Up For Newsletter'),
                'description' => WebsiteSettings::get('newsletter_description', ''),
                'placeholder' => WebsiteSettings::get('newsletter_placeholder', 'Your Email Address...'),
                'button' => WebsiteSettings::get('newsletter_button', 'Subscribe'),
            ],
            'seo' => [
                'meta_tags' => WebsiteSettings::get('meta_tags'),
                'meta_description' => WebsiteSettings::get('meta_description'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'pos_change' => $request->session()->get('pos_change'),
            ],
        ];
    }

    protected function shouldShowPanelGuideButton(Request $request, User $user): bool
    {
        $path = trim($request->path(), '/');

        if ($path === 'panel-guide') {
            return false;
        }

        return in_array($path, ['dashboard', 'branch-panel'], true);
    }
}
