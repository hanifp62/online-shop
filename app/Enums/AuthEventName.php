<?php

namespace App\Enums;

final class AuthEventName
{
    public const ENROL_CONFIRMED = 'ENROL_CONFIRMED';

    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';

    public const LOGIN_FAILURE = 'LOGIN_FAILURE';

    public const TOTP_FAILURE = 'TOTP_FAILURE';

    public const RECOVERY_CODE_USED = 'RECOVERY_CODE_USED';

    public const LOCKOUT_TRIGGERED = 'LOCKOUT_TRIGGERED';

    public const SESSION_ROTATED = 'SESSION_ROTATED';

    public const PASSWORD_RESET_TOKEN_ISSUED = 'PASSWORD_RESET_TOKEN_ISSUED';

    public const ALL = [
        self::ENROL_CONFIRMED,
        self::LOGIN_SUCCESS,
        self::LOGIN_FAILURE,
        self::TOTP_FAILURE,
        self::RECOVERY_CODE_USED,
        self::LOCKOUT_TRIGGERED,
        self::SESSION_ROTATED,
        self::PASSWORD_RESET_TOKEN_ISSUED,
    ];
}
