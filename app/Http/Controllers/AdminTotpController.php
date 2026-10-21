<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminTotp;
use App\Services\AuthEventRecorder;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminTotpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TotpService $totp, private readonly AuthEventRecorder $events) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user('admin')->requiresTotp()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.totp');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        return $this->attempt($request, 'code', fn (int $id) => $this->totp->verifyAndAdvance($id, $validated['code']));
    }

    public function recovery(Request $request): RedirectResponse
    {
        $validated = $request->validate(['recovery_code' => ['required', 'string', 'max:32']]);

        return $this->attempt($request, 'recovery_code', fn (int $id) => $this->totp->consumeRecoveryCode($id, $validated['recovery_code']));
    }

    private function attempt(Request $request, string $field, callable $check): RedirectResponse
    {
        $admin = $request->user('admin');
        if (! $admin->requiresTotp()) {
            return redirect()->route('admin.dashboard');
        }

        // Per account AND per IP: one attacker IP cannot lock out other admins (K7).
        $key = 'admin-totp:'.$admin->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([$field => trans('admin_ui.flash.rate_limited', ['seconds' => $seconds])]);
        }

        $result = $check($admin->id);

        if (! $result['ok']) {
            $this->events->recordTotpFailure($admin->id, $request->ip(), (string) ($result['reason'] ?? 'invalid_code'));
            RateLimiter::hit($key, 60);
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $this->events->recordLockout('admin', $admin->id, $request->ip(), 'totp');
            }
            $message = in_array($result['reason'] ?? null, ['broken_secret', 'operational_error'], true)
                ? trans('admin_ui.flash.totp_unavailable')
                : trans('admin_ui.flash.totp_invalid');

            return back()->withErrors([$field => $message]);
        }

        RateLimiter::clear($key);
        if ($field === 'recovery_code') {
            $this->events->recordRecoveryUsed($admin->id, $request->ip());
        }
        // Second rotation: the pre-verification session id must not carry console access.
        $request->session()->regenerate(true);
        $request->session()->put(EnsureAdminTotp::SESSION_KEY, [
            'admin_id' => $admin->id,
            'session_id' => $request->session()->getId(),
            'last_used_at' => (int) $result['timestamp'],
        ]);
        $this->events->recordSessionRotated('admin', $admin->id, $request->ip(), $field === 'recovery_code' ? 'recovery' : 'totp');
        $this->events->recordLoginSuccess($admin->id, $request->ip(), 'admin', $field === 'recovery_code' ? 'recovery' : 'totp');

        return redirect()->route('admin.dashboard')->with('success', trans('admin_ui.flash.welcome'));
    }
}
