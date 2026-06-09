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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        $this->configureDefaults();
        $this->ensurePublicUploadDirectories();

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
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
