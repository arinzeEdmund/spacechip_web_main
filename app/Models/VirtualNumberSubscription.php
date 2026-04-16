<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualNumberSubscription extends Model
{
    protected $casts = [
        'cap_sms' => 'bool',
        'cap_voice' => 'bool',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'virtual_number_product_id',
        'status',
        'phone_number',
        'country_iso',
        'cap_sms',
        'cap_voice',
        'provider',
        'billing_method',
        'twilio_phone_number_sid',
        'currency',
        'monthly_amount_minor',
        'current_period_start',
        'current_period_end',
        'paystack_customer_code',
        'paystack_authorization_code',
        'paystack_email',
        'forward_to_email',
        'forward_to_phone',
        'last_charge_reference',
        'renewal_failed_count',
        'last_renewal_error',
        'canceled_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(VirtualNumberProduct::class, 'virtual_number_product_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(VirtualNumberMessage::class);
    }
}
