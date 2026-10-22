<?php

namespace App\Support;

use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

final class FrontendPage
{
    public static function render(string $component, string $view, array $viewData, array $props, ?string $url = null): Response|View
    {
        if (! config('frontend.enabled') || request()->query('frontend') === 'blade'
            || ! in_array($component, config('frontend.pages', []), true)) {
            return view($view, $viewData)->with('_frontendFallback', true);
        }

        if ($url !== null) {
            // A page may provide its validated canonical query instead of reflecting rejected input.
            $path = parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            Inertia::resolveUrlUsing(fn () => $path.($query === null ? '' : '?'.$query));
        }

        // The original view remains a no-JS fallback; only the explicit DTO crosses the wire.
        return Inertia::render($component, $props)->rootView($view)->withViewData($viewData);
    }
}
