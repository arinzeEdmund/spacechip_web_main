<?php

namespace App\Services;

use App\Contracts\RentalProvider;
use App\Services\Admin\SettingsService;

/**
 * Picks the monthly-rental supplier. Exactly one supplier is active for NEW rentals (admin setting
 * `social_rental_provider`); existing rentals keep using the supplier they were bought from.
 */
class RentalProviders
{
    public const SMSPOOL = 'smspool';

    public const LONGSMS = 'longsms';

    public function __construct(
        private SettingsService $settings,
        private SmsPoolRentService $smsPool,
        private LongSmsRentService $longSms,
    ) {}

    public function activeKey(): string
    {
        return $this->settings->get('social_rental_provider') === self::LONGSMS ? self::LONGSMS : self::SMSPOOL;
    }

    /** Supplier for new rentals and for browsing apps / countries / prices. */
    public function active(): RentalProvider
    {
        return $this->activeKey() === self::LONGSMS ? $this->longSms : $this->smsPool;
    }

    /** Supplier an existing rental was bought from (its stored `provider` value). Legacy values map to SMSPool. */
    public function for(?string $storedProvider): RentalProvider
    {
        return $storedProvider === LongSmsRentService::KEY ? $this->longSms : $this->smsPool;
    }

    public function isConfigured(string $key): bool
    {
        return $key === self::LONGSMS ? $this->longSms->isConfigured() : $this->smsPool->isConfigured();
    }
}
