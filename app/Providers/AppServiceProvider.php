<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AdminNavigation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AdminNavigation::class);

        $this->loadMigrationsFrom(config('tenancy.central_migrations_path'));

        if (! config('tenancy.enabled')) {
            $this->loadMigrationsFrom(config('tenancy.tenant_migrations_path'));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        $this->configureDefaults();
        $this->ensurePublicUploadDirectories();
        $this->syncTenancyPreferenceFromSettings();

        if (config('tenancy.enabled')) {
            config(['database.default' => config('tenancy.central_connection')]);
        }

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasUnrestrictedPermissions()) {
                return true;
            }

            return null;
        });
    }

    /**
     * Prefer Business Setup multi-tenant flag when the settings table is available.
     * Env TENANCY_ENABLED remains the bootstrap default before first save.
     */
    protected function syncTenancyPreferenceFromSettings(): void
    {
        try {
            if (! Schema::hasTable('business_settings')) {
                return;
            }

            $stored = DB::table('business_settings')->where('key', 'multi_tenant_enabled')->value('value');

            if ($stored === null) {
                return;
            }

            config(['tenancy.enabled' => filter_var($stored, FILTER_VALIDATE_BOOLEAN)]);
        } catch (\Throwable) {
            // Settings DB may be unavailable during early install / migrate.
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // DB::prohibitDestructiveCommands(
        //     app()->isProduction(),
        // );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function ensurePublicUploadDirectories(): void
    {
        foreach (['categories', 'brands', 'tags', 'sliders', 'products', 'website'] as $directory) {
            $path = storage_path('app/public/'.$directory);

            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }

        $fontCache = storage_path('fonts');

        if (! is_dir($fontCache)) {
            mkdir($fontCache, 0775, true);
        }
    }
}
