<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialNumberRental extends Model
{
    protected $casts = [
        'provider_payload' => 'array',
        'sms_messages' => 'array',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'activated_at' => 'datetime',
        'last_sms_sync_at' => 'datetime',
        'last_renewed_at' => 'datetime',
        'canceled_at' => 'datetime',
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
        'provider_name',
        'phone',
        'phone_country_code',
        'provider_cost_minor',
        'monthly_amount_minor',
        'currency',
        'auto_renew',
        'current_period_start',
        'current_period_end',
        'activated_at',
        'last_sms_sync_at',
        'last_renewed_at',
        'renewal_failed_count',
        'last_renewal_error',
        'last_charge_reference',
        'sms_messages',
        'provider_payload',
        'canceled_at',
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
