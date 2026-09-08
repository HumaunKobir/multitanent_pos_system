<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Services\BranchSubscriptionService;
use App\Services\DefaultCustomerService;
use App\Services\EcommerceBranchService;
use App\Traits\UsesCentralConnection;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, UsesCentralConnection;

    public const int MAIN_BRANCH_ID = 1;

    public const string MAIN_BRANCH_NAME = 'Main Branch';

    public const string ECOMMERCE_BRANCH_NAME = 'Ecommerce Branch';

    protected $fillable = [
        'name',
        'database_name',
        'phone',
        'address',
        'logo',
        'pos_terms_and_conditions',
        'status',
        'subscription_plan',
        'subscription_status',
        'subscription_fee',
        'subscription_starts_at',
        'subscription_expires_at',
        'subscription_last_paid_at',
        'custom_grace_period_days',
        'custom_warning_days',
        'custom_overdue_action',
        'subscription_notes',
    ];

    public static function hasPosTerms(?string $content): bool
    {
        if ($content === null || $content === '') {
            return false;
        }

        return trim(strip_tags($content)) !== '';
    }

    protected $casts = [
        'status' => CommonStatus::class,
        'subscription_fee' => 'decimal:2',
        'subscription_starts_at' => 'date',
        'subscription_expires_at' => 'date',
        'subscription_last_paid_at' => 'date',
        'custom_grace_period_days' => 'integer',
        'custom_warning_days' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (Branch $branch): void {
            app(BranchSubscriptionService::class)->provisionDefaultSubscription($branch);

            if (config('tenancy.enabled')) {
                return;
            }

            DefaultCustomerService::seed($branch);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CommonStatus::Active);
    }

    public function scopeOperating(Builder $query): Builder
    {
        return $query->where('id', '!=', self::resolveMainBranchId());
    }

    public function scopeAssignableForUsers(Builder $query): Builder
    {
        return $query;
    }

    public function scopeAvailableForUserAssignment(Builder $query): Builder
    {
        return $query
            ->active()
            ->assignableForUsers();
    }

    public static function isMainBranch(?int $branchId): bool
    {
        if ($branchId === null) {
            return false;
        }

        return $branchId === self::resolveMainBranchId();
    }

    public static function resolveMainBranchId(): int
    {
        if (static::query()->whereKey(self::MAIN_BRANCH_ID)->exists()) {
            return self::MAIN_BRANCH_ID;
        }

        $byName = static::query()
            ->where('name', self::MAIN_BRANCH_NAME)
            ->value('id');

        if ($byName !== null) {
            return (int) $byName;
        }

        return self::MAIN_BRANCH_ID;
    }

    /**
     * Superadmin settings catalog branch. Isolated from the ecommerce branch panel.
     */
    public static function resolveAdminCatalogBranchId(): int
    {
        $mainBranchId = static::query()
            ->where('name', self::MAIN_BRANCH_NAME)
            ->value('id');

        if ($mainBranchId !== null) {
            return (int) $mainBranchId;
        }

        $ecommerceBranchId = EcommerceBranchService::resolveIdStatic();

        $nonEcommerceBranchId = static::query()
            ->active()
            ->where('id', '!=', $ecommerceBranchId)
            ->orderBy('id')
            ->value('id');

        if ($nonEcommerceBranchId !== null) {
            return (int) $nonEcommerceBranchId;
        }

        return (int) $ecommerceBranchId;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(BranchSubscriptionPayment::class)->latest('paid_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionSummary(): array
    {
        return app(BranchSubscriptionService::class)->getSubscriptionSummary($this);
    }
}
