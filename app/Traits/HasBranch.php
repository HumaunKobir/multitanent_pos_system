<?php

namespace App\Traits;

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
}
