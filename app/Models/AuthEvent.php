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
        'context',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'actor_id' => 'integer',
            'context' => 'array',
        ];
    }
}
