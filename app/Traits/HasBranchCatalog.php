<?php

namespace App\Traits;

use App\Models\Branch;
use App\Services\EcommerceBranchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasBranchCatalog
{
    use HasBranch;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function siblingForBranch(int $branchId): ?static
    {
        if ((int) $this->branch_id === $branchId) {
            return $this;
        }

        if ($this->catalog_group_id === null) {
            return null;
        }

        return static::query()
            ->where('catalog_group_id', $this->catalog_group_id)
            ->where('branch_id', $branchId)
            ->first();
    }

    public function scopeForCatalogPanel(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null) {
            return $query->where('branch_id', $branchId);
        }

        return $query->where('branch_id', Branch::resolveAdminCatalogBranchId());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeForStorefront(Builder $query): Builder
    {
        return $query->accessibleAtBranch(EcommerceBranchService::resolveIdStatic());
    }
}
