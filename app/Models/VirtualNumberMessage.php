<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualNumberMessage extends Model
{
    protected $casts = [
        'raw' => 'array',
        'media' => 'array',
    ];

    protected $fillable = [
        'virtual_number_subscription_id',
        'direction',
        'message_type',
        'from',
        'to',
        'body',
        'twilio_message_sid',
        'twilio_call_sid',
        'twilio_recording_sid',
        'recording_url',
        'recording_duration',
        'media',
        'raw',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VirtualNumberSubscription::class, 'virtual_number_subscription_id');
    }
}
