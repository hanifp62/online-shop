<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-XSS-Protection', '0');

        if (config('security.csp_report_only', false)) {
            $response->headers->set(
                'Content-Security-Policy-Report-Only',
                $this->reportOnlyPolicy(),
            );
        }

        return $response;
    }

    /**
     * Allow known storefront asset hosts without hiding inline-code violations.
     * Report-Only does not block resources.
     * Reporting endpoint is omitted until one exists (see config/security.php).
     */
    private function reportOnlyPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "img-src 'self' data: https://images.unsplash.com",
            "font-src 'self' https://fonts.bunny.net",
            // shortcut: inline <style>/style= used across views, drop 'unsafe-inline' once moved to CSS files.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            // Strict on purpose: inline bootstrap scripts surface as reports to fix before enforcing.
            "script-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
        ]);
    }
}
