<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        if ($orderId < 1) {
            throw new \InvalidArgumentException('Refund order ID must be positive.');
        }
        // Exact integer base36 (no floating-point base_convert precision loss).
        $digits = '0123456789abcdefghijklmnopqrstuvwxyz';
        $namespace = '';
        $remaining = $orderId;
        do {
            $namespace = $digits[$remaining % 36].$namespace;
            $remaining = intdiv($remaining, 36);
        } while ($remaining > 0);

        // Full UUID bytes, not a truncated prefix: 13 + 22 + separators fits varchar(40).
        $uuid = hex2bin(str_replace('-', '', (string) Str::uuid()));
        $token = rtrim(strtr(base64_encode($uuid), '+/', '-_'), '=');

        return 'REF-'.$namespace.'-'.$token;
    }
}
