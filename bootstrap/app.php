<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\AuthorizeAdmin;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\FeatureFlag;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Di luar grup web: tanpa sesi/cookie/CSRF, sehingga DB mati tetap menghasilkan 503 jujur.
        then: function (): void {
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CorrelationIdMiddleware::class);
        $middleware->alias(['admin.can' => AuthorizeAdmin::class, 'feature' => FeatureFlag::class]);
        $middleware->web(replace: [
            ShareErrorsFromSession::class => SetLocale::class,
        ]);
        $middleware->web(append: [
            ShareErrorsFromSession::class,
            SecurityHeaders::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Kernel dapat di-resolve sebelum konfigurasi dimuat; baca setelah bootstrap selesai.
        app()->booted(function () use ($middleware): void {
            $middleware->trustProxies(at: config('trustedproxy.proxies', []));
        });

        $middleware->validateCsrfTokens(except: ['webhooks/midtrans', 'auth/apple/callback']);

        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin*')
            ? route('admin.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Halaman tak dikenal tetap perlu cookie sesi terenkripsi + locale sebelum 404 dirender.
        // Pakai pipeline manual (bukan Route::fallback) supaya 405 method-not-allowed dan 404 route
        // yang benar tetap utuh.
        $exceptions->render(function (NotFoundHttpException $e, $request) {
            if ($request->route() !== null || $request->expectsJson()) {
                return null;
            }

            return (new Pipeline(app()))->send($request)->through([
                EncryptCookies::class,
                StartSession::class,
                SetLocale::class,
                ShareErrorsFromSession::class,
            ])->then(fn () => response()->view('errors.404', ['exception' => $e], 404, $e->getHeaders()));
        });

        // Tautan verifikasi kedaluwarsa/rusak: arahkan ke akun dengan langkah pemulihan, bukan 403 polos.
        $exceptions->render(function (InvalidSignatureException $e, $request) {
            if ($request->routeIs('verification.verify') && $request->user()) {
                return redirect()->route('account.show')->withErrors([
                    'email' => __('customer_messages.auth.verification_invalid'),
                ]);
            }
        });
    })->create();
