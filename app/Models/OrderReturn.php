<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    protected $table = 'returns';

    public const WINDOW_DAYS = 7;

    public const REASONS = [
        'damaged' => 'Barang rusak / cacat produksi',
        'wrong_item' => 'Barang tidak sesuai pesanan',
        'not_as_described' => 'Ukuran / warna berbeda signifikan dari deskripsi',
        'defective' => 'Fungsi atau jahitan cacat',
        'other' => 'Alasan lain',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'order_id',
        'user_id',
        'return_number',
        'status',
        'reason',
        'notes',
        'requested_refund_amount',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_refund_amount' => 'integer',
            'reviewed_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    /** Label alasan sesuai locale aktif; kode REASONS tetap menjadi nilai tersimpan dan aturan validasi. */
    public static function reasons(): array
    {
        return array_combine(
            array_keys(self::REASONS),
            array_map(fn (string $code) => __("customer_messages.returns.reasons.$code"), array_keys(self::REASONS)),
        );
    }

    public static function generateReturnNumber(int $orderId): string
    {
        return sprintf('RET-%s-%04d-%s', now()->format('Ymd'), $orderId, strtoupper(substr(bin2hex(random_bytes(2)), 0, 4)));
    }
}
