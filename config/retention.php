<?php

/** How long message content is kept before messages:purge removes it (days). */
return [
    'social_otp_days' => (int) env('RETENTION_SOCIAL_OTP_DAYS', 90),
    'rental_sms_days' => (int) env('RETENTION_RENTAL_SMS_DAYS', 90),
    'virtual_message_days' => (int) env('RETENTION_VIRTUAL_MESSAGE_DAYS', 365),
];
