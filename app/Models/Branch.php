<?php

namespace App\Models;

use App\Enums\CommonStatus;
use App\Services\EcommerceBranchService;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    public const int MAIN_BRANCH_ID = 1;

    public const string ECOMMERCE_BRANCH_NAME = 'Ecommerce Branch';

    protected $fillable = ['name', 'phone', 'address', 'status'];

    protected $casts = [
        'status' => CommonStatus::class,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CommonStatus::Active);
    }

    public function scopeOperating(Builder $query): Builder
    {
        return $query->where('id', '!=', self::MAIN_BRANCH_ID);
    }

    public function scopeAssignableForUsers(Builder $query): Builder
    {
        $ecommerceBranchId = EcommerceBranchService::resolveIdStatic();

        return $query->where(function (Builder $query) use ($ecommerceBranchId): void {
            $query->where('id', '!=', self::MAIN_BRANCH_ID)
                ->orWhere('id', $ecommerceBranchId);
        });
    }

    public function scopeAvailableForUserAssignment(Builder $query): Builder
    {
        return $query
            ->active()
            ->assignableForUsers();
    }

    public static function isMainBranch(?int $branchId): bool
    {
        return $branchId === self::MAIN_BRANCH_ID;
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
}
