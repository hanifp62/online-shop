<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
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

    public function totpRecoveryCodes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdminTotpRecoveryCode::class);
    }

    public function requiresTotp(): bool
    {
        return $this->totp_required_at !== null && $this->getRawOriginal('totp_secret') !== null;
    }
}
