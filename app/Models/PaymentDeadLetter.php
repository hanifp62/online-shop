<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDeadLetter extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'reason', 'transaction_id',
        'attempts', 'raw_body', 'last_attempted_at', 'resolved_at',
    ];

    protected $hidden = ['raw_body'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            // Signature di body dapat diputar ulang; simpan terenkripsi (APP_PREVIOUS_KEYS saat rotasi).
            'raw_body' => 'encrypted',
            'last_attempted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function markResolved(): void
    {
        $this->update(['resolved_at' => now()]);
    }
}
