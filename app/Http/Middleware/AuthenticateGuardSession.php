<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Password revocation is guard-scoped and independent of the TOTP replay watermark. */
class AuthenticateGuardSession
{
    public static function fingerprint(Authenticatable $user): string
    {
        return hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key'));
    }

    public static function remember(Request $request, string $guard, Authenticatable $user): void
    {
        $request->session()->put('auth_fingerprint_'.$guard, [
            'id' => $user->getAuthIdentifier(),
            'password' => self::fingerprint($user),
        ]);
    }

    public static function clear(Request $request, string $guard): void
    {
        $keys = $guard === 'admin'
            ? ['admin_totp', 'totp_enrol_secret', 'totp_enrol_uri', 'totp_enrol_state', 'totp_enrol_codes', 'totp_enrol_codes_state']
            : ['customer_totp_login', 'customer_totp_secret', 'customer_totp_state', 'customer_totp_codes', 'customer_totp_codes_state'];
        $request->session()->forget([...$keys, 'auth_fingerprint_'.$guard]);
    }

    /** Preserve only an already-valid OTHER guard's second factor across an ID rotation. */
    public static function rotate(Request $request, string $guard, ?Closure $authenticate = null): void
    {
        $marker = $request->session()->get(EnsureAdminTotp::SESSION_KEY);
        $preserve = false;
        if ($guard === 'web') {
            $admin = Auth::guard('admin')->user()?->fresh();
            $fingerprint = $request->session()->get('auth_fingerprint_admin');
            $preserve = $admin && $admin->requiresTotp() && is_array($marker)
                && ($marker['admin_id'] ?? null) === $admin->id
                && ($marker['session_id'] ?? null) === $request->session()->getId()
                && $admin->totp_last_used_at !== null
                && ($marker['last_used_at'] ?? null) === (int) $admin->totp_last_used_at
                && is_array($fingerprint) && ($fingerprint['id'] ?? null) === $admin->id
                && is_string($fingerprint['password'] ?? null)
                && hash_equals(self::fingerprint($admin), $fingerprint['password']);
        }
        // SessionGuard::login also migrates the ID, so capture validity before authenticating.
        $authenticate?->__invoke();
        $request->session()->migrate(true);
        $request->session()->regenerateToken();
        if ($guard === 'web') {
            if ($preserve) {
                $marker['session_id'] = $request->session()->getId();
                $request->session()->put(EnsureAdminTotp::SESSION_KEY, $marker);
            } else {
                $request->session()->forget(EnsureAdminTotp::SESSION_KEY);
            }
        }
    }

    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        $auth = Auth::guard($guard);
        $user = $auth->user();
        if (! $user) {
            return $next($request);
        }
        $fresh = $user->newQuery()->find($user->getAuthIdentifier());
        $marker = $request->session()->get('auth_fingerprint_'.$guard);
        // In-memory authentication (e.g. actingAs) has no persisted login to revoke.
        // Real pre-rollout sessions without a fingerprint must log in again, not be silently trusted.
        if ($fresh && ! $request->session()->has($auth->getName())) {
            self::remember($request, $guard, $fresh);
            $marker = $request->session()->get('auth_fingerprint_'.$guard);
        }
        if (! $fresh || ! is_array($marker)
            || ($marker['id'] ?? null) !== $fresh->getAuthIdentifier()
            || ! is_string($marker['password'] ?? null)
            || ! hash_equals(self::fingerprint($fresh), $marker['password'])) {
            $auth->logoutCurrentDevice();
            self::clear($request, $guard);
            self::rotate($request, $guard);

            return redirect()->route($guard === 'admin' ? 'admin.login' : 'login');
        }

        return $next($request);
    }
}
