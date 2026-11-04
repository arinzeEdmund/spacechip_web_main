<?php

namespace App\Services\Admin;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runtime application settings editable from the admin panel.
 *
 * Each definition may map onto a config key; overrides are applied at boot
 * (see AppServiceProvider) so existing services pick them up unchanged.
 */
class SettingsService
{
    private const CACHE_KEY = 'app_settings.v1';

    /** @return array<string, array{group:string,label:string,help:string,type:string,default:mixed,config?:string,min?:float,max?:float,step?:float}> */
    public static function definitions(): array
    {
        return [
            'maintenance_mode' => ['group' => 'Availability', 'label' => 'Maintenance mode', 'help' => 'Customers see a maintenance message on web and mobile. Admins, logins and payment webhooks keep working.', 'type' => 'bool', 'default' => false],
            'maintenance_message' => ['group' => 'Availability', 'label' => 'Maintenance message', 'help' => 'Shown to customers while maintenance mode is on.', 'type' => 'text', 'default' => 'We’re making improvements and will be back shortly.'],
            'registrations_enabled' => ['group' => 'Availability', 'label' => 'New sign-ups', 'help' => 'Allow new accounts to register on web and mobile.', 'type' => 'bool', 'default' => true],

            'social_rental_provider' => ['group' => 'Providers', 'label' => 'Monthly rental supplier', 'help' => 'Which supplier sells NEW monthly social-number rentals. Only one can be on at a time; turning one on turns the other off. Existing rentals keep renewing with the supplier they were bought from.', 'type' => 'choice', 'default' => 'smspool', 'options' => ['smspool' => 'SMSPool', 'longsms' => 'LongSMS (cheaper)']],

            'feature_esim_purchases' => ['group' => 'Features', 'label' => 'eSIM purchases', 'help' => 'Card, wallet and crypto checkout for eSIM plans.', 'type' => 'bool', 'default' => true],
            'feature_virtual_numbers' => ['group' => 'Features', 'label' => 'Virtual numbers', 'help' => 'New monthly virtual-number subscriptions.', 'type' => 'bool', 'default' => true],
            'feature_social_numbers' => ['group' => 'Features', 'label' => 'Social numbers', 'help' => 'One-time verification numbers and monthly rentals.', 'type' => 'bool', 'default' => true],
            'feature_wallet_deposits' => ['group' => 'Features', 'label' => 'Wallet deposits', 'help' => 'Paystack top-ups of the Reserve wallet.', 'type' => 'bool', 'default' => true],

            'smspool_markup_multiplier' => ['group' => 'Pricing', 'label' => 'Social number markup', 'help' => 'Sell price = provider cost × this multiplier.', 'type' => 'number', 'default' => null, 'config' => 'services.smspool.markup_multiplier', 'min' => 1, 'max' => 5, 'step' => 0.01],
            'smspool_rent_markup_multiplier' => ['group' => 'Pricing', 'label' => 'Rental markup', 'help' => 'Monthly rental sell price = provider cost × this multiplier.', 'type' => 'number', 'default' => null, 'config' => 'services.smspool.rent_markup_multiplier', 'min' => 1, 'max' => 5, 'step' => 0.01],
            'longsms_monthly_cost_usd' => ['group' => 'Pricing', 'label' => 'LongSMS monthly cost (USD)', 'help' => 'What LongSMS charges YOU for one 30-day number. LongSMS does not tell us this price over its API, so enter it here (overrides LONGSMS_MONTHLY_COST_USD in .env). The rental markup above is added on top for the customer price.', 'type' => 'number', 'default' => null, 'config' => 'services.longsms.monthly_cost_usd', 'min' => 0, 'max' => 1000, 'step' => 0.01],
            'esim_ngn_rate_markup' => ['group' => 'Pricing', 'label' => 'eSIM NGN rate markup', 'help' => 'Multiplier applied to the USD→NGN rate used for naira checkout.', 'type' => 'number', 'default' => null, 'config' => 'services.exchange_rates.esim_markup_multiplier', 'min' => 1, 'max' => 2, 'step' => 0.005],

            'provider_low_balance_usd' => ['group' => 'Alerts', 'label' => 'Low provider balance alert (USD)', 'help' => 'Providers below this balance are flagged on the dashboard.', 'type' => 'number', 'default' => 20, 'min' => 0, 'max' => 100000, 'step' => 1],
            'stale_payment_minutes' => ['group' => 'Alerts', 'label' => 'Stuck payment threshold (minutes)', 'help' => 'Paid-but-unfulfilled orders older than this need attention.', 'type' => 'number', 'default' => 30, 'min' => 5, 'max' => 1440, 'step' => 5],
        ];
    }

    /** @return array<string, mixed> stored values keyed by setting key (no defaults applied) */
    public function stored(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            try {
                if (! Schema::hasTable('app_settings')) {
                    return [];
                }

                return AppSetting::query()->pluck('value', 'key')
                    ->map(fn ($v) => json_decode((string) $v, true))
                    ->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    public function get(string $key): mixed
    {
        $def = self::definitions()[$key] ?? null;
        $stored = $this->stored();
        if (array_key_exists($key, $stored) && $stored[$key] !== null) {
            return $stored[$key];
        }
        if ($def && isset($def['config'])) {
            return config($def['config'], $def['default']);
        }

        return $def['default'] ?? null;
    }

    public function enabled(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): array
    {
        $changes = [];
        foreach (self::definitions() as $key => $def) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $new = $this->cast($def['type'], $values[$key]);
            $old = $this->get($key);
            if ($new === $old) {
                continue;
            }
            AppSetting::updateOrCreate(['key' => $key], ['value' => json_encode($new), 'updated_by' => Auth::id()]);
            $changes[$key] = ['from' => $old, 'to' => $new];
        }
        Cache::forget(self::CACHE_KEY);

        return $changes;
    }

    /** Push numeric overrides into config so services read them transparently. */
    public function applyConfigOverrides(): void
    {
        $stored = $this->stored();
        foreach (self::definitions() as $key => $def) {
            if (isset($def['config']) && isset($stored[$key]) && $stored[$key] !== null) {
                config([$def['config'] => $stored[$key]]);
            }
        }
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'number' => $value === null || $value === '' ? null : (float) $value,
            default => trim((string) $value),
        };
    }
}
