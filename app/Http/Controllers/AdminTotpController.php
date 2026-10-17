<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminTotp;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminTotpController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TotpService $totp) {}

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

            return back()->withErrors([$field => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."]);
        }

        $result = $check($admin->id);

        if (! $result['ok']) {
            RateLimiter::hit($key, 60);
            $message = ($result['reason'] ?? null) === 'broken_secret'
                ? 'Verifikasi dua langkah sedang tidak tersedia. Hubungi pengelola sistem.'
                : 'Kode tidak cocok atau sudah dipakai.';

            return back()->withErrors([$field => $message]);
        }

        RateLimiter::clear($key);
        // Second rotation: the pre-verification session id must not carry console access.
        $request->session()->regenerate(true);
        $request->session()->put(EnsureAdminTotp::SESSION_KEY, [
            'admin_id' => $admin->id,
            'session_id' => $request->session()->getId(),
            'last_used_at' => (int) $result['timestamp'],
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Selamat datang kembali, Admin.');
    }
}
