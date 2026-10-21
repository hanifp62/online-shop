<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['id']);
        $sessionLocale = $request->hasSession() ? $request->session()->get('locale') : null;
        $chosen = $request->query('locale') ?? $sessionLocale ?? $request->user()?->locale;
        $candidate = $chosen ?? config('app.locale', 'id');
        $locale = is_string($candidate) && in_array($candidate, $supported, true) ? $candidate : 'id';
        app()->setLocale($locale);

        if ($request->hasSession() && ($request->query('locale') !== null || $sessionLocale !== null)) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
