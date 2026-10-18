<?php

namespace App\Services;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class PasswordResetTokenIssuer
{
    public function __construct(private readonly AuthEventRecorder $events) {}

    public function issue(CanResetPassword $user): string
    {
        $token = Password::broker('users')->createToken($user);
        try {
            $id = $user->getAuthIdentifier();
            $this->events->recordPasswordResetTokenIssued(is_numeric($id) ? (int) $id : null, request()?->ip());
        } catch (\Throwable $e) {
            // Token issuance must succeed even if audit recording fails; never log the token or message.
            try {
                Log::warning('Password reset audit failed', ['exception' => $e::class]);
            } catch (\Throwable) {
            }
        }
        return $token;
    }
}
