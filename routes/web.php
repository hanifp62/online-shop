<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminTotpController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StorefrontController;
use App\Http\Middleware\EnsureAdminTotp;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('login', fn ($job) => Limit::perMinute(10)->by($job->ip()));
RateLimiter::for('register', fn ($job) => Limit::perHour(5)->by($job->ip()));
RateLimiter::for('concierge', fn ($job) => Limit::perHour(3)->by($job->ip()));
RateLimiter::for('newsletter', fn ($job) => Limit::perDay(5)->by($job->ip()));
RateLimiter::for('checkout', fn ($job) => Limit::perMinute(10)->by($job->user()?->id ?: $job->ip()));

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/koleksi', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/belanja', [ShopController::class, 'index'])->name('shop.index');
Route::get('/prototype', fn () => redirect('/prototype/'))->name('prototype');
Route::get('/produk/{product:product_slug}', [StorefrontController::class, 'show'])->name('products.show');
Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('sitemap');
Route::post('/concierge', [StorefrontController::class, 'bookConcierge'])
    ->middleware('throttle:concierge')
    ->name('concierge.store');
Route::post('/newsletter', [StorefrontController::class, 'subscribe'])
    ->middleware('throttle:newsletter')
    ->name('newsletter.store');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::delete('/cart/items/{productId}', [CartController::class, 'destroy'])->whereNumber('productId')->name('cart.items.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.process');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/akun', [ProfileController::class, 'show'])->name('account.show');
    Route::patch('/akun', [ProfileController::class, 'update'])->name('account.update');

    Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/addresses/{id}', [AddressController::class, 'update'])->whereNumber('id')->name('addresses.update');
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->whereNumber('id')->name('addresses.destroy');

    Route::post('/checkout', [OrderController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id')->name('orders.cancel');

    Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{productId}', [FavoriteController::class, 'destroy'])->whereNumber('productId')->name('favorites.destroy');
});

Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('admin.login.process');
});

// Admin sudah login tetapi belum lolos faktor kedua: hanya boleh verifikasi atau keluar.
Route::middleware('auth:admin')->group(function () {
    Route::get('/admin/totp', [AdminTotpController::class, 'show'])->name('admin.totp.challenge');
    Route::post('/admin/totp', [AdminTotpController::class, 'verify'])->name('admin.totp.verify');
    Route::post('/admin/totp/recovery', [AdminTotpController::class, 'recovery'])->name('admin.totp.recovery');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

Route::middleware(['auth:admin', EnsureAdminTotp::class])->group(function () {
    Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::patch('/admin/concierge/{concierge}', [AdminDashboardController::class, 'updateConcierge'])->name('admin.concierge.update');
    Route::patch('/admin/password', [AdminDashboardController::class, 'updatePassword'])->name('admin.password.update');
    Route::post('/admin/categories', [AdminCategoryController::class, 'store'])->name('admin.categories.store');
    Route::patch('/admin/categories/{category:cat_id}', [AdminCategoryController::class, 'update'])->name('admin.categories.update');
    Route::delete('/admin/categories/{category:cat_id}', [AdminCategoryController::class, 'destroy'])->name('admin.categories.destroy');
    Route::patch('/admin/products/{product:product_id}', [AdminProductController::class, 'update'])->name('admin.products.update');
    Route::post('/admin/products', [AdminProductController::class, 'store'])->name('admin.products.store');
    Route::delete('/admin/products/{product:product_id}', [AdminProductController::class, 'destroy'])->name('admin.products.destroy');
    Route::delete('/admin/subscribers/{subscriber}', [AdminDashboardController::class, 'destroySubscriber'])->name('admin.subscribers.destroy');
    Route::get('/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/admin/orders/{id}', [AdminOrderController::class, 'show'])->whereNumber('id')->name('admin.orders.show');
    Route::patch('/admin/orders/{id}', [AdminOrderController::class, 'updateStatus'])->whereNumber('id')->name('admin.orders.update');
    Route::get('/admin/orders/{id}/invoice', [AdminOrderController::class, 'invoice'])->whereNumber('id')->name('admin.orders.invoice');
    Route::get('/admin/reports/orders.pdf', [AdminOrderController::class, 'report'])->name('admin.orders.report');
});
