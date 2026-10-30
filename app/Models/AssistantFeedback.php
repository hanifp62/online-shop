<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Anonymous "was this helpful?" votes; no user, session or message text is stored.
class AssistantFeedback extends Model
{
    protected $table = 'assistant_feedback';

    protected $fillable = ['topic', 'helpful', 'locale'];

    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }
}
