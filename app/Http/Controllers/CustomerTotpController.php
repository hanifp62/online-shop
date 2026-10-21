<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CheckoutService;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Verifikasi dua langkah opsional untuk pelanggan: tidak pernah dipaksa.
 * Rahasia yang belum dikonfirmasi hanya hidup terenkripsi di sesi.
 */
class CustomerTotpController extends Controller
{
    public const PENDING_LOGIN = 'customer_totp_login';

    private const PENDING_SECRET = 'customer_totp_secret';

    private const FRESH_CODES = 'customer_totp_codes';

    private const LOGIN_TTL = 300;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TotpService $totp) {}

    /** Selesaikan login; bila akun memakai TOTP, tunda sampai kode kedua diverifikasi. */
    public static function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        if ($user->hasTotp()) {
            $request->session()->regenerate();
            $request->session()->put(self::PENDING_LOGIN, [
                'user_id' => $user->id,
                'remember' => $remember,
                'expires_at' => now()->addSeconds(self::LOGIN_TTL)->getTimestamp(),
            ]);

            return redirect()->route('login.totp');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->attributes->set('cart_allow_merge', true);
        CheckoutService::cart($request);

        return redirect('/')->with('success', __('customer_messages.customer_totp.login'));
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUserId($request)) {
            return redirect()->route('login');
        }

        return response()->view('auth.totp')->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request): RedirectResponse
    {
        $userId = $this->pendingUserId($request);
        if (! $userId) {
            return redirect()->route('login')->withErrors(['email' => __('customer_messages.customer_totp.expired')]);
        }

        $useRecovery = $request->filled('recovery_code');
        $field = $useRecovery ? 'recovery_code' : 'code';
        $request->validate($useRecovery
            ? ['recovery_code' => ['required', 'string', 'max:20']]
            : ['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $key = 'customer-totp:'.$userId;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return back()->withErrors([$field => __('customer_messages.customer_totp.rate_limited', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        try {
            $result = $useRecovery
                ? $this->totp->consumeUserRecoveryCode($userId, (string) $request->input('recovery_code'))
                : $this->totp->verifyUserAndAdvance($userId, (string) $request->input('code'));
        } catch (\Throwable $e) {
            Log::critical('Customer TOTP verification failed', ['user_id' => $userId, 'exception' => $e::class]);
            $result = ['ok' => false];
        }

        if (! $result['ok']) {
            RateLimiter::hit($key, 300);

            return back()->withErrors([$field => $useRecovery ? __('customer_messages.customer_totp.invalid_recovery') : __('customer_messages.customer_totp.invalid_clock')]);
        }

        RateLimiter::clear($key);
        $pending = $request->session()->pull(self::PENDING_LOGIN);
        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('login');
        }

        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        $request->attributes->set('cart_allow_merge', true);
        CheckoutService::cart($request);

        $remaining = $user->totpRecoveryCodes()->count();
        $message = $useRecovery ? __('customer_messages.customer_totp.remaining', ['count' => $remaining]) : __('customer_messages.customer_totp.login');

        return redirect('/')->with('success', $message);
    }

    public function showEnrol(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTotp()) {
            return redirect()->route('account.show');
        }

        $secret = $this->totp->generateSecret();
        $uri = $this->totp->provisioningUri($user->email, $secret);
        $request->session()->put(self::PENDING_SECRET, Crypt::encryptString($secret));

        return response()
            ->view('account.totp-enrol', ['secret' => $secret, 'qr' => $this->totp->qrDataUri($uri)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function enrol(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTotp()) {
            return redirect()->route('account.show');
        }
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $key = 'customer-totp-enrol:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return back()->withErrors(['code' => __('customer_messages.customer_totp.rate_limited', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        $pending = $request->session()->get(self::PENDING_SECRET);
        try {
            $secret = is_string($pending) ? Crypt::decryptString($pending) : null;
        } catch (\Throwable) {
            $secret = null;
        }
        if ($secret === null) {
            return redirect()->route('account.totp.enrol')->withErrors(['code' => __('customer_messages.customer_totp.enrol_expired')]);
        }

        $timestamp = $this->totp->verifyPendingSecret($secret, $validated['code']);
        if ($timestamp === false) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => __('customer_messages.customer_totp.invalid_clock')]);
        }

        $codes = $this->totp->generateRecoveryCodes();
        DB::transaction(function () use ($user, $secret, $timestamp, $codes) {
            $user->forceFill([
                'totp_secret' => $secret,
                'totp_enabled_at' => now(),
                'totp_last_used_at' => $timestamp,
            ])->save();
            $this->totp->replaceUserRecoveryCodes($user, $codes);
        });

        RateLimiter::clear($key);
        $request->session()->forget(self::PENDING_SECRET);
        $request->session()->put(self::FRESH_CODES, Crypt::encryptString(json_encode($codes)));

        return redirect()->route('account.totp.recovery');
    }

    public function recovery(Request $request): Response|RedirectResponse
    {
        // pull(): kode cadangan hanya tampil sekali.
        $blob = $request->session()->pull(self::FRESH_CODES);
        try {
            $codes = is_string($blob) ? json_decode(Crypt::decryptString($blob), true) : null;
        } catch (\Throwable) {
            $codes = null;
        }
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('account.show');
        }

        return response()
            ->view('account.totp-recovery', ['codes' => $codes])
            ->header('Cache-Control', 'no-store, private');
    }

    /** Matikan TOTP: wajib kode TOTP saat ini agar sesi curian tidak bisa melepas lapisan kedua. */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->hasTotp()) {
            return redirect()->route('account.show');
        }
        $validated = $request->validate(['disable_code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $key = 'customer-totp-disable:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return back()->withErrors(['disable_code' => __('customer_messages.customer_totp.rate_limited', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        if (! $this->totp->verifyUserAndAdvance($user->id, $validated['disable_code'])['ok']) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['disable_code' => __('customer_messages.customer_totp.invalid')]);
        }

        DB::transaction(function () use ($user) {
            $user->totpRecoveryCodes()->delete();
            $user->forceFill(['totp_secret' => null, 'totp_enabled_at' => null, 'totp_last_used_at' => null])->save();
        });
        RateLimiter::clear($key);

        return redirect()->route('account.show')->with('success', __('customer_messages.customer_totp.disabled'));
    }

    private function pendingUserId(Request $request): ?int
    {
        $pending = $request->session()->get(self::PENDING_LOGIN);
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::PENDING_LOGIN);

            return null;
        }

        return (int) $pending['user_id'];
    }
}
