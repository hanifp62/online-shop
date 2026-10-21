<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Jejak audit append-only: baris tidak boleh diubah atau dihapus lewat model. */
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'from_status', 'to_status', 'admin_id'];

    protected static function booted(): void
    {
        $immutable = fn () => throw new LogicException('Riwayat status pesanan tidak dapat diubah atau dihapus.');
        static::updating($immutable);
        static::deleting($immutable);
    }
}
