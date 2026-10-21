<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menutup route ketika flag mati untuk subject saat ini.
 *
 * Subject diambil dari id admin yang terautentikasi agar cohort deterministik;
 * ketika tidak ada admin, subject adalah flag itu sendiri (cohort tetap).
 */
class FeatureFlag
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $subject = (string) ($request->user('admin')?->getAuthIdentifier() ?? $flag);

        if (! app(FeatureFlags::class)->enabled($flag, $subject)) {
            return response()->json(['message' => 'Not Found.'], 404, ['Cache-Control' => 'no-store, private']);
        }

        return $next($request);
    }
}
