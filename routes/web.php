<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Customer\Auth\CustomerLoginController;
use App\Http\Controllers\Customer\Auth\CustomerRegisterController;
use App\Http\Controllers\Customer\CustomerDashboardController;
use App\Http\Controllers\Customer\CustomerProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PathaoCourierController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// ── PUBLIC FRONTEND ───────────────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [HomeController::class, 'allProducts'])->name('products.index');
Route::get('/products/{product:slug}', [HomeController::class, 'show'])->name('product.show');
Route::post('/products/{product:slug}/reviews', [ProductReviewController::class, 'store'])->name('product.reviews.store');
Route::get('/category/{category}/products', [HomeController::class, 'categoryProducts'])->name('category.products');
Route::get('/brand/{brand}/products', [HomeController::class, 'brandProducts'])->name('brand.products');
Route::get('/section/{id}/products', [HomeController::class, 'sectionProducts'])->name('section.products');
Route::get('/collection/{name}', [HomeController::class, 'collectionProducts'])->name('collection.products')->where('name', '.*');
Route::get('/search', [HomeController::class, 'search'])->name('search');
Route::get('/search/suggestions', [HomeController::class, 'searchSuggestions'])->name('search.suggestions');

// Static pages
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/faq', [HomeController::class, 'staticPage'])->defaults('page', 'faq')->name('faq');
Route::get('/size-guide', [HomeController::class, 'staticPage'])->defaults('page', 'size-guide')->name('size-guide');
Route::get('/refund-policy', [HomeController::class, 'staticPage'])->defaults('page', 'refund-policy')->name('refund-policy');
Route::get('/cancellation-policy', [HomeController::class, 'staticPage'])->defaults('page', 'cancellation-policy')->name('cancellation-policy');
Route::get('/privacy-policy', [HomeController::class, 'staticPage'])->defaults('page', 'privacy-policy')->name('privacy-policy');
Route::get('/terms-policy', [HomeController::class, 'staticPage'])->defaults('page', 'terms-policy')->name('terms-policy');
Route::get('/contact', [HomeController::class, 'contact'])->name('contact');
Route::post('/contact', [HomeController::class, 'contactStore'])->name('contact.store');
Route::post('/subscribe', [SubscriptionController::class, 'store'])->name('subscribe.store');

// Cart
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::get('/cart/json', [CartController::class, 'json'])->name('cart.json');
Route::post('/cart/add', [CartController::class, 'addToCart'])->name('cart.add');
Route::patch('/cart/{cartKey}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{cartKey}', [CartController::class, 'remove'])->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');

// Checkout
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])->name('checkout.success');

// Pathao API stubs (Phase 12 — wire to Pathao package when available)
Route::get('/pathao/cities', [PathaoCourierController::class, 'getCities'])->name('pathao.cities');
Route::get('/pathao/zones/{cityId}', [PathaoCourierController::class, 'getZones'])->name('pathao.zones');
Route::get('/pathao/areas/{zoneId}', [PathaoCourierController::class, 'getAreas'])->name('pathao.areas');

// ── CUSTOMER AUTH ─────────────────────────────────────────────────────────────
Route::prefix('customer')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [CustomerLoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [CustomerLoginController::class, 'login']);
        Route::get('register', [CustomerRegisterController::class, 'showRegisterForm'])->name('register');
        Route::post('register', [CustomerRegisterController::class, 'register']);
    });

    Route::middleware('auth:customer')->group(function () {
        Route::post('logout', [CustomerLoginController::class, 'logout'])->name('logout');
        Route::get('profile', [CustomerDashboardController::class, 'dashboard'])->name('profile');
        Route::get('dashboard', [CustomerDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('orders', [CustomerDashboardController::class, 'orders'])->name('orders');
        Route::get('orders/{id}', [CustomerDashboardController::class, 'orderDetails'])->name('order.details');
        Route::get('settings', [CustomerProfileController::class, 'edit'])->name('settings');
        Route::patch('settings', [CustomerProfileController::class, 'update'])->name('settings.update');
    });
});

Route::middleware(['auth', 'verified', 'superadmin'])
    ->get('/dashboard', AdminDashboardController::class)
    ->name('dashboard');
