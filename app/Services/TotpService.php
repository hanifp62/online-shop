<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminTotpRecoveryCode;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Exceptions\Contracts\Google2FA as Google2FAException;
use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly Google2FA $google2fa = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function makeOtpAuthUri(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name', 'ZAFIRA'), $email, $secret);
    }

    /** @return list<string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $part = fn (): string => implode('', array_map(
                fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)],
                range(1, 5),
            ));
            $codes[] = $part().'-'.$part();
        }

        return $codes;
    }

    public static function normalizeRecoveryCode(string $code): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($code)));
    }

    /** @param list<string> $plainCodes */
    public function replaceRecoveryCodes(Admin $admin, array $plainCodes): void
    {
        $admin->totpRecoveryCodes()->delete();
        foreach ($plainCodes as $code) {
            $admin->totpRecoveryCodes()->create([
                'code_hash' => Hash::make(self::normalizeRecoveryCode($code)),
            ]);
        }
    }

    public function verifyAndAdvance(int $adminId, string $code): array
    {
        return DB::transaction(function () use ($adminId, $code): array {
            // Defense-in-depth lock; anti-replay relies on CAS below.
            $admin = Admin::whereKey($adminId)->lockForUpdate()->first();
            if (! $admin || $admin->totp_required_at === null) {
                return ['ok' => false, 'reason' => 'not_required', 'timestamp' => null];
            }
            if ($admin->getRawOriginal('totp_secret') === null) {
                Log::critical('Admin TOTP flag present without secret', ['admin_id' => $adminId]);

                return ['ok' => false, 'reason' => 'broken_secret', 'timestamp' => null];
            }

            try {
                $secret = $admin->totp_secret;
                $storedOld = $admin->totp_last_used_at;
                $vendorOld = $storedOld ?? 0;
                $window = 1; // Explicit ±1 step tolerance; never accept client timestamps.
                $result = $this->google2fa->verifyKeyNewer($secret, trim($code), $vendorOld, $window);
            } catch (DecryptException|Google2FAException $e) {
                Log::critical('Admin TOTP secret unreadable or invalid', [
                    'admin_id' => $adminId,
                    'exception' => $e::class,
                ]);

                return ['ok' => false, 'reason' => 'broken_secret', 'timestamp' => null];
            }

            if ($result !== false) {
                $affected = $this->compareAndSwapTimestamp($adminId, $storedOld, (int) $result);
                if ($affected === 1) {
                    return ['ok' => true, 'reason' => 'verified', 'timestamp' => (int) $result];
                }

                return ['ok' => false, 'reason' => 'replayed', 'timestamp' => null];
            }

            return ['ok' => false, 'reason' => 'invalid_code', 'timestamp' => null];
        });
    }

    /** Returns affected rows: 1 = this caller won, 0 = value already moved (replay/race). */
    public function compareAndSwapTimestamp(int $adminId, ?int $expectedOld, int $newTimestamp): int
    {
        // where(col, null) compiles to IS NULL, so a never-verified admin matches.
        return Admin::whereKey($adminId)
            ->where('totp_last_used_at', $expectedOld)
            ->update(['totp_last_used_at' => $newTimestamp]);
    }

    public function consumeRecoveryCode(int $adminId, string $code): array
    {
        $normalized = self::normalizeRecoveryCode($code);
        if ($normalized === '') {
            return ['ok' => false, 'timestamp' => null];
        }

        return DB::transaction(function () use ($adminId, $normalized): array {
            $admin = Admin::whereKey($adminId)->lockForUpdate()->first();
            if (! $admin || ! $admin->requiresTotp()) {
                return ['ok' => false, 'timestamp' => null];
            }

            foreach ($admin->totpRecoveryCodes()->get() as $record) {
                if (Hash::check($normalized, $record->code_hash)) {
                    $deleted = AdminTotpRecoveryCode::whereKey($record->id)->delete();
                    if ($deleted !== 1) {
                        return ['ok' => false, 'timestamp' => null];
                    }
                    // Bump marker so other devices re-enter challenge; max preserves monotonicity if clock moves backward.
                    $next = max($this->google2fa->getTimestamp(), (int) ($admin->totp_last_used_at ?? 0) + 1);
                    Admin::whereKey($adminId)->update(['totp_last_used_at' => $next]);

                    return ['ok' => true, 'timestamp' => $next];
                }
            }

            return ['ok' => false, 'timestamp' => null];
        });
    }
}
