<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'branch.user'])
    ->prefix('branch-panel')
    ->name('branch-panel.')
    ->group(function () {
        Route::inertia('/', 'branch-panel/dashboard')->name('dashboard');
    });
