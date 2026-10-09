<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

RateLimiter::for('login', fn ($job) => Limit::perMinute(10)->by($job->ip()));
RateLimiter::for('register', fn ($job) => Limit::perHour(5)->by($job->ip()));
RateLimiter::for('concierge', fn ($job) => Limit::perHour(3)->by($job->ip()));
RateLimiter::for('newsletter', fn ($job) => Limit::perDay(5)->by($job->ip()));

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/prototype', fn () => response()->file(base_path('prototype/index.html')))->name('prototype');
Route::get('/produk/{product:product_slug}', [StorefrontController::class, 'show'])->name('products.show');
Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('sitemap');
Route::post('/concierge', [StorefrontController::class, 'bookConcierge'])
    ->middleware('throttle:concierge')
    ->name('concierge.store');
Route::post('/newsletter', [StorefrontController::class, 'subscribe'])
    ->middleware('throttle:newsletter')
    ->name('newsletter.store');

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
});

Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('admin.login.process');
});

Route::middleware('auth:admin')->group(function () {
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
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});
