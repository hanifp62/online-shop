<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies to success and handler-rendered errors (422/404/409/429/503) of guest endpoints. */
class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        // Symfony appends ", private" to non-public responses; both directives are desired.
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
