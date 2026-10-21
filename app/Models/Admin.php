<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STAFF = 'staff';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_STAFF];

    /** Sama dengan default kolom; tanpa ini model baru dibuat punya role null di memori. */
    protected $attributes = ['role' => self::ROLE_ADMIN];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'totp_secret',
        'totp_required_at',
        'totp_last_used_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'totp_required_at' => 'datetime',
            'totp_last_used_at' => 'integer',
        ];
    }

    public function totpRecoveryCodes(): HasMany
    {
        return $this->hasMany(AdminTotpRecoveryCode::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function requiresTotp(): bool
    {
        return $this->totp_required_at !== null && $this->getRawOriginal('totp_secret') !== null;
    }
}
