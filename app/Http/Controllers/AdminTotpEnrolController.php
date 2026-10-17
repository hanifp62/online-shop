<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminTotp;
use App\Services\TotpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Response;
use PragmaRX\Google2FA\Exceptions\Contracts\Google2FA as Google2FAException;
use PragmaRX\Google2FA\Google2FA;

/** Self-service TOTP enrolment. Pending secret lives encrypted in the session, never in the DB. */
class AdminTotpEnrolController extends Controller
{
    public const PENDING_SECRET = 'totp_enrol_secret';

    public const PENDING_URI = 'totp_enrol_uri';

    public const FRESH_CODES = 'totp_enrol_codes';

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TotpService $totp, private readonly Google2FA $google2fa) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $admin = $request->user('admin');
        // An admin who already holds a secret must not rotate it with a password-only session.
        if ($admin->getRawOriginal('totp_secret') !== null) {
            return redirect()->route('admin.dashboard');
        }

        $secret = $this->totp->generateSecret();
        $uri = $this->totp->provisioningUri($admin->email, $secret);
        $request->session()->put(self::PENDING_SECRET, Crypt::encryptString($secret));
        $request->session()->put(self::PENDING_URI, Crypt::encryptString($uri));

        return response()
            ->view('admin.totp-enrol', ['secret' => $secret, 'uri' => $uri, 'qr' => $this->totp->qrDataUri($uri)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        if ($admin->getRawOriginal('totp_secret') !== null) {
            return redirect()->route('admin.dashboard');
        }

        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $key = 'admin-totp-enrol:'.$admin->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['code' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik."]);
        }

        $pending = $request->session()->get(self::PENDING_SECRET);
        if (! is_string($pending) || $pending === '') {
            return redirect()->route('admin.totp.enrol')
                ->withErrors(['code' => 'Sesi pendaftaran kedaluwarsa. Mulai ulang pendaftaran.']);
        }

        try {
            $secret = Crypt::decryptString($pending);
            $timestamp = $this->google2fa->verifyKeyNewer($secret, trim($validated['code']), 0, 1);
            if ($timestamp !== false && (int) $timestamp <= (int) ($admin->totp_last_used_at ?? 0)) {
                $timestamp = false;
            }
        } catch (DecryptException|Google2FAException $e) {
            Log::critical('Admin TOTP enrolment secret unreadable or invalid', [
                'admin_id' => $admin->id,
                'exception' => $e::class,
            ]);
            $request->session()->forget([self::PENDING_SECRET, self::PENDING_URI]);

            return back()->withErrors(['code' => 'Pendaftaran dua langkah sedang tidak tersedia. Hubungi pengelola sistem.']);
        }

        if ($timestamp === false) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'Kode tidak cocok. Periksa jam perangkat Anda.']);
        }

        $codes = $this->totp->generateRecoveryCodes();

        // CAS on totp_secret IS NULL: two concurrent enrol POSTs cannot both win.
        $won = DB::transaction(function () use ($admin, $secret, $timestamp, $codes): bool {
            $updated = DB::table('admins')
                ->where('id', $admin->id)
                ->whereNull('totp_secret')
                ->update([
                    'totp_secret' => Crypt::encryptString($secret),
                    'totp_required_at' => now(),
                    'totp_last_used_at' => (int) $timestamp,
                    'updated_at' => now(),
                ]);
            if ($updated !== 1) {
                return false;
            }
            $this->totp->replaceRecoveryCodes($admin, $codes);

            return true;
        });

        if (! $won) {
            $request->session()->forget([self::PENDING_SECRET, self::PENDING_URI]);

            return redirect()->route('admin.dashboard');
        }

        RateLimiter::clear($key);
        $request->session()->forget([self::PENDING_SECRET, self::PENDING_URI]);
        // The code just verified counts as this device's second factor.
        $request->session()->regenerate(true);
        $request->session()->put(EnsureAdminTotp::SESSION_KEY, [
            'admin_id' => $admin->id,
            'session_id' => $request->session()->getId(),
            'last_used_at' => (int) $timestamp,
        ]);
        $request->session()->put(self::FRESH_CODES, Crypt::encryptString(json_encode($codes)));

        return redirect()->route('admin.totp.enrol.recovery');
    }

    public function recovery(Request $request): Response|RedirectResponse
    {
        // pull(): the codes are rendered exactly once, so a refresh cannot reveal them again.
        $blob = $request->session()->pull(self::FRESH_CODES);
        $codes = null;
        if (is_string($blob)) {
            try {
                $codes = json_decode(Crypt::decryptString($blob), true);
            } catch (DecryptException) {
                $codes = null;
            }
        }
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('admin.dashboard');
        }

        return response()
            ->view('admin.totp-enrol-recovery', ['codes' => $codes])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }
}
