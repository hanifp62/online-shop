<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Ledger pembayaran append-only: sumber kebenaran status lunas. */
class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'provider_transaction_id', 'provider_order_id',
        'event_status', 'amount', 'outcome', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        $immutable = fn () => throw new LogicException('Ledger pembayaran tidak dapat diubah atau dihapus.');
        static::updating($immutable);
        static::deleting($immutable);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
