<?php

namespace App\Contracts;

use Carbon\Carbon;

/**
 * A supplier of monthly social-number rentals (SMSPool, LongSMS, ...).
 *
 * Every method returns the same normalised envelope the SMSPool rental
 * integration always used, so controllers and the renewal command never need to
 * know which supplier is behind a rental. `key()` is the value stored in
 * `social_number_rentals.provider`.
 */
interface RentalProvider
{
    public function key(): string;

    public function isConfigured(): bool;

    public function apps(): array;

    public function app(string $key): ?array;

    public function countries(): array;

    public function country(string $code): ?array;

    public function quote(string $product, string $country, string $provider = 'all_providers', string $dtype = 'month', int $dcount = 1): array;

    public function create(string $service, string $country, string $dtype = 'month', int $dcount = 1, ?string $provider = null): array;

    public function activate(string|int $id): array;

    public function sms(string|int $id): array;

    public function prolong(string|int $id, string $dtype = 'month', int $dcount = 1, int $days = 30): array;

    public function sellAmountMinor(int $providerCostMinor): int;

    public function formatUsd(int $amountMinor): string;

    public function timestampToCarbon(mixed $value): ?Carbon;
}
