<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Setting\BrandController;
use App\Http\Controllers\Setting\CategoryController;
use App\Http\Controllers\Setting\ColorController;
use App\Http\Controllers\Setting\SizeController;
use App\Http\Controllers\Setting\TailorMeasurementController;
use App\Http\Controllers\Setting\UnitController;
use App\Http\Controllers\Setting\WarrantyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::inertia('/', 'admin/dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'superadmin'])->group(function () {
    Route::resource('branch', BranchController::class)->except(['create', 'edit', 'show']);
    Route::resource('user', UserController::class)->except(['create', 'edit', 'show']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('product', ProductController::class)->except(['show']);
    Route::post('variation', [VariationController::class, 'store'])->name('variation.store');
    Route::delete('variation/{variation}', [VariationController::class, 'destroy'])->name('variation.destroy');
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
