<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminTotp;
use App\Services\AuthEventRecorder;
use App\Services\TotpService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Exceptions\Contracts\Google2FA as Google2FAException;
use PragmaRX\Google2FA\Google2FA;

/** Self-service TOTP enrolment. Pending secret lives encrypted in the session, never in the DB. */
class AdminTotpEnrolController extends Controller
{
    public const PENDING_SECRET = 'totp_enrol_secret';

    public const PENDING_URI = 'totp_enrol_uri';

    public const FRESH_CODES = 'totp_enrol_codes';

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TotpService $totp,
        private readonly Google2FA $google2fa,
        private readonly AuthEventRecorder $events,
    ) {}

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

            return back()->withErrors(['code' => trans('admin_ui.flash.rate_limited', ['seconds' => $seconds])]);
        }

        $pending = $request->session()->get(self::PENDING_SECRET);
        if (! is_string($pending) || $pending === '') {
            return redirect()->route('admin.totp.enrol')
                ->withErrors(['code' => trans('admin_ui.flash.enrol_expired')]);
        }

        try {
            $secret = Crypt::decryptString($pending);
            $timestamp = $this->google2fa->verifyKeyNewer($secret, trim($validated['code']), 0, 1);
            if ($timestamp !== false && (int) $timestamp <= (int) ($admin->totp_last_used_at ?? 0)) {
                $timestamp = false;
            }
        } catch (DecryptException|Google2FAException $e) {
            $this->events->recordTotpFailure($admin->id, $request->ip(), 'corrupt_secret');
            try {
                Log::critical('Admin TOTP enrolment secret unreadable or invalid', [
                    'admin_id' => $admin->id,
                    'exception' => $e::class,
                ]);
            } catch (\Throwable) {
                // Logger failure must not expose the verification trace.
            }
            $request->session()->forget([self::PENDING_SECRET, self::PENDING_URI]);

            return back()->withErrors(['code' => trans('admin_ui.flash.enrol_unavailable')]);
        } catch (\Throwable $e) {
            $this->events->recordTotpFailure($admin->id, $request->ip(), 'operational_error');
            try {
                Log::critical('Admin TOTP enrolment verification failed', [
                    'admin_id' => $admin->id,
                    'exception' => $e::class,
                ]);
            } catch (\Throwable) {
                // Logger failure must not expose the verification trace.
            }
            $request->session()->forget([self::PENDING_SECRET, self::PENDING_URI]);

            return back()->withErrors(['code' => trans('admin_ui.flash.enrol_unavailable')]);
        }

        if ($timestamp === false) {
            $this->events->recordTotpFailure($admin->id, $request->ip(), 'invalid_code');
            RateLimiter::hit($key, 60);
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $this->events->recordLockout('admin', $admin->id, $request->ip(), 'enrol');
            }

            return back()->withErrors(['code' => trans('admin_ui.flash.enrol_invalid')]);
        }

        $codes = $this->totp->generateRecoveryCodes();

        try {
            $won = $this->persistEnrolment($admin, $secret, (int) $timestamp, $codes);
        } catch (\Throwable $e) {
            // Transaction rolled back; log class only (messages/traces may carry the secret or codes).
            $this->events->recordTotpFailure($admin->id, $request->ip(), 'operational_error');
            try {
                Log::critical('Admin TOTP enrolment transaction failed', ['admin_id' => $admin->id, 'exception' => $e::class]);
            } catch (\Throwable) {
                // Logger down must not escalate into an unhandled, trace-logging error.
            }

            return back()->withErrors(['code' => trans('admin_ui.flash.enrol_retry')]);
        }

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
        $this->events->recordEnrolConfirmed($admin->id, $request->ip());
        // No LOGIN_SUCCESS here: console access was already granted (and recorded) at the password step.
        $this->events->recordSessionRotated('admin', $admin->id, $request->ip(), 'enrol');

        return redirect()->route('admin.totp.enrol.recovery');
    }

    /** @param list<string> $codes */
    private function persistEnrolment($admin, #[\SensitiveParameter] string $secret, int $timestamp, #[\SensitiveParameter] array $codes): bool
    {
        // CAS on totp_secret IS NULL: two concurrent enrol POSTs cannot both win.
        return DB::transaction(function () use ($admin, $secret, $timestamp, $codes): bool {
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
