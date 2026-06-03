<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\ContraVoucherController;
use App\Http\Controllers\Account\ExpenseVoucherController;
use App\Http\Controllers\Account\IncomeVoucherController;
use App\Http\Controllers\Account\JournalVoucherController;
use App\Http\Controllers\Api\CustomerSearchController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\PurchaseLookupController;
use App\Http\Controllers\Api\SaleLookupController;
use App\Http\Controllers\Api\SupplierController as SupplierApiController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Inventory\DamageController;
use App\Http\Controllers\Inventory\ProductExchangeController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Inventory\PurchaseReturnController;
use App\Http\Controllers\Inventory\SaleReturnController;
use App\Http\Controllers\Inventory\SellController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Setting\BrandController;
use App\Http\Controllers\Setting\CategoryController;
use App\Http\Controllers\Setting\ColorController;
use App\Http\Controllers\Setting\ProductSectionController;
use App\Http\Controllers\Setting\SizeController;
use App\Http\Controllers\Setting\SliderController;
use App\Http\Controllers\Setting\TagController;
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

Route::middleware(['auth', 'verified'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::resource('purchase', PurchaseController::class);
    Route::resource('purchase-return', PurchaseReturnController::class);
    Route::resource('damage', DamageController::class);
    Route::resource('sell', SellController::class);
    Route::resource('sale-return', SaleReturnController::class);
    Route::resource('product-exchange', ProductExchangeController::class);
});

Route::middleware(['auth', 'verified'])->prefix('party')->name('party.')->group(function () {
    Route::resource('supplier', SupplierController::class)->except(['create', 'edit', 'show']);
    Route::resource('customer', CustomerController::class)->except(['create', 'edit', 'show']);
});

Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function () {
    Route::get('suppliers', [SupplierApiController::class, 'index'])->name('suppliers');
    Route::post('suppliers', [SupplierApiController::class, 'store'])->name('suppliers.store');
    Route::get('products/for-purchase', [ProductSearchController::class, 'forPurchase'])->name('products.purchase');
    Route::get('products/for-sell', [ProductSearchController::class, 'forSell'])->name('products.sell');
    Route::get('purchases/lookup', PurchaseLookupController::class)->name('purchases.lookup');
    Route::get('sales/lookup', SaleLookupController::class)->name('sales.lookup');
    Route::get('customers', [CustomerSearchController::class, 'index'])->name('customers');
    Route::post('customers', [CustomerSearchController::class, 'store'])->name('customers.store');
});

Route::middleware(['auth', 'verified'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::post('/', [AccountController::class, 'store'])->name('store');
    Route::get('next-code', [AccountController::class, 'nextCode'])->name('next-code');
    Route::patch('{chartOfAccount}', [AccountController::class, 'update'])->name('update');
    Route::delete('{chartOfAccount}', [AccountController::class, 'destroy'])->name('destroy');
    Route::get('journal-voucher', [JournalVoucherController::class, 'index'])->name('journal-voucher.index');
    Route::get('contra-voucher', [ContraVoucherController::class, 'index'])->name('contra-voucher.index');
    Route::get('income-voucher', [IncomeVoucherController::class, 'index'])->name('income-voucher.index');
    Route::get('expense-voucher', [ExpenseVoucherController::class, 'index'])->name('expense-voucher.index');
});

Route::middleware(['auth', 'verified'])->prefix('setting')->name('setting.')->group(function () {
    Route::resource('category', CategoryController::class)->except(['create', 'edit']);
    Route::resource('tag', TagController::class)->except(['create', 'edit']);
    Route::resource('brand', BrandController::class)->except(['create', 'edit']);
    Route::resource('unit', UnitController::class)->except(['create', 'edit']);
    Route::resource('size', SizeController::class)->except(['create', 'edit']);
    Route::resource('color', ColorController::class)->except(['create', 'edit']);
    Route::resource('warranty', WarrantyController::class)->except(['create', 'edit']);
    Route::resource('slider', SliderController::class)->except(['create', 'edit', 'show']);
    Route::resource('productsection', ProductSectionController::class)->except(['create', 'edit', 'show']);
    Route::post('productsection/update-order', [ProductSectionController::class, 'updateOrder'])
        ->name('productsection.update-order');
});
