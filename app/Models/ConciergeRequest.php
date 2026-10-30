<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConciergeRequest extends Model
{
    public const TRANSITIONS = [
        'new' => ['new', 'contacted', 'confirmed', 'cancelled'],
        'contacted' => ['contacted', 'confirmed', 'cancelled'],
        'confirmed' => ['confirmed', 'completed', 'cancelled'],
        'completed' => ['completed'],
        'cancelled' => ['cancelled'],
    ];

    public function availableTransitions(): array
    {
        return in_array($this->status, ['completed', 'cancelled'], true)
            ? []
            : (self::TRANSITIONS[$this->status] ?? []);
    }

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'service',
        'preferred_date',
        'preferred_time',
        'notes',
        'source',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
        ];
    }

    // Shown to the client after a request so staff can find it by ID.
    public function reference(): string
    {
        return 'ZC-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
