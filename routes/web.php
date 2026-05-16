<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Customer\Auth\CustomerLoginController;
use App\Http\Controllers\Customer\Auth\CustomerRegisterController;
use App\Http\Controllers\Customer\CustomerDashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// ─── Admin ───────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::inertia('/', 'admin/dashboard')->name('dashboard');
    });
});

// ─── Frontend public ─────────────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/search', [HomeController::class, 'search'])->name('search');
Route::get('/product/{slug}', [HomeController::class, 'show'])->name('product.show');
Route::get('/collection/{name}', [HomeController::class, 'collectionProducts'])->name('collection.products');
Route::get('/category/{id}/products', [HomeController::class, 'categoryProducts'])->name('category.products');

// Static pages
Route::get('/about', [HomeController::class, 'staticPage'])->defaults('page', 'about')->name('about');
Route::get('/faq', [HomeController::class, 'staticPage'])->defaults('page', 'faq')->name('faq');
Route::get('/size-guide', [HomeController::class, 'staticPage'])->defaults('page', 'size-guide')->name('size-guide');
Route::get('/refund-policy', [HomeController::class, 'staticPage'])->defaults('page', 'refund-policy')->name('refund-policy');
Route::get('/cancellation-policy', [HomeController::class, 'staticPage'])->defaults('page', 'cancellation-policy')->name('cancellation-policy');
Route::get('/privacy-policy', [HomeController::class, 'staticPage'])->defaults('page', 'privacy-policy')->name('privacy-policy');
Route::get('/terms-policy', [HomeController::class, 'staticPage'])->defaults('page', 'terms-policy')->name('terms-policy');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'contactStore'])->name('contact.store');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/add', [CartController::class, 'addToCart'])->name('cart.add');
Route::patch('/cart/{cartKey}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartKey}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/order/{id}/success', [CheckoutController::class, 'success'])->name('order.success');

// ─── Customer auth ────────────────────────────────────────────────────────────
Route::prefix('customer')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [CustomerLoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [CustomerLoginController::class, 'login']);
        Route::get('register', [CustomerRegisterController::class, 'showRegisterForm'])->name('register');
        Route::post('register', [CustomerRegisterController::class, 'register']);
    });

    Route::post('logout', [CustomerLoginController::class, 'logout'])->name('logout');

    Route::middleware('auth:customer')->group(function () {
        Route::get('dashboard', [CustomerDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('orders', [CustomerDashboardController::class, 'orders'])->name('orders');
        Route::get('orders/{id}', [CustomerDashboardController::class, 'orderDetails'])->name('order.details');
    });
});

require __DIR__.'/settings.php';
