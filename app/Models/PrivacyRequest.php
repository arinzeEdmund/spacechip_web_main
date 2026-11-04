<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyRequest extends Model
{
    public const TYPES = ['access' => 'Get a copy of my data', 'delete' => 'Delete my account and data', 'correct' => 'Correct my data', 'restrict' => 'Restrict how you use my data', 'object' => 'Object to processing / stop marketing', 'other' => 'Something else'];

    protected $guarded = [];

    protected $casts = ['due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->due_at->isPast();
    }
}
