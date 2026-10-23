<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AuthenticateGuardSession;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\TotpService;
use App\Support\AuthSecurityPageProps;
use App\Support\FrontendPage;
use App\Support\TotpEnrolPageProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    private const STATE_TTL = 300;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TotpService $totp) {}

    /** Selesaikan login; bila akun memakai TOTP, tunda sampai kode kedua diverifikasi. */
    public static function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        AuthenticateGuardSession::clear($request, 'web');
        if ($user->hasTotp()) {
            AuthenticateGuardSession::rotate($request, 'web');
            $request->session()->put(self::PENDING_LOGIN, [
                'user_id' => $user->id,
                'remember' => $remember,
                'password' => AuthenticateGuardSession::fingerprint($user),
                'expires_at' => now()->addSeconds(self::LOGIN_TTL)->getTimestamp(),
            ]);

            return redirect()->route('login.totp');
        }

        AuthenticateGuardSession::rotate($request, 'web', fn () => Auth::guard('web')->login($user, $remember));
        $request->attributes->set('cart_allow_merge', true);
        CheckoutService::cart($request);

        return redirect('/')->with('success', __('customer_messages.customer_totp.login'));
    }

    public function challenge(Request $request): View|\Inertia\Response|RedirectResponse
    {
        if (! $this->pendingUserId($request)) {
            return redirect()->route('login');
        }

        return FrontendPage::render('Auth/TotpChallenge', 'auth.totp', [], AuthSecurityPageProps::customerTotp($request));
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
        if (! $user || ! is_string($pending['password'] ?? null)
            || ! hash_equals(AuthenticateGuardSession::fingerprint($user), $pending['password'])) {
            return redirect()->route('login');
        }

        AuthenticateGuardSession::rotate($request, 'web', fn () => Auth::guard('web')->login($user, (bool) ($pending['remember'] ?? false)));
        $request->attributes->set('cart_allow_merge', true);
        CheckoutService::cart($request);

        $remaining = $user->totpRecoveryCodes()->count();
        $message = $useRecovery ? __('customer_messages.customer_totp.remaining', ['count' => $remaining]) : __('customer_messages.customer_totp.login');

        return redirect('/')->with('success', $message);
    }

    public function showEnrol(Request $request): Response|RedirectResponse
    {
        if (TotpEnrolPageProps::prefetch($request)) {
            return response('', 204)->header('Cache-Control', 'no-store, private');
        }
        $user = $request->user();
        if ($user->hasTotp()) {
            return redirect()->route('account.show');
        }

        $secret = $this->totp->generateSecret();
        $uri = $this->totp->provisioningUri($user->email, $secret);
        $request->session()->put(self::PENDING_SECRET, Crypt::encryptString($secret));
        $request->session()->put('customer_totp_state', [
            'user_id' => $user->id, 'expires_at' => now()->addSeconds(self::STATE_TTL)->timestamp,
        ]);
        $request->session()->forget([self::FRESH_CODES, 'customer_totp_codes_state']);

        return TotpEnrolPageProps::render($request, false, false, ['secret' => $secret, 'qr' => $this->totp->qrDataUri($uri)]);
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
        $state = $request->session()->get('customer_totp_state');
        if (! is_array($state) || ($state['user_id'] ?? null) !== $user->id
            || ($state['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget([self::PENDING_SECRET, 'customer_totp_state']);

            return redirect()->route('account.totp.enrol')->withErrors(['code' => __('customer_messages.customer_totp.enrol_expired')]);
        }
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
        $won = DB::transaction(function () use ($user, $secret, $timestamp, $codes): bool {
            $updated = DB::table('users')->where('id', $user->id)->whereNull('totp_secret')->update([
                'totp_secret' => Crypt::encryptString($secret),
                'totp_enabled_at' => now(),
                'totp_last_used_at' => $timestamp,
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                return false;
            }
            $this->totp->replaceUserRecoveryCodes($user, $codes);

            return true;
        });
        if (! $won) {
            $request->session()->forget([self::PENDING_SECRET, 'customer_totp_state', self::FRESH_CODES, 'customer_totp_codes_state']);

            return redirect()->route('account.show');
        }

        RateLimiter::clear($key);
        $request->session()->forget([self::PENDING_SECRET, 'customer_totp_state']);
        $request->session()->put(self::FRESH_CODES, Crypt::encryptString(json_encode($codes)));
        $request->session()->put('customer_totp_codes_state', [
            'user_id' => $user->id, 'expires_at' => now()->addSeconds(self::STATE_TTL)->timestamp,
        ]);

        return redirect()->route('account.totp.recovery');
    }

    public function recovery(Request $request): Response|RedirectResponse
    {
        if (TotpEnrolPageProps::prefetch($request)) {
            return response('', 204)->header('Cache-Control', 'no-store, private');
        }
        if ($documentVisit = TotpEnrolPageProps::recoveryDocumentVisit($request, false)) {
            return $documentVisit;
        }
        // pull(): kode cadangan hanya tampil sekali.
        $blob = $request->session()->pull(self::FRESH_CODES);
        $state = $request->session()->pull('customer_totp_codes_state');
        if (! is_array($state) || ($state['user_id'] ?? null) !== $request->user()->id
            || ($state['expires_at'] ?? 0) <= now()->timestamp) {
            return redirect()->route('account.show');
        }
        try {
            $codes = is_string($blob) ? json_decode(Crypt::decryptString($blob), true) : null;
        } catch (\Throwable) {
            $codes = null;
        }
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('account.show');
        }

        return TotpEnrolPageProps::render($request, false, true, ['codes' => $codes]);
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
        $user = is_array($pending) ? User::find($pending['user_id'] ?? 0) : null;
        if (! $user || ! is_string($pending['password'] ?? null)
            || ! hash_equals(AuthenticateGuardSession::fingerprint($user), $pending['password'])
            || ($pending['expires_at'] ?? 0) <= now()->getTimestamp()) {
            $request->session()->forget(self::PENDING_LOGIN);

            return null;
        }

        return (int) $pending['user_id'];
    }
}
