<?php

namespace App\Models;

use App\Services\EcommerceBranchService;
use App\Support\AdminNavigation;
use App\Traits\HasBranch;
use App\Traits\UsesCentralConnection;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'branch_id', 'created_by_id', 'image', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    public const int SUPER_ADMIN_ID = 1;

    public const string ECOMMERCE_BRANCH_ADMIN_EMAIL = 'branchadmin@coolness.com';

    public const string ECOMMERCE_BRANCH_USER_EMAIL = 'ecommerce@coolness.com';

    public const string OPERATING_BRANCH_ADMIN_EMAIL = 'branchmanager@coolness.com';

    /** @use HasFactory<UserFactory> */
    use HasBranch, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable, UsesCentralConnection;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->branch_id === null || Branch::isMainBranch($this->branch_id);
    }

    public function bypassesPermissionChecks(): bool
    {
        return $this->id === self::SUPER_ADMIN_ID;
    }

    public function hasUnrestrictedPermissions(): bool
    {
        return $this->bypassesPermissionChecks() || $this->isSuperAdmin();
    }

    public function isProtectedFromPasswordReset(): bool
    {
        return $this->id === self::SUPER_ADMIN_ID
            || Branch::isMainBranch($this->branch_id);
    }

    public function isBranchUser(): bool
    {
        return $this->branch_id !== null;
    }

    public function usesAdminPanel(): bool
    {
        return $this->isSuperAdmin() || Branch::isMainBranch($this->branch_id);
    }

    public function usesBranchPanel(): bool
    {
        return $this->isBranchUser() && ! Branch::isMainBranch($this->branch_id);
    }

    public function canAccessEcommercePanel(): bool
    {
        if ($this->hasUnrestrictedPermissions()) {
            return true;
        }

        if ($this->usesBranchPanel() && EcommerceBranchService::isEcommerceBranchStatic($this->branch_id)) {
            return true;
        }

        return $this->hasAnyPermission(EcommerceBranchService::permissionNames());
    }

    public function ecommercePanelBranchId(): int
    {
        return EcommerceBranchService::resolveIdStatic();
    }

    public function isSystemEcommerceAdmin(): bool
    {
        return $this->email === self::ECOMMERCE_BRANCH_ADMIN_EMAIL;
    }

    public function scopeListedInUserManagement(Builder $query): Builder
    {
        return $query
            ->whereHas('branch', fn (Builder $branchQuery) => $branchQuery->assignableForUsers())
            ->where('email', '!=', self::ECOMMERCE_BRANCH_ADMIN_EMAIL);
    }

    public function scopeManagedInUserList(Builder $query): Builder
    {
        return $query->listedInUserManagement();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactionLogs(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    public function defaultLandingUrl(): string
    {
        if (! $this->can('dashboard.view')) {
            return $this->usesBranchPanel()
                ? route('branch-panel.dashboard')
                : route('dashboard');
        }

        $navigation = app(AdminNavigation::class)->build($this);

        foreach ($navigation as $section) {
            if (($section['single'] ?? false) && ! empty($section['href'])) {
                return $section['href'];
            }

            $firstChildHref = $section['children'][0]['href'] ?? null;

            if ($firstChildHref !== null) {
                return $firstChildHref;
            }
        }

        return $this->usesBranchPanel()
            ? route('branch-panel.dashboard')
            : route('dashboard');
    }

    public function defaultLandingPath(): string
    {
        return parse_url($this->defaultLandingUrl(), PHP_URL_PATH) ?: '/';
    }
}
