<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminTotpController;
use App\Http\Controllers\AdminTotpEnrolController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GuestCheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StorefrontController;
use App\Http\Middleware\EnsureAdminTotp;
use App\Http\Middleware\NoStore;
use Illuminate\Cache\RateLimiting\Limit;
use App\Http\Controllers\AdminAuthEventController;
use App\Services\AuthEventRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Dua batas terpisah: per akun (lintas IP) DAN per IP (lintas akun).
// Kunci menyertakan path agar panel admin dan storefront tidak saling mengunci.
RateLimiter::for('login', function ($request) {
    $respond = fn (string $key, string $kind) => function ($request, array $headers) use ($key, $kind) {
        $retryAfter = (int) ($headers['Retry-After'] ?? 60);
        // One LOCKOUT_TRIGGERED per limiter episode; a cache failure must never break the 429 itself.
        try {
            if (Cache::add('auth-lockout:'.hash('sha256', $key), 1, max(1, $retryAfter))) {
                $guard = $request->segment(1) === 'admin' ? 'admin' : 'web';
                app(AuthEventRecorder::class)->recordLockout($guard, null, $request->ip(), $kind);
            }
        } catch (\Throwable $e) {
            try {
                // Warning, not critical: unauthenticated traffic can reach this path repeatedly.
                Log::warning('Auth lockout dedup failed', ['exception' => $e::class]);
            } catch (\Throwable) {
                // The 429 response must survive audit and logging failures.
            }
        }

        return response('Terlalu banyak percobaan masuk. Coba lagi dalam '.$retryAfter.' detik.', 429, $headers);
    };
    $scope = $request->path();
    $email = $request->input('email');
    $account = is_string($email) ? Str::lower(trim($email)) : '';

    $ipKey = $scope.'|ip|'.$request->ip();
    $limits = [
        Limit::perMinute(10)->by($ipKey)->response($respond($ipKey, 'ip')),
    ];

    if ($account !== '') {
        $accountKey = $scope.'|account|'.$account;
        array_unshift($limits, Limit::perMinute(10)->by($accountKey)->response($respond($accountKey, 'account')));
    }

    return $limits;
});
RateLimiter::for('register', fn ($job) => Limit::perHour(5)->by($job->ip()));
RateLimiter::for('concierge', fn ($job) => Limit::perHour(3)->by($job->ip()));
RateLimiter::for('newsletter', fn ($job) => Limit::perDay(5)->by($job->ip()));
RateLimiter::for('checkout', fn ($job) => Limit::perMinute(10)->by($job->user()?->id ?: $job->ip()));
// Throttle aborts before the NoStore middleware can post-process, so set it here too.
RateLimiter::for('cart', fn ($request) => Limit::perMinute(60)->by('cart|'.$request->ip())
    ->response(fn ($request, array $headers) => response()->json(
        ['message' => 'Terlalu banyak permintaan tas belanja.'],
        429,
        $headers + ['Cache-Control' => 'no-store'],
    )));
RateLimiter::for('track', function ($request) {
    // Throttle aborts before the NoStore middleware can post-process, so set it here too.
    $respond = fn ($request, array $headers) => response()->json(['message' => 'Terlalu banyak percobaan pelacakan.'], 429, $headers + ['Cache-Control' => 'no-store']);
    $token = $request->isJson() ? $request->json('token') : $request->request->get('token');
    $limits = [Limit::perMinute(10)->by('track|ip|'.$request->ip())->response($respond)];
    if (is_string($token) && $token !== '') {
        $limits[] = Limit::perMinute(10)->by('track|token|'.hash('sha256', $token))->response($respond);
    }

    return $limits;
});

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

Route::get('/cart', [CartController::class, 'show'])->middleware([NoStore::class, 'throttle:cart'])->block(10, 10)->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->middleware([NoStore::class, 'throttle:cart'])->block(10, 10)->name('cart.items.store');
Route::post('/cart/items/{productId}/delta', [CartController::class, 'adjust'])->whereNumber('productId')->middleware([NoStore::class, 'throttle:cart'])->block(10, 10)->name('cart.items.adjust');
Route::delete('/cart/items/{productId}', [CartController::class, 'destroy'])->whereNumber('productId')->middleware([NoStore::class, 'throttle:cart'])->block(10, 10)->name('cart.items.destroy');
Route::delete('/cart', [CartController::class, 'clear'])->middleware([NoStore::class, 'throttle:cart'])->block(10, 10)->name('cart.clear');
Route::get('/checkout', fn () => redirect('/belanja'));
Route::post('/checkout', [OrderController::class, 'store'])->middleware([NoStore::class, 'throttle:checkout'])->block(10, 10)->name('checkout.store');
Route::get('/orders/track', fn () => redirect('/belanja'))->name('orders.track.form');
Route::post('/orders/track', [GuestCheckoutController::class, 'track'])->middleware([NoStore::class, 'throttle:track'])->name('orders.track');
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
Route::middleware([NoStore::class, 'auth:admin'])->group(function () {
    Route::get('/admin/totp', [AdminTotpController::class, 'show'])->name('admin.totp.challenge');
    Route::post('/admin/totp', [AdminTotpController::class, 'verify'])->name('admin.totp.verify');
    Route::post('/admin/totp/recovery', [AdminTotpController::class, 'recovery'])->name('admin.totp.recovery');
    // Enrolment: deliberately outside EnsureAdminTotp so an admin without a secret can still reach it.
    Route::get('/admin/totp/enrol', [AdminTotpEnrolController::class, 'show'])->name('admin.totp.enrol');
    Route::post('/admin/totp/enrol', [AdminTotpEnrolController::class, 'store'])->name('admin.totp.enrol.store');
    Route::get('/admin/totp/enrol/recovery', [AdminTotpEnrolController::class, 'recovery'])->name('admin.totp.enrol.recovery');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

Route::middleware([NoStore::class, 'auth:admin', EnsureAdminTotp::class])->group(function () {
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
    Route::get('/admin/auth-events', [AdminAuthEventController::class, 'index'])->name('admin.auth-events.index');
});
