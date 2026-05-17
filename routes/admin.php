<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::inertia('/', 'admin/dashboard')->name('dashboard');
});
