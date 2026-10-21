<?php

namespace App\Services;

use App\Enums\AuthEventName;
use App\Models\AuthEvent;
use Illuminate\Support\Facades\Log;

class AuthEventRecorder
{
    private const ALLOWED_GUARDS = ['admin', 'web'];

    private const ALLOWED_REASONS = [
        'invalid_credentials',
        'invalid_code',
        'replayed',
        'locked',
        'corrupt_secret',
        'not_required',
        'operational_error',
    ];

    private const ALLOWED_SCOPES = [
        'account',
        'ip',
        'totp',
        'enrol',
        'login',
        'recovery',
        'logout',
        'password_update',
        'password_reset',
    ];

    public function record(
        string $event,
        string $outcome,
        ?string $actorType,
        ?int $actorId,
        ?string $ip,
        array $context = []
    ): ?AuthEvent {
        if (! in_array($event, AuthEventName::ALL, true)) {
            return null;
        }
        if (! in_array($outcome, ['success', 'failure'], true)) {
            return null;
        }
        if ($actorType !== null && ! in_array($actorType, self::ALLOWED_GUARDS, true)) {
            $actorType = null;
        }

        $safeContext = $this->sanitizeContext($context);

        try {
            return AuthEvent::create([
                'occurred_at' => now(),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'event' => $event,
                'outcome' => $outcome,
                'ip' => $ip !== null ? substr($ip, 0, 45) : null,
                'request_id' => $this->resolveRequestId(),
                'context' => (object) $safeContext,
            ]);
        } catch (\Throwable $e) {
            try {
                Log::critical('Auth event recorder failed', [
                    'event' => $event,
                    'exception' => $e::class,
                ]);
            } catch (\Throwable) {
                // Audit and logging failures must not block authentication.
            }

            return null;
        }
    }

    public function sanitizeContext(array $context): array
    {
        $out = [];
        if (isset($context['reason']) && is_string($context['reason']) && in_array($context['reason'], self::ALLOWED_REASONS, true)) {
            $out['reason'] = $context['reason'];
        }
        if (isset($context['guard']) && is_string($context['guard']) && in_array($context['guard'], self::ALLOWED_GUARDS, true)) {
            $out['guard'] = $context['guard'];
        }
        if (isset($context['scope']) && is_string($context['scope']) && in_array($context['scope'], self::ALLOWED_SCOPES, true)) {
            $out['scope'] = $context['scope'];
        }

        return $out;
    }

    public function recordLoginSuccess(?int $actorId, ?string $ip, string $guard = 'admin', string $scope = 'login'): ?AuthEvent
    {
        return $this->record(AuthEventName::LOGIN_SUCCESS, 'success', $guard, $actorId, $ip, ['guard' => $guard, 'scope' => $scope]);
    }

    public function recordLoginFailure(?int $actorId, ?string $ip, string $reason = 'invalid_credentials', string $guard = 'admin'): ?AuthEvent
    {
        return $this->record(AuthEventName::LOGIN_FAILURE, 'failure', $guard, $actorId, $ip, ['reason' => $reason, 'guard' => $guard]);
    }

    public function recordTotpFailure(?int $actorId, ?string $ip, string $reason = 'invalid_code'): ?AuthEvent
    {
        $mapped = match ($reason) {
            'broken_secret' => 'corrupt_secret',
            default => $reason,
        };

        return $this->record(AuthEventName::TOTP_FAILURE, 'failure', 'admin', $actorId, $ip, ['reason' => $mapped, 'guard' => 'admin']);
    }

    public function recordRecoveryUsed(int $actorId, ?string $ip): ?AuthEvent
    {
        return $this->record(AuthEventName::RECOVERY_CODE_USED, 'success', 'admin', $actorId, $ip, ['guard' => 'admin']);
    }

    public function recordLockout(?string $actorType, ?int $actorId, ?string $ip, string $scope = 'account'): ?AuthEvent
    {
        return $this->record(AuthEventName::LOCKOUT_TRIGGERED, 'failure', $actorType, $actorId, $ip, ['reason' => 'locked', 'scope' => $scope]);
    }

    public function recordSessionRotated(?string $actorType, ?int $actorId, ?string $ip, string $scope = 'login'): ?AuthEvent
    {
        return $this->record(AuthEventName::SESSION_ROTATED, 'success', $actorType, $actorId, $ip, ['scope' => $scope]);
    }

    public function recordEnrolConfirmed(int $actorId, ?string $ip): ?AuthEvent
    {
        return $this->record(AuthEventName::ENROL_CONFIRMED, 'success', 'admin', $actorId, $ip, ['guard' => 'admin']);
    }

    public function recordPasswordResetTokenIssued(?int $actorId, ?string $ip): ?AuthEvent
    {
        return $this->record(AuthEventName::PASSWORD_RESET_TOKEN_ISSUED, 'success', 'web', $actorId, $ip, ['guard' => 'web', 'scope' => 'password_reset']);
    }

    /** Hanya ID yang sudah disanitasi CorrelationIdMiddleware; header mentah tidak pernah dipercaya. */
    private function resolveRequestId(): ?string
    {
        $id = request()?->attributes->get('request_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
