<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminAuthEventController;
use App\Http\Controllers\AdminCategoryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminOperationsSummaryController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminTotpController;
use App\Http\Controllers\AdminTotpEnrolController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerTotpController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GuestCheckoutController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\StorefrontController;
use App\Http\Middleware\EnsureAdminTotp;
use App\Http\Middleware\NoStore;
use App\Services\AuthEventRecorder;
use Illuminate\Cache\RateLimiting\Limit;
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
        } catch (Throwable $e) {
            try {
                // Warning, not critical: unauthenticated traffic can reach this path repeatedly.
                Log::warning('Auth lockout dedup failed', ['exception' => $e::class]);
            } catch (Throwable) {
                // The 429 response must survive audit and logging failures.
            }
        }

        return response(__('customer_messages.auth.rate_limited', ['seconds' => $retryAfter]), 429, $headers);
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
RateLimiter::for('password-reset', function ($request) {
    $limits = [Limit::perHour(20)->by('ip:'.$request->ip())];
    $email = $request->input('email');
    if (is_string($email) && trim($email) !== '') {
        $limits[] = Limit::perHour(5)->by('email:'.hash('sha256', mb_strtolower(trim($email))));
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
        ['message' => __('customer_messages.cart.rate_limited')],
        429,
        $headers + ['Cache-Control' => 'no-store'],
    )));
RateLimiter::for('track', function ($request) {
    // Throttle aborts before the NoStore middleware can post-process, so set it here too.
    $respond = fn ($request, array $headers) => response()->json(['message' => __('customer_messages.guest.rate_limited')], 429, $headers + ['Cache-Control' => 'no-store']);
    $token = $request->isJson() ? $request->json('token') : $request->request->get('token');
    $limits = [Limit::perMinute(10)->by('track|ip|'.$request->ip())->response($respond)];
    if (is_string($token) && $token !== '') {
        $limits[] = Limit::perMinute(10)->by('track|token|'.hash('sha256', $token))->response($respond);
    }

    return $limits;
});

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/koleksi', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/pengingat-keranjang/berhenti/{user}', [ProfileController::class, 'confirmCartReminderUnsubscribe'])
    ->whereNumber('user')->middleware(['signed', 'throttle:6,1'])->name('cart-reminder.unsubscribe');
Route::post('/pengingat-keranjang/berhenti/{user}', [ProfileController::class, 'unsubscribeCartReminder'])
    ->whereNumber('user')->middleware(['signed', 'throttle:6,1'])->name('cart-reminder.unsubscribe.store');
Route::post('/locale', [ProfileController::class, 'updateLocale'])->middleware('throttle:20,1')->name('locale.update');
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
Route::post('/webhooks/midtrans', MidtransWebhookController::class)->middleware('throttle:60,1')->name('webhooks.midtrans');

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
    Route::get('/login/verifikasi', [CustomerTotpController::class, 'challenge'])->name('login.totp');
    Route::post('/login/verifikasi', [CustomerTotpController::class, 'verify'])
        ->middleware('throttle:login')
        ->name('login.totp.verify');

    Route::get('/lupa-kata-sandi', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-kata-sandi', [PasswordResetController::class, 'email'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-kata-sandi/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-kata-sandi', [PasswordResetController::class, 'update'])
        ->middleware('throttle:password-reset')
        ->name('password.update');

    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->where('provider', 'google|apple')
        ->middleware('throttle:login')
        ->name('social.redirect');
});

// Di luar grup guest: penautan dari akun yang sudah login juga kembali ke sini.
// Apple memanggil balik lewat POST lintas situs (CSRF dikecualikan, dilindungi nonce cookie).
Route::match(['get', 'post'], '/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google|apple')
    ->middleware('throttle:login')
    ->name('social.callback');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/akun', [ProfileController::class, 'show'])->name('account.show');
    Route::patch('/akun', [ProfileController::class, 'update'])->name('account.update');
    Route::get('/akun/ekspor', [ProfileController::class, 'export'])->middleware('throttle:6,1')->name('account.export');
    Route::delete('/akun', [ProfileController::class, 'destroy'])->middleware('throttle:5,1')->name('account.destroy');
    Route::get('/akun/verifikasi-dua-langkah', [CustomerTotpController::class, 'showEnrol'])->name('account.totp.enrol');
    Route::post('/akun/verifikasi-dua-langkah', [CustomerTotpController::class, 'enrol'])->middleware('throttle:10,1')->name('account.totp.store');
    Route::get('/akun/verifikasi-dua-langkah/kode-cadangan', [CustomerTotpController::class, 'recovery'])->name('account.totp.recovery');
    Route::delete('/akun/verifikasi-dua-langkah', [CustomerTotpController::class, 'disable'])->middleware('throttle:10,1')->name('account.totp.destroy');
    Route::get('/email/verifikasi/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verifikasi-ulang', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');
    Route::get('/email/verifikasi-perlu', fn () => redirect()->route('account.show')
        ->withErrors(['email' => __('customer_messages.auth.verify_first')]))
        ->name('verification.notice');
    // Hanya Google: callback Apple (POST lintas situs) tidak membawa sesi, jadi akun yang login tidak terlihat.
    Route::get('/akun/tautan/{provider}', [SocialAuthController::class, 'linkRedirect'])->where('provider', 'google')->middleware('verified')->name('social.link');
    Route::delete('/akun/tautan/{provider}', [SocialAuthController::class, 'unlink'])->where('provider', 'google|apple')->name('social.unlink');

    Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::patch('/addresses/{id}', [AddressController::class, 'update'])->whereNumber('id')->name('addresses.update');
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy'])->whereNumber('id')->name('addresses.destroy');

    Route::post('/checkout/quote', [OrderController::class, 'quote'])->middleware([NoStore::class, 'throttle:cart'])->name('checkout.quote');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id')->name('orders.show');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id')->name('orders.cancel');
    Route::get('/returns', [OrderReturnController::class, 'index'])->name('returns.index');
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('/returns/{id}/refunds', [RefundController::class, 'store'])->whereNumber('id')->name('refunds.store');
    Route::get('/orders/{id}/return-eligibility', [OrderReturnController::class, 'eligibility'])->whereNumber('id')->name('returns.eligibility');
    Route::post('/orders/{id}/returns', [OrderReturnController::class, 'store'])->whereNumber('id')->middleware('throttle:cart')->name('returns.store');

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
    Route::get('/admin', [AdminDashboardController::class, 'index'])->middleware('admin.can:view-dashboard')->name('admin.dashboard');
    Route::patch('/admin/concierge/{concierge}', [AdminDashboardController::class, 'updateConcierge'])->middleware('admin.can:manage-concierge')->name('admin.concierge.update');
    Route::patch('/admin/password', [AdminDashboardController::class, 'updatePassword'])->middleware('admin.can:view-dashboard')->name('admin.password.update');
    Route::post('/admin/categories', [AdminCategoryController::class, 'store'])->middleware('admin.can:manage-categories')->name('admin.categories.store');
    Route::patch('/admin/categories/{category:cat_id}', [AdminCategoryController::class, 'update'])->middleware('admin.can:manage-categories')->name('admin.categories.update');
    Route::delete('/admin/categories/{category:cat_id}', [AdminCategoryController::class, 'destroy'])->middleware('admin.can:manage-categories')->name('admin.categories.destroy');
    Route::patch('/admin/products/{product:product_id}', [AdminProductController::class, 'update'])->middleware('admin.can:manage-catalog')->name('admin.products.update');
    Route::post('/admin/products', [AdminProductController::class, 'store'])->middleware('admin.can:manage-catalog')->name('admin.products.store');
    Route::delete('/admin/products/{product:product_id}', [AdminProductController::class, 'destroy'])->middleware('admin.can:manage-catalog')->name('admin.products.destroy');
    Route::delete('/admin/subscribers/{subscriber}', [AdminDashboardController::class, 'destroySubscriber'])->middleware('admin.can:manage-subscribers')->name('admin.subscribers.destroy');
    Route::get('/admin/orders', [AdminOrderController::class, 'index'])->middleware('admin.can:manage-orders')->name('admin.orders.index');
    Route::get('/admin/orders/{id}', [AdminOrderController::class, 'show'])->whereNumber('id')->middleware('admin.can:manage-orders')->name('admin.orders.show');
    Route::patch('/admin/orders/{id}', [AdminOrderController::class, 'updateStatus'])->whereNumber('id')->middleware('admin.can:manage-orders')->name('admin.orders.update');
    Route::get('/admin/orders/{id}/invoice', [AdminOrderController::class, 'invoice'])->whereNumber('id')->middleware('admin.can:manage-orders')->name('admin.orders.invoice');
    Route::get('/admin/reports/sales-summary', [AdminOrderController::class, 'salesSummary'])->middleware(['feature:admin_sales_summary', 'admin.can:view-reports'])->name('admin.reports.sales-summary');
    Route::get('/admin/reports/operations-summary', [AdminOperationsSummaryController::class, 'index'])->middleware(['feature:admin_ops_summary', 'admin.can:view-reports'])->name('admin.reports.operations-summary');
    Route::get('/admin/reports/orders.pdf', [AdminOrderController::class, 'report'])->middleware('admin.can:view-reports')->name('admin.orders.report');
    Route::get('/admin/auth-events', [AdminAuthEventController::class, 'index'])->middleware('admin.can:view-audit-log')->name('admin.auth-events.index');
    Route::patch('/admin/refunds/{id}', [RefundController::class, 'update'])->whereNumber('id')->middleware('admin.can:manage-refunds')->name('admin.refunds.update');
});
