<?php

namespace App\Support;

use Illuminate\Http\Request;

final class IntendedRedirect
{
    private const BLOCKED_PREFIXES = ['//', '/\\', '/admin', '/logout', '/auth/'];

    public static function sanitize(mixed $target): ?string
    {
        if (! is_string($target)) {
            return null;
        }

        $trimmed = trim($target);
        if ($trimmed === '' || strlen($trimmed) > 255 || ! str_starts_with($trimmed, '/')) {
            return null;
        }

        if (preg_match('/[\x00-\x1f\x7f]/', $trimmed) === 1) {
            return null;
        }

        $lower = strtolower($trimmed);
        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return null;
            }
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false || isset($parsed['scheme']) || isset($parsed['host']) || isset($parsed['user'])) {
            return null;
        }

        return $trimmed;
    }

    public static function capture(Request $request): ?string
    {
        $candidate = $request->input('intended') ?? $request->query('intended');
        $clean = self::sanitize($candidate);
        if ($clean !== null) {
            $request->session()->put('url.intended', $clean);

            return $clean;
        }

        return self::sanitize($request->session()->get('url.intended'));
    }

    public static function pullOrDefault(Request $request, string $default = '/'): string
    {
        $fromRequest = self::sanitize($request->input('intended') ?? $request->query('intended'));
        $stored = self::sanitize($request->session()->pull('url.intended'));

        return $fromRequest ?? $stored ?? $default;
    }
}
