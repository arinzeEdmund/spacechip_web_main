<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $casts = [
        'provider_payload' => 'array',
        'fulfillment_payload' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'provider_reference',
        'status',
        'asset_type',
        'asset_id',
        'bundle_id',
        'package_type',
        'currency',
        'amount_minor',
        'provider_payload',
        'fulfillment_payload',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

