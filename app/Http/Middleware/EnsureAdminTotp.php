<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Runs after auth:admin. Enforces TOTP only for accounts with flag AND secret (K5). */
class EnsureAdminTotp
{
    public const SESSION_KEY = 'admin_totp';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        if (! $admin instanceof Admin) {
            return redirect()->route('admin.login');
        }

        if (! $admin->requiresTotp()) {
            if ($admin->totp_required_at !== null) {
                Log::critical('Admin TOTP flag present without secret', ['admin_id' => $admin->id]);

                return redirect()->route('admin.totp.enrol');
            }

            return $next($request);
        }

        // Fresh DB read: a newer verification on another device invalidates this marker (K6).
        $current = Admin::whereKey($admin->id)->value('totp_last_used_at');
        $marker = $request->session()->get(self::SESSION_KEY);

        $valid = is_array($marker)
            && ($marker['admin_id'] ?? null) === $admin->id
            && ($marker['session_id'] ?? null) === $request->session()->getId()
            && $current !== null
            && ($marker['last_used_at'] ?? null) === (int) $current;

        if (! $valid) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('admin.totp.challenge');
        }

        return $next($request);
    }
}
