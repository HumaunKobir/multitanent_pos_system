<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\ConfigDictionary;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\StorageUrl;
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
            'auth' => [
                'user' => $user instanceof User ? $user : null,
                'customer' => $customer,
                'permissions' => $user instanceof User
                    ? ($user->isSuperAdmin()
                        ? ['*']
                        : $user->getAllPermissions()->pluck('name')->values()->all())
                    : [],
            ],
            'adminNavigation' => $user instanceof User
                ? app(AdminNavigation::class)->build($user)
                : [],
            'panelType' => $user instanceof User && $user->isBranchUser() ? 'branch' : 'admin',
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'cart' => $cart,
            'cartCount' => collect($cart)->sum('quantity'),
            'categories' => Category::active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'image'])
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'image' => StorageUrl::public($c->image),
                ]),
            'logo' => StorageUrl::public(ConfigDictionary::get('logo')),
            'siteName' => ConfigDictionary::get('website_name', config('app.name')),
            'topNotice' => ConfigDictionary::get('topnotice1'),
            'contact' => [
                'phone' => ConfigDictionary::get('phone'),
                'email' => ConfigDictionary::get('email'),
                'address' => ConfigDictionary::get('address'),
            ],
            'social' => [
                'facebook' => ConfigDictionary::get('fb_share_for_withdraw'),
                'youtube' => ConfigDictionary::get('youtube'),
                'twitter' => ConfigDictionary::get('twit'),
                'linkedin' => ConfigDictionary::get('linkend'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
