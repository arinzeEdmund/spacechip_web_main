<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SocialNumberRental;
use App\Contracts\RentalProvider;
use App\Services\RentalProviders;
use App\Services\WalletService;
use App\Support\SocialNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Monthly number rentals (SMSPool or LongSMS, whichever is active) paid from the wallet: quote, purchase, inbox and cancel.
 * Rentals are live as soon as they're bought; renewals run in RenewSocialNumberRentals.
 */
class SocialRentalController extends Controller
{
    public function __construct(private RentalProviders $providers) {}

    /** The supplier currently selling new rentals. */
    private function active(): RentalProvider
    {
        return $this->providers->active();
    }

    public function apps(): JsonResponse
    {
        $items = array_values(array_map(fn (array $app) => [
            'key' => (string) $app['key'],
            'name' => (string) $app['name'],
            'icon' => (string) $app['icon'],
            'description' => (string) $app['desc'],
            'available' => true,
        ], $this->active()->apps()));

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function countries(): JsonResponse
    {
        return response()->json(['ok' => true, 'items' => $this->active()->countries()]);
    }

    public function quote(Request $request): JsonResponse
    {
        if ($error = $this->notConfigured()) {
            return $error;
        }

        [$product, $country, $provider] = $this->selection($request->query('product'), $request->query('country'), $request->query('provider'));
        $quote = $this->active()->quote($product, $country, $provider, 'month', 1);
        if (($quote['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote monthly rental.')], 422);
        }

        $sellMinor = (int) ($quote['sell_amount_minor'] ?? 0);

        return response()->json([
            'ok' => true,
            'product' => $product,
            'country' => $country,
            'provider' => $provider,
            'count' => (int) ($quote['count'] ?? 0),
            'providers' => array_values(array_filter(array_map('strval', (array) ($quote['providers'] ?? [])))),
            'provider_cost_minor' => (int) ($quote['provider_cost_minor'] ?? 0),
            'monthly_amount_minor' => $sellMinor,
            'monthly_amount_formatted' => $this->active()->formatUsd($sellMinor),
            'period' => '1 month',
        ]);
    }

    /** Charge the wallet, rent the number, and refund automatically if SMSPool can't deliver. */
    public function buy(Request $request, WalletService $wallet): JsonResponse
    {
        if ($error = $this->notConfigured()) {
            return $error;
        }

        $data = $request->validate([
            'product' => ['required', 'string'],
            'country' => ['required', 'string'],
            'provider' => ['nullable', 'string'],
        ]);
        [$product, $country, $provider] = $this->selection($data['product'], $data['country'], $data['provider'] ?? null);

        $rent = $this->active();
        $supplier = $rent->key();
        $quote = $rent->quote($product, $country, $provider, 'month', 1);
        if (($quote['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote monthly rental.')], 422);
        }
        if ((int) ($quote['count'] ?? 0) <= 0) {
            return response()->json(['message' => 'No monthly rental numbers are available for this app and country.'], 422);
        }

        $amountMinor = (int) ($quote['sell_amount_minor'] ?? 0);
        if ($amountMinor <= 0) {
            return response()->json(['message' => 'Unable to determine monthly price.'], 422);
        }

        $userId = (int) Auth::id();
        $app = is_array($quote['app'] ?? null) ? $quote['app'] : [];
        $countryRow = is_array($quote['country'] ?? null) ? $quote['country'] : [];
        $selection = ['product' => $product, 'country' => $country, 'selected_provider' => $provider];
        $reference = 'snrent_'.Str::uuid();

        $payment = Payment::create([
            'user_id' => $userId,
            'provider' => 'wallet',
            'provider_reference' => $reference,
            'status' => 'created',
            'asset_type' => 'social_number_rental',
            'asset_id' => $product,
            'bundle_id' => $country,
            'package_type' => 'MONTHLY',
            'currency' => 'USD',
            'amount_minor' => $amountMinor,
            'provider_payload' => ['provider' => $supplier, ...$selection, 'quote' => $quote],
        ]);

        try {
            $wallet->debit($userId, $amountMinor, 'buy_social_rental', ['reference' => $reference, 'provider' => $supplier, ...$selection], (int) $payment->id, (int) config('wallet.min_balance_minor', 0));
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed_insufficient_wallet', 'fulfillment_payload' => ['ok' => false, 'error' => $e->getMessage()]]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $refund = function (string $reason, array $fulfillment) use ($wallet, $userId, $amountMinor, $reference, $payment, $supplier) {
            try {
                $wallet->credit($userId, $amountMinor, 'refund', ['reference' => $reference, 'provider' => $supplier, 'reason' => $reason], (int) $payment->id);
            } catch (Throwable) {
            }
            $payment->update(['status' => 'paid_failed_provision_refunded', 'fulfillment_payload' => $fulfillment]);
        };

        $create = $rent->create((string) ($app['service'] ?? ''), $country, 'month', 1, $provider);
        if (($create['ok'] ?? false) !== true) {
            $reason = trim((string) ($create['error'] ?? 'Monthly rental purchase failed.'));
            Log::warning('Social rental purchase failed at provider', ['user_id' => $userId, 'product' => $product, 'country' => $country, 'sell_amount_minor' => $amountMinor, 'provider_reason' => $reason]);
            $refund($reason, ['ok' => false, 'error' => $reason]);

            // Never expose our own supplier account balance problems to customers.
            return response()->json(['message' => stripos($reason, 'balance') !== false
                ? 'Monthly rentals are temporarily unavailable. Your wallet was not charged.'
                : $reason], 502);
        }

        $number = is_array($create['json']['data'] ?? null) ? $create['json']['data'] : [];
        $providerOrderId = (string) ($number['id'] ?? '');
        $phone = (string) ($number['pnumber'] ?? '');
        $dialCode = (string) ($number['ccode'] ?? '');
        if ($providerOrderId === '' || $phone === '') {
            $refund('The provider did not return a usable monthly rental number.', ['ok' => false, 'payload' => $number]);

            return response()->json(['message' => 'The provider did not return a usable monthly rental number. Wallet was refunded.'], 502);
        }

        $rental = SocialNumberRental::create([
            'user_id' => $userId,
            'payment_id' => $payment->id,
            'provider' => $supplier,
            'provider_order_id' => $providerOrderId,
            'status' => 'active',
            'activated_at' => now(),
            'product' => $product,
            'product_name' => (string) ($app['name'] ?? $product),
            'service_code' => (string) ($app['service'] ?? ''),
            'country' => $country,
            'country_name' => (string) ($countryRow['name'] ?? $country),
            'provider_name' => $provider !== 'all_providers' ? $provider : null,
            'phone' => $phone,
            'phone_country_code' => $dialCode,
            'provider_cost_minor' => (int) ($number['cost_minor'] ?? $quote['provider_cost_minor'] ?? 0),
            'monthly_amount_minor' => $amountMinor,
            'currency' => 'USD',
            'auto_renew' => true,
            'current_period_start' => now(),
            'current_period_end' => $rent->timestampToCarbon($number['until'] ?? null) ?: now()->addMonth(),
            'sms_messages' => [],
            'provider_payload' => ['create' => $number, 'quote' => $quote],
        ]);

        $payment->update([
            'status' => 'paid',
            'asset_id' => (string) $rental->id,
            'fulfillment_payload' => ['ok' => true, 'rental_id' => $rental->id, 'provider_order_id' => $providerOrderId, 'phone' => trim($dialCode.$phone)],
        ]);

        return $this->rentalResponse($rental);
    }

    public function activate(SocialNumberRental $rental): JsonResponse
    {
        $this->authorizeOwner($rental);

        if (! in_array($rental->status, ['active', 'pending_activation'], true)) {
            return response()->json(['message' => 'This rental cannot be activated.'], 422);
        }

        $res = $this->providers->for($rental->provider)->activate((string) $rental->provider_order_id);
        if (($res['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($res['error'] ?? 'Activation failed.')], 502);
        }

        $rental->update([
            'status' => 'active',
            'activated_at' => now(),
            'provider_payload' => array_merge((array) $rental->provider_payload, ['last_activate' => $res['json'] ?? null]),
        ]);

        return $this->rentalResponse($rental);
    }

    /** Refresh the inbox from SMSPool (the provider keeps the full history; we mirror it). */
    public function sms(SocialNumberRental $rental): JsonResponse
    {
        $this->authorizeOwner($rental);

        $res = $this->providers->for($rental->provider)->sms((string) $rental->provider_order_id);
        if (($res['ok'] ?? false) === true) {
            $messages = [];
            foreach ((array) ($res['json']['data'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $date = is_numeric($row['date'] ?? null) ? (int) $row['date'] : null;
                $messages[] = [
                    'sender' => (string) ($row['sender'] ?? 'SMS'),
                    'text' => (string) ($row['text'] ?? ''),
                    'date' => $date,
                    'created_at' => ($date !== null ? now()->setTimestamp($date) : now())->toIso8601String(),
                ];
            }

            $rental->update([
                'sms_messages' => $messages,
                'last_sms_sync_at' => now(),
                'provider_payload' => array_merge((array) $rental->provider_payload, ['last_sms' => ['count' => count($messages)]]),
            ]);
        }

        return $this->rentalResponse($rental->fresh());
    }

    /** Stop auto-renewal. The number stays usable until the paid period ends. */
    public function cancel(SocialNumberRental $rental): JsonResponse
    {
        $this->authorizeOwner($rental);

        $rental->auto_renew = false;
        if (! in_array($rental->status, ['canceled', 'expired'], true)) {
            $rental->status = 'canceled';
            $rental->canceled_at = now();
        }
        $rental->save();

        return $this->rentalResponse($rental);
    }

    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));
        $query = SocialNumberRental::query()->where('user_id', Auth::id())->orderByDesc('created_at');

        return response()->json([
            'ok' => true,
            'total' => (clone $query)->count(),
            'items' => $query->skip($offset)->take($limit)->get()->map(fn (SocialNumberRental $r) => SocialNumbers::rentalPayload($r, $this->providers->for($r->provider)))->values(),
        ]);
    }

    /** @return array{0: string, 1: string, 2: string} [product, country ISO, provider ("all_providers" = any)] */
    private function selection(mixed $product, mixed $country, mixed $provider): array
    {
        return [trim((string) $product), strtoupper(trim((string) $country)), trim((string) ($provider ?? 'all_providers')) ?: 'all_providers'];
    }

    private function authorizeOwner(SocialNumberRental $rental): void
    {
        if ((int) $rental->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function rentalResponse(SocialNumberRental $rental): JsonResponse
    {
        return response()->json(['ok' => true, 'rental' => SocialNumbers::rentalPayload($rental, $this->providers->for($rental->provider))]);
    }

    private function notConfigured(): ?JsonResponse
    {
        return $this->active()->isConfigured() ? null : response()->json(['message' => 'The number service is not configured.'], 500);
    }
}
