<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInfoBackup extends Model
{
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'address1',
        'address2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
