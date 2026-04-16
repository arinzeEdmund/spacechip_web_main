<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $casts = [
        'meta' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'direction',
        'action',
        'amount_minor',
        'balance_before_minor',
        'balance_after_minor',
        'currency',
        'payment_id',
        'meta',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
