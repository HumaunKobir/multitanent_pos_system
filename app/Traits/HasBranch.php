<?php

namespace App\Traits;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasBranch
{
    public function scopeOwnBranch(Builder $query): Builder
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            return $query;
        }

        return $query->accessibleAtBranch($branchId);
    }

    public function scopeAccessibleAtBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function isAccessibleAtBranch(?int $branchId): bool
    {
        if ($branchId === null) {
            return true;
        }

        if ($this->branch_id === null) {
            return true;
        }

        return (int) $this->branch_id === (int) $branchId
            || (int) $this->branch_id === (int) Branch::resolveAdminCatalogBranchId();
    }
}
