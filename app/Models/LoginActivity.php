<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sign-in attempt (web, social or API), successful or not.
 */
class LoginActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'channel', 'successful', 'ip_address', 'user_agent', 'device'];

    protected $casts = ['successful' => 'bool'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
