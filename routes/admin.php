<?php

use App\Http\Controllers\Setting\BrandController;
use App\Http\Controllers\Setting\CategoryController;
use App\Http\Controllers\Setting\ColorController;
use App\Http\Controllers\Setting\SizeController;
use App\Http\Controllers\Setting\TailorMeasurementController;
use App\Http\Controllers\Setting\UnitController;
use App\Http\Controllers\Setting\WarrantyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::inertia('/', 'admin/dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified'])->prefix('setting')->name('setting.')->group(function () {
    Route::resource('category', CategoryController::class)->except(['create', 'edit']);
    Route::resource('brand', BrandController::class)->except(['create', 'edit']);
    Route::resource('unit', UnitController::class)->except(['create', 'edit']);
    Route::resource('size', SizeController::class)->except(['create', 'edit']);
    Route::resource('color', ColorController::class)->except(['create', 'edit']);
    Route::resource('tailormeasurement', TailorMeasurementController::class)->except(['create', 'edit']);
    Route::resource('warranty', WarrantyController::class)->except(['create', 'edit']);
});
