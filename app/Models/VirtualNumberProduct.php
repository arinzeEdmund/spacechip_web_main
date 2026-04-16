<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VirtualNumberProduct extends Model
{
    protected $casts = [
        'cap_sms' => 'bool',
        'cap_voice' => 'bool',
        'active' => 'bool',
        'twilio_search_filters' => 'array',
    ];

    protected $fillable = [
        'country_iso',
        'label',
        'cap_sms',
        'cap_voice',
        'currency',
        'monthly_amount_minor',
        'twilio_search_filters',
        'active',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(VirtualNumberSubscription::class);
    }
}
