<?php

namespace App\Support;

use App\Contracts\RentalProvider;
use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Services\SmsPoolService;
use App\Services\WalletService;

/**
 * Shared helpers for the SMSPool social-number products (one-time
 * activations and monthly rentals).
 *
 * Previously defined as global functions inside routes/web.php, which made
 * them disappear whenever routes were cached (`php artisan route:cache`).
 */
final class SocialNumbers
{
    /** SMSPool numbers live 10 minutes; orders are expired slightly earlier so the refund is safe. */
    public const OTP_LIFETIME_SECONDS = 580;

    /**
     * Shape a one-time social number order for API responses.
     */
    public static function orderPayload(SocialNumberOrder $order, SmsPoolService $smsPool): array
    {
        $sms = [];
        $orderedAt = $order->ordered_at ?: $order->created_at;
        $expiresAt = $orderedAt?->copy()->addSeconds(self::OTP_LIFETIME_SECONDS);
        $phone = trim((string) ($order->phone ?? ''));
        $country = $smsPool->country((string) $order->country);
        $prefix = trim((string) (data_get($order->provider_payload, 'buy.countryCode') ?? data_get($order->provider_payload, 'buy.CountryCode') ?? $country['prefix'] ?? ''));
        // Display in E.164: providers return national or international digits without "+".
        $phoneDisplay = $phone;
        if ($phone !== '' && ! str_starts_with($phone, '+') && $prefix !== '') {
            $digits = preg_replace('/\D+/', '', $phone) ?: $phone;
            $prefixDigits = preg_replace('/\D+/', '', $prefix) ?: '';
            if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
                $phoneDisplay = '+'.$digits;
            } else {
                $phoneDisplay = $prefix.ltrim($digits, '0');
            }
        }
        if (trim((string) $order->sms_code) !== '' || trim((string) $order->sms_text) !== '') {
            $sms[] = [
                'code' => (string) ($order->sms_code ?? ''),
                'sender' => (string) ($order->sms_sender ?? $order->product_name ?? 'SMS'),
                'text' => (string) ($order->sms_text ?? $order->sms_code ?? ''),
                'created_at' => $order->sms_received_at?->toIso8601String(),
            ];
        }

        return [
            'id' => (int) $order->id,
            'provider' => (string) $order->provider,
            'provider_order_id' => (string) ($order->provider_order_id ?? ''),
            'status' => (string) $order->status,
            'product' => (string) $order->product,
            'product_name' => (string) $order->product_name,
            'country' => (string) $order->country,
            'country_name' => (string) ($order->country_name ?? $order->country),
            'operator' => (string) ($order->operator ?? 'any'),
            'phone' => $phone,
            'phone_display' => $phoneDisplay,
            'sms' => $sms,
            'amount_minor' => (int) $order->sell_amount_minor,
            'amount_formatted' => $smsPool->formatUsd((int) $order->sell_amount_minor),
            'provider_cost_minor' => (int) $order->provider_cost_minor,
            'created_at' => $order->created_at?->toIso8601String(),
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    /**
     * Pull the verification code and full text out of a provider SMS response.
     *
     * @return array{0: string, 1: string} [code, text]
     */
    public static function extractSmsCode(array $json): array
    {
        $rawCode = trim((string) (data_get($json, 'sms.code') ?? $json['sms'] ?? $json['code'] ?? $json['pass'] ?? ''));
        $text = trim((string) (data_get($json, 'sms.fullText') ?? $json['text'] ?? $json['message'] ?? $rawCode));
        $code = $rawCode;

        if ($code === '' && $text !== '' && preg_match('/(?<!\d)(\d{4,8})(?!\d)/', $text, $m)) {
            $code = (string) $m[1];
        }

        return [$code, $text !== '' ? $text : $code];
    }

    /**
     * Refund an order to the user's wallet once, unless an SMS code was received.
     * Sets `refunded_at` on the order; the caller saves it.
     */
    public static function refundOrder(SocialNumberOrder $order, WalletService $wallet, string $reason): void
    {
        if ($order->refunded_at || (int) $order->sell_amount_minor <= 0 || trim((string) $order->sms_code) !== '') {
            return;
        }

        $wallet->credit((int) $order->user_id, (int) $order->sell_amount_minor, 'refund', [
            'reason' => $reason,
            'provider' => 'smspool',
            'social_number_order_id' => $order->id,
            'provider_order_id' => $order->provider_order_id,
        ], $order->payment_id ? (int) $order->payment_id : null);

        $order->refunded_at = now();
        if ($order->payment) {
            $order->payment->status = 'refunded';
            $order->payment->save();
        }
    }

    /**
     * Shape a monthly social number rental for API responses.
     */
    public static function rentalPayload(SocialNumberRental $rental, RentalProvider $rent): array
    {
        $messages = is_array($rental->sms_messages) ? $rental->sms_messages : [];

        return [
            'id' => (int) $rental->id,
            'provider' => (string) $rental->provider,
            'provider_order_id' => (string) ($rental->provider_order_id ?? ''),
            'status' => (string) $rental->status,
            'product' => (string) $rental->product,
            'product_name' => (string) $rental->product_name,
            'country' => (string) $rental->country,
            'country_name' => (string) ($rental->country_name ?? $rental->country),
            'provider_name' => (string) ($rental->provider_name ?? ''),
            'phone' => trim((string) ($rental->phone_country_code ?? '').(string) ($rental->phone ?? '')),
            'raw_phone' => (string) ($rental->phone ?? ''),
            'phone_country_code' => (string) ($rental->phone_country_code ?? ''),
            'auto_renew' => (bool) $rental->auto_renew,
            'monthly_amount_minor' => (int) $rental->monthly_amount_minor,
            'monthly_amount_formatted' => $rent->formatUsd((int) $rental->monthly_amount_minor),
            'current_period_start' => $rental->current_period_start?->toIso8601String(),
            'current_period_end' => $rental->current_period_end?->toIso8601String(),
            'activated_at' => $rental->activated_at?->toIso8601String(),
            'last_sms_sync_at' => $rental->last_sms_sync_at?->toIso8601String(),
            'renewal_failed_count' => (int) $rental->renewal_failed_count,
            'last_renewal_error' => (string) ($rental->last_renewal_error ?? ''),
            'sms' => array_values($messages),
            'created_at' => $rental->created_at?->toIso8601String(),
        ];
    }
}
