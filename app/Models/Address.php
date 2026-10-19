<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone',
        'line1', 'line2', 'city', 'province', 'postal_code', 'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Snapshot alamat agar perubahan data profil tidak mengubah riwayat pesanan. */
    public function snapshot(): array
    {
        return $this->only([
            'label', 'recipient_name', 'phone', 'line1', 'line2', 'city', 'province', 'postal_code',
        ]);
    }
}
