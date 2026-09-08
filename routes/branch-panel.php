<?php

use App\Http\Controllers\BranchPanel\BranchDashboardController;
use App\Http\Controllers\BranchPanel\SubscriptionSuspendedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'branch.user'])
    ->prefix('branch-panel')
    ->name('branch-panel.')
    ->group(function () {
        Route::get('/', BranchDashboardController::class)->name('dashboard');
        Route::get('/subscription-suspended', SubscriptionSuspendedController::class)->name('subscription-suspended');
    });
