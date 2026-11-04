<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checkout attempt for any product (eSIM, wallet deposit, number) through any provider.
 * `provider_payload` holds what was sold; `fulfillment_payload` holds what was delivered.
 */
class Payment extends Model
{
    protected $casts = [
        'provider_payload' => 'array',
        // Holds eSIM activation data (LPA / QR payload / ICCID): encrypted at rest, readable through the model.
        'fulfillment_payload' => \App\Casts\EncryptedJson::class,
    ];

    protected static function booted(): void
    {
        // Where the buyer was (from the CDN/host country header). One piece of location evidence for VAT/sales-tax.
        static::creating(function (Payment $payment) {
            if ($payment->ip_country === null && app()->bound('request')) {
                $req = request();
                $c = strtoupper((string) ($req->header('CF-IPCountry') ?: $req->header('X-Vercel-IP-Country') ?: $req->header('CloudFront-Viewer-Country') ?: ''));
                $payment->ip_country = preg_match('/^[A-Z]{2}$/', $c) ? $c : null;
            }
        });

        // Keep the searchable blind index of the ICCID in step with the (encrypted) payload.
        static::saving(function (Payment $payment) {
            if ($payment->isDirty('fulfillment_payload') || $payment->iccid_index === null) {
                $iccid = data_get($payment->fulfillment_payload, 'iccid');
                $payment->iccid_index = is_string($iccid) && $iccid !== '' ? static::iccidIndex($iccid) : null;
            }
        });
    }

    /** Keyed hash used to look an order up by ICCID without storing the ICCID in a searchable column. */
    public static function iccidIndex(string $iccid): string
    {
        return hash_hmac('sha256', strtolower(trim($iccid)), (string) config('app.key'));
    }

    protected $fillable = [
        'user_id',
        'provider',
        'provider_reference',
        'status',
        'asset_type',
        'asset_id',
        'bundle_id',
        'package_type',
        'currency',
        'amount_minor',
        'provider_payload',
        'fulfillment_payload',
        'iccid_index',
        'ip_country',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEsim(): bool
    {
        return in_array($this->asset_type, ['country', 'region', 'global'], true);
    }

    /** Human product type, e.g. "eSIM", "Verification number", "Wallet top-up". */
    public function productTypeLabel(): string
    {
        return match ($this->asset_type) {
            'country', 'region', 'global' => 'eSIM · '.$this->asset_type,
            'social_number' => 'Verification number',
            'social_number_rental' => 'Number rental',
            'virtual_number' => 'Virtual number',
            'wallet_deposit' => 'Wallet top-up',
            default => ucfirst(str_replace('_', ' ', (string) $this->asset_type)),
        };
    }

    /** What was bought, e.g. "United States", "TikTok", "Wallet top-up". */
    public function displayTitle(): string
    {
        return (string) (data_get($this->provider_payload, 'asset.name')
            ?: data_get($this->provider_payload, 'quote.app.name')
            ?: data_get($this->provider_payload, 'app.name')
            ?: data_get($this->provider_payload, 'product_name')
            ?: $this->productTypeLabel());
    }

    /** Secondary detail: plan for eSIMs, number for social numbers. */
    public function displaySubtitle(): string
    {
        if ($this->isEsim()) {
            return (string) (data_get($this->provider_payload, 'bundle.name')
                ?: collect([data_get($this->provider_payload, 'bundle.data'), data_get($this->provider_payload, 'bundle.validity')])->filter()->implode(' · ')
                ?: $this->bundle_id);
        }
        if ($phone = data_get($this->fulfillment_payload, 'phone')) {
            return '+'.ltrim((string) $phone, '+');
        }

        return $this->productTypeLabel();
    }
}
