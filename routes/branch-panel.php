<?php

use App\Http\Controllers\BranchPanel\BranchDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'branch.user'])
    ->prefix('branch-panel')
    ->name('branch-panel.')
    ->group(function () {
        Route::get('/', BranchDashboardController::class)->name('dashboard');
    });
