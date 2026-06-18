<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuthorizesBranchUserRecords
{
    protected function authorizeBranchUserRecord(Model $record): void
    {
        $user = Auth::user();

        if ($user?->isSuperAdmin()) {
            return;
        }

        if (! method_exists($record, 'isAccessibleByCurrentUser') || ! $record->isAccessibleByCurrentUser()) {
            abort(404);
        }
    }

    protected function forCurrentBranchUser(Builder $query): Builder
    {
        if (! method_exists($query->getModel(), 'scopeOwnBranchUser')) {
            return $query;
        }

        return $query->ownBranchUser();
    }

    protected function currentUserId(): int
    {
        return (int) Auth::id();
    }
}
