<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasBranch
{
    public function scopeOwnBranch(Builder $query): Builder
    {
        if (Auth::user()->hasRole('admin')) {
            return $query;
        }

        return $query->where('branch_id', Auth::user()->branch_id);
    }
}
