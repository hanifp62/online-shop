<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'occurred_at',
        'actor_type',
        'actor_id',
        'event',
        'outcome',
        'ip',
        'request_id',
        'context',
    ];

    /** Append-only: baris audit tidak boleh diubah atau dihapus per baris. Retensi lewat `auth-events:prune` (hapus massal). */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Auth events are append-only.'));
        static::deleting(fn () => throw new \LogicException('Auth events are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'actor_id' => 'integer',
            'context' => 'array',
        ];
    }
}
