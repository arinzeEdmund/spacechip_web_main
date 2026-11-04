<?php

namespace App\Support;

/**
 * Formatting for amounts stored in minor units (cents/kobo).
 */
final class Money
{
    /** Format minor units, e.g. Money::format(1234, 'USD') → "$12.34". */
    public static function format(int|float|null $minor, ?string $currency = 'USD'): string
    {
        $currency = strtoupper((string) ($currency ?: 'USD'));
        $amount = ((float) $minor) / 100;
        $symbol = ['USD' => '$', 'NGN' => '₦', 'EUR' => '€', 'GBP' => '£'][$currency] ?? '';

        return $symbol !== ''
            ? ($amount < 0 ? '−' : '').$symbol.number_format(abs($amount), 2)
            : number_format($amount, 2).' '.$currency;
    }

    public static function usd(int|float|null $minor): string
    {
        return self::format($minor, 'USD');
    }
}
