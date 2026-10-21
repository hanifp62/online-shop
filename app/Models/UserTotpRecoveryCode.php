<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTotpRecoveryCode extends Model
{
    protected $fillable = ['code_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
