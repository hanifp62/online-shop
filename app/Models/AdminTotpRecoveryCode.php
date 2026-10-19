<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTotpRecoveryCode extends Model
{
    protected $fillable = ['admin_id', 'code_hash'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
