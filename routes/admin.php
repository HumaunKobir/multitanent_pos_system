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
    Route::resource('category', CategoryController::class);
    Route::resource('brand', BrandController::class);
    Route::resource('unit', UnitController::class);
    Route::resource('size', SizeController::class);
    Route::resource('color', ColorController::class);
    Route::resource('tailormeasurement', TailorMeasurementController::class);
    Route::resource('warranty', WarrantyController::class);
});
