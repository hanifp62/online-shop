<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_DANA_DIKIRIM = 'dana_dikirim';

    public const STATUS_DITERIMA = 'diterima';

    public const STATUS_FLOW = [
        self::STATUS_DIAJUKAN => [self::STATUS_DISETUJUI, self::STATUS_DITOLAK],
        self::STATUS_DISETUJUI => [self::STATUS_DANA_DIKIRIM],
        self::STATUS_DANA_DIKIRIM => [self::STATUS_DITERIMA],
        self::STATUS_DITOLAK => [],
        self::STATUS_DITERIMA => [],
    ];

    protected $fillable = [
        'order_id',
        'return_id',
        'user_id',
        'refund_number',
        'amount',
        'status',
        'reason',
        'provider',
        'provider_refund_id',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
        'dispatched_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::STATUS_FLOW[$this->status] ?? [], true);
    }

    /** Label status sesuai locale aktif; status tak dikenal dikembalikan apa adanya. */
    public static function statusLabel(?string $status): ?string
    {
        return array_key_exists((string) $status, self::STATUS_FLOW)
            ? __("customer_messages.refund.status_labels.$status")
            : $status;
    }

    public static function generateRefundNumber(int $orderId): string
    {
        return sprintf('REF-%s-%04d-%s', now()->format('Ymd'), $orderId, strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
    }
}
