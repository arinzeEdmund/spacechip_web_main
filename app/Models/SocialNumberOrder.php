<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialNumberOrder extends Model
{
    protected $casts = [
        'provider_payload' => 'array',
        'ordered_at' => 'datetime',
        'sms_received_at' => 'datetime',
        'finished_at' => 'datetime',
        'canceled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'payment_id',
        'provider',
        'provider_order_id',
        'status',
        'product',
        'product_name',
        'service_code',
        'country',
        'country_name',
        'operator',
        'phone',
        'sms_code',
        'sms_text',
        'sms_sender',
        'provider_cost_minor',
        'sell_amount_minor',
        'currency',
        'provider_payload',
        'ordered_at',
        'sms_received_at',
        'finished_at',
        'canceled_at',
        'refunded_at',
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
