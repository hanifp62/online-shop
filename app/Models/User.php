<?php

namespace App\Models;

use App\Mail\RecipientLocale;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'mobile',
        'address1',
        'address2',
        'cart_reminder_opt_in',
        'locale',
    ];

    /** Cocokkan default kolom DB agar model baru tidak dianggap tanpa kata sandi. */
    protected $attributes = ['has_password' => true];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_last_used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'cart_reminder_opt_in' => 'boolean',
            'has_password' => 'boolean',
            'cart_reminded_at' => 'datetime',
            'totp_secret' => 'encrypted',
            'totp_enabled_at' => 'datetime',
            'totp_last_used_at' => 'integer',
        ];
    }

    /** Laravel memakai ini untuk notifikasi dan Mail::to($user), jadi email selalu ikut locale penerima. */
    public function preferredLocale(): string
    {
        return RecipientLocale::resolve($this->locale);
    }

    public function conciergeRequests(): HasMany
    {
        return $this->hasMany(ConciergeRequest::class);
    }

    public function totpRecoveryCodes(): HasMany
    {
        return $this->hasMany(UserTotpRecoveryCode::class);
    }

    public function hasTotp(): bool
    {
        return $this->totp_enabled_at !== null && $this->getRawOriginal('totp_secret') !== null;
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }
}
