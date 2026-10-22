<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDeadLetter extends Model
{
    public const MAX_REPLAY_ATTEMPTS = 10;

    public const REPLAY_BATCH_SIZE = 50;

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

    /** Exhausted rows are quarantined, not resolved: reservation holds remain in force. */
    public function scopeEligibleForReplay(Builder $query): Builder
    {
        return $query->where('provider', 'midtrans')->whereNull('resolved_at')
            ->where('attempts', '<', self::MAX_REPLAY_ATTEMPTS);
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
