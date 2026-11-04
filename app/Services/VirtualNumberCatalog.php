<?php

namespace App\Services;

use App\Models\VirtualNumberProduct;

/**
 * What we sell as virtual numbers and for how much.
 *
 * Every country Twilio stocks is sellable: a default product is created the first time a
 * country is viewed. Prices are Twilio's live monthly rate for the chosen number type when
 * available, otherwise the product's list price.
 */
class VirtualNumberCatalog
{
    public const NUMBER_TYPES = ['Local', 'Mobile', 'TollFree'];

    private const COUNTRIES_CACHE_KEY = 'twilio.countries.v2';

    public function __construct(private TwilioService $twilio) {}

    /** A valid number type from user input, or '' for "any". */
    public static function numberType(mixed $raw): string
    {
        $type = trim((string) $raw);

        return in_array($type, self::NUMBER_TYPES, true) ? $type : '';
    }

    /**
     * Twilio's country list, cached 3 days. Failures are not cached (so a Twilio blip doesn't
     * empty the catalogue); they return null with the reason in `$error`.
     */
    public function countries(?string &$error = null): ?array
    {
        $items = cache()->get(self::COUNTRIES_CACHE_KEY);
        if (is_array($items)) {
            return $items;
        }

        $res = $this->twilio->availableCountries();
        if (($res['ok'] ?? false) !== true) {
            $error = (string) ($res['error'] ?? 'Unable to fetch countries.');

            return null;
        }

        $items = is_array($res['items'] ?? null) ? $res['items'] : [];
        cache()->put(self::COUNTRIES_CACHE_KEY, $items, now()->addDays(3));

        return $items;
    }

    public function countryName(string $iso, ?array $countries = null): string
    {
        $row = collect($countries ?? $this->countries() ?? [])
            ->first(fn ($r) => is_array($r) && strtoupper((string) ($r['country_code'] ?? '')) === $iso);

        return (string) ($row['country_name'] ?? $iso);
    }

    /** @return array{0: string, 1: int} [currency, monthly minor units] from config/virtual_numbers.php */
    public function defaultPrice(): array
    {
        $currency = strtoupper(trim((string) config('virtual_numbers.default_currency', 'USD'))) ?: 'USD';
        $amount = (int) config('virtual_numbers.default_monthly_amount_minor', 500);

        return [$currency, $amount > 0 ? $amount : 500];
    }

    /** The cheapest active product for a country, creating the default one if none exists. */
    public function productFor(string $iso, string $countryName): VirtualNumberProduct
    {
        [$currency, $amount] = $this->defaultPrice();

        return VirtualNumberProduct::query()->where('active', true)->where('country_iso', $iso)->orderBy('monthly_amount_minor')->first()
            ?? VirtualNumberProduct::create([
                'country_iso' => $iso,
                'label' => $countryName.' Local Number',
                'cap_sms' => true,
                'cap_voice' => true,
                'currency' => $currency,
                'monthly_amount_minor' => $amount,
                'twilio_search_filters' => [],
                'active' => true,
            ]);
    }

    /**
     * Monthly price for a product and number type.
     *
     * @return array{0: string, 1: int, 2: bool} [currency, minor units, whether it's Twilio's live rate]
     */
    public function monthlyPrice(VirtualNumberProduct $product, string $numberType): array
    {
        $currency = strtoupper((string) ($product->currency ?? 'USD'));
        $minor = (int) $product->monthly_amount_minor;

        if ($numberType === '' || ! $this->twilio->isConfigured()) {
            return [$currency, $minor, false];
        }

        $pricing = $this->twilio->phoneNumberPricing((string) $product->country_iso);
        $live = ($pricing['ok'] ?? false) === true ? data_get($pricing, "prices_minor.{$numberType}") : null;
        if (! is_numeric($live) || (int) $live <= 0) {
            return [$currency, $minor, false];
        }

        return [strtoupper(trim((string) ($pricing['currency'] ?? ''))) ?: $currency, (int) $live, true];
    }

    /** @return array{0: string, 1: int}|null Twilio's cheapest number type in a country, if priced. */
    public function cheapestLivePrice(string $iso, string $fallbackCurrency): ?array
    {
        $pricing = $this->twilio->phoneNumberPricing($iso);
        if (($pricing['ok'] ?? false) !== true || ! is_array($pricing['prices_minor'] ?? null)) {
            return null;
        }

        $mins = array_filter(array_map(fn ($v) => is_numeric($v) ? (int) $v : null, $pricing['prices_minor']));

        return $mins ? [strtoupper((string) ($pricing['currency'] ?? $fallbackCurrency)) ?: $fallbackCurrency, min($mins)] : null;
    }
}
