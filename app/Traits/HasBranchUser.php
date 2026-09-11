<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasBranchUser
{
    use HasBranch;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnBranchUser(Builder $query): Builder
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        if ($user?->branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('branch_id', $user->branch_id);
    }

    public function isAccessibleByCurrentUser(): bool
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return true;
        }

        if ($user?->branch_id === null) {
            return false;
        }

        return (int) $this->branch_id === (int) $user->branch_id;
    }
}
