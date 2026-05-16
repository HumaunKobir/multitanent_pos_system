<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');
        Route::inertia('ui-showcase', 'admin/ui-showcase')->name('ui-showcase');
    });
});

require __DIR__.'/settings.php';
