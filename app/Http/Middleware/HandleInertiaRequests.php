<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    public function urlResolver(): Closure
    {
        // Public catalogue URLs deliberately use repeated, non-indexed kategori keys.
        return fn (Request $request): string => $request->getRequestUri();
    }

    public function onVersionChange(Request $request, Response $response): Response
    {
        $response = parent::onVersionChange($request, $response);
        $response->headers->set('X-Inertia-Location', $request->getSchemeAndHttpHost().$request->getRequestUri());
        $response->headers->set('Vary', 'X-Inertia', false);

        return $response;
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => fn () => app()->getLocale(),
        ];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        $view = $response instanceof \Illuminate\Http\Response && $response->original instanceof View
            ? $response->original : null;
        $fallback = $view?->offsetExists('_frontendFallback') ?? false;
        // Only successful, explicitly opted-in page GETs may cross the renderer boundary.
        // Never turn an API, validation error, or POST response into a successful visit.
        if ($fallback && $request->isMethod('GET') && $request->header('X-Inertia') && $response->getStatusCode() === 200) {
            // fullUrl() normalizes query parameters; repeated kategori keys must survive.
            $response = Inertia::location($request->getSchemeAndHttpHost().$request->getRequestUri());
            $response->headers->set('Vary', 'X-Inertia', false);
        }

        // Page variants include session-specific forms. Never let a shared cache mix users.
        if ($fallback || $response->headers->has('X-Inertia')
            || $response->headers->has('X-Inertia-Location') || $view?->offsetExists('page')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
