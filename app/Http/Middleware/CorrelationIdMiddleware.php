<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header(self::HEADER);
        // Header dari luar hanya dipakai bila aman (8-64 karakter [A-Za-z0-9-_]); selain itu buat UUID baru.
        $requestId = (is_string($raw) && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $raw))
            ? $raw
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        // Context ikut ke setiap entri log dan ke job antrean yang di-dispatch selama request ini.
        Context::add('request_id', $requestId);

        $response = $next($request);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
