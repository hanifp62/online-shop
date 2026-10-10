<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Kernel dapat di-resolve sebelum konfigurasi dimuat; baca setelah bootstrap selesai.
        app()->booted(function () use ($middleware): void {
            $middleware->trustProxies(at: config('trustedproxy.proxies', []));
        });

        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin*')
            ? route('admin.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
