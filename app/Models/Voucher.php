<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'code', 'discount_amount', 'quota', 'used', 'per_user_limit', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'quota' => 'integer',
            'used' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
