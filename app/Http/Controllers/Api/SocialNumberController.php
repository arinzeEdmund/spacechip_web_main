<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SocialNumberOrder;
use App\Services\SmsPoolService;
use App\Services\WalletService;
use App\Support\SocialNumbers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-time SMS activation numbers (SMSPool), paid from the wallet.
 *
 * Lifecycle: buy → PENDING → (poll) RECEIVED → FINISHED. Numbers that get no SMS within
 * ~10 minutes, or that the user cancels/bans/replaces first, are released at SMSPool and
 * refunded to the wallet.
 */
class SocialNumberController extends Controller
{
    private const CLOSED = ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'];

    public function __construct(private SmsPoolService $smsPool) {}

    // ─── Catalogue ──────────────────────────────────────────────────────────────

    public function profile(): JsonResponse
    {
        if ($error = $this->notConfigured()) {
            return $error;
        }

        $res = $this->smsPool->profile();
        if (($res['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($res['error'] ?? 'Unable to fetch profile.')], 502);
        }

        return response()->json(['ok' => true, 'profile' => $res['json'] ?? null]);
    }

    /** Curated apps with live country coverage and the cheapest sell price. */
    public function apps(): JsonResponse
    {
        $coverage = $this->smsPool->curatedCoverage();

        $items = array_values(array_map(function (array $app) use ($coverage) {
            $cov = $coverage[$app['key']] ?? null;
            $countries = (int) ($cov['countries'] ?? 0);
            $minSellMinor = $cov ? $this->smsPool->sellAmountMinor((int) round($cov['min_price'] * 100)) : 0;

            return [
                'key' => (string) $app['key'],
                'name' => (string) $app['name'],
                'icon' => (string) $app['icon'],
                'description' => (string) $app['desc'],
                'category' => 'activation',
                'qty' => $countries,
                'coverage' => $countries > 0 ? $countries.' '.Str::plural('country', $countries) : null,
                'price' => $minSellMinor > 0 ? $this->smsPool->formatUsd($minSellMinor) : null,
                // Unknown coverage (lookup failed) is shown as available rather than hidden.
                'available' => $cov === null || $countries > 0,
            ];
        }, $this->smsPool->apps()));

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /** The full SMSPool service catalogue, searchable and paginated. */
    public function services(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 60)));
        $offset = max(0, (int) $request->query('offset', 0));
        $needle = mb_strtolower(trim((string) $request->query('q', '')));

        $catalog = $this->smsPool->serviceCatalog();
        if ($needle !== '') {
            $catalog = array_values(array_filter($catalog, fn ($row) => str_contains(mb_strtolower($row['name']), $needle)));
        }

        $items = array_map(fn ($row) => [
            'key' => (string) $row['id'],
            'name' => (string) $row['name'],
            'icon' => '',
            'description' => '',
            'category' => 'activation',
            'qty' => 0,
            'coverage' => null,
            'price' => null,
            'available' => true,
        ], array_slice($catalog, $offset, $limit));

        return response()->json([
            'ok' => true,
            'items' => $items,
            'total' => count($catalog),
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => $offset + $limit < count($catalog),
        ]);
    }

    public function countries(): JsonResponse
    {
        return response()->json(['ok' => true, 'items' => $this->smsPool->countries()]);
    }

    public function operators(Request $request): JsonResponse
    {
        $country = strtoupper(trim((string) $request->query('country', '')));
        if ($country === '') {
            return response()->json(['message' => 'Missing country.'], 422);
        }

        return response()->json(['ok' => true, 'country' => $country, 'items' => $this->smsPool->operators($country)]);
    }

    /** Sell price and stock for an app/country (cached 45s). SMSPool picks the carrier, so there is one "any" operator. */
    public function prices(Request $request): JsonResponse
    {
        if ($error = $this->notConfigured()) {
            return $error;
        }

        $country = strtoupper(trim((string) $request->query('country', '')));
        $product = trim((string) $request->query('product', ''));
        if ($country === '' || $product === '') {
            return response()->json(['message' => 'Invalid country/product.'], 422);
        }

        $quote = Cache::remember("smspool.quote.v1.{$country}.{$product}", now()->addSeconds(45), fn () => $this->smsPool->quote($product, $country));
        if (($quote['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to fetch prices.')], 502);
        }

        $count = (int) ($quote['count'] ?? 0);
        $sellMinor = (int) ($quote['sell_amount_minor'] ?? 0);
        $cost = $sellMinor > 0 ? $this->smsPool->formatUsd($sellMinor) : null;

        return response()->json([
            'ok' => true,
            'country' => $country,
            'product' => $product,
            'min_cost' => $cost,
            'total_count' => $count,
            'total_operators' => $count > 0 ? 1 : 0,
            'limit' => 1,
            'offset' => 0,
            'has_more' => false,
            'operators' => [['operator' => 'any', 'cost' => $cost, 'count' => $count, 'rate' => null]],
        ]);
    }

    // ─── Orders ─────────────────────────────────────────────────────────────────

    public function buy(Request $request, WalletService $wallet): JsonResponse
    {
        if ($error = $this->notConfigured()) {
            return $error;
        }

        $data = $request->validate([
            'product' => ['required', 'string'],
            'country' => ['required', 'string'],
            'operator' => ['nullable', 'string'],
        ]);

        $product = trim((string) $data['product']);
        $country = strtoupper(trim((string) $data['country']));
        $operator = trim((string) ($data['operator'] ?? 'any')) ?: 'any';

        $quote = $this->smsPool->quote($product, $country);
        if (($quote['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote number.')], 422);
        }
        if ((int) ($quote['count'] ?? 0) <= 0) {
            return response()->json(['message' => 'No numbers are currently available for this app and country.'], 422);
        }
        if ((int) ($quote['sell_amount_minor'] ?? 0) <= 0) {
            return response()->json(['message' => 'Unable to determine price.'], 422);
        }

        return $this->purchase($wallet, $quote, $product, $country, $operator);
    }

    /** Poll SMSPool for the code; expire and refund numbers that never received one. */
    public function check(int $id, WalletService $wallet): JsonResponse
    {
        $order = $this->ownedOrder($id);
        $orderedAt = $order->ordered_at ?: $order->created_at;
        $expired = (bool) $orderedAt?->copy()->addSeconds(SocialNumbers::OTP_LIFETIME_SECONDS)->isPast();
        $context = [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'provider_order' => $order->provider_order_id,
            'elapsed_seconds' => $orderedAt ? (int) $orderedAt->diffInSeconds(now()) : null,
        ];

        Log::info('SocialOTP poll', $context + ['product' => $order->product_name, 'country' => $order->country, 'status' => $order->status, 'expired_locally' => $expired]);

        if (in_array($order->status, ['PENDING', 'WAITING', 'RECEIVED'], true) && $order->provider_order_id) {
            $this->poll($order, $wallet, $expired, $context);
        }

        // Still nothing after the window: give the number back and refund.
        $order = $order->fresh();
        if (in_array($order->status, ['PENDING', 'WAITING'], true) && trim((string) $order->sms_code) === '' && $expired) {
            $this->smsPool->release((string) $order->provider_order_id);
            $this->close($order, $wallet, 'TIMEOUT', 'Social number OTP timed out after 10 minutes.');
            Log::warning('SocialOTP timeout (local expiry)', $context);
        }

        return $this->orderResponse($order->fresh());
    }

    public function finish(int $id): JsonResponse
    {
        $order = $this->ownedOrder($id);
        if (trim((string) $order->sms_code) === '') {
            return response()->json(['message' => 'No OTP has been received for this order yet.'], 422);
        }

        if (! in_array($order->status, ['CANCELED', 'BANNED', 'TIMEOUT'], true)) {
            $order->update(['status' => 'FINISHED', 'finished_at' => now()]);
        }

        return $this->orderResponse($order);
    }

    public function cancel(int $id, WalletService $wallet): JsonResponse
    {
        return $this->releaseByUser($this->ownedOrder($id), $wallet, 'CANCELED', 'Social number canceled before SMS was received.');
    }

    /** "Ban" = the number was already used for this app; same release + refund as cancel. */
    public function ban(int $id, WalletService $wallet): JsonResponse
    {
        return $this->releaseByUser($this->ownedOrder($id), $wallet, 'BANNED', 'Social number banned before SMS was received.');
    }

    /** Swap a number that isn't receiving codes for a fresh one: release + refund the old, buy a new one. */
    public function tryAnother(int $id, WalletService $wallet): JsonResponse
    {
        if ($error = $this->notConfigured()) {
            return $error;
        }

        $old = $this->ownedOrder($id);
        if (trim((string) $old->sms_code) !== '' || in_array($old->status, ['RECEIVED', 'FINISHED'], true)) {
            return response()->json(['message' => 'This order already received an OTP.'], 422);
        }
        if (in_array($old->status, ['CANCELED', 'BANNED'], true)) {
            return response()->json(['message' => 'This order is already closed.'], 422);
        }

        $product = (string) $old->product;
        $country = (string) $old->country;

        $quote = $this->smsPool->quote($product, $country);
        if (($quote['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($quote['error'] ?? 'Unable to quote another number.')], 422);
        }
        if ((int) ($quote['count'] ?? 0) <= 0) {
            return response()->json(['message' => 'No replacement numbers are currently available.'], 422);
        }

        // Timed-out orders were already released and refunded by check().
        if ($old->status !== 'TIMEOUT') {
            try {
                $release = $this->smsPool->release((string) $old->provider_order_id);
                if (($release['ok'] ?? false) !== true) {
                    return response()->json(['message' => (string) ($release['error'] ?? 'Unable to close the current number. Try again in a moment.')], 422);
                }
                $this->close($old, $wallet, 'CANCELED', 'Social number replaced before SMS was received.', now());
            } catch (Throwable) {
                return response()->json(['message' => 'Unable to close the current number. Try again in a moment.'], 502);
            }
        }

        if ((int) ($quote['sell_amount_minor'] ?? 0) <= 0) {
            return response()->json(['message' => 'Unable to determine replacement price.'], 422);
        }

        return $this->purchase($wallet, $quote, $product, $country, (string) ($old->operator ?: 'any'), $old);
    }

    public function orders(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->query('limit', 20)));
        $offset = max(0, (int) $request->query('offset', 0));
        $query = SocialNumberOrder::query()->where('user_id', Auth::id())->orderByDesc('created_at');

        return response()->json([
            'ok' => true,
            'total' => (clone $query)->count(),
            'items' => $query->skip($offset)->take($limit)->get()->map(fn (SocialNumberOrder $o) => SocialNumbers::orderPayload($o, $this->smsPool))->values(),
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Charge the wallet, buy the number and open the order. Any provider failure refunds the
     * charge. `$replaces` links a "try another" purchase to the order it replaces.
     */
    private function purchase(WalletService $wallet, array $quote, string $product, string $country, string $operator, ?SocialNumberOrder $replaces = null): JsonResponse
    {
        $userId = (int) Auth::id();
        $amountMinor = (int) $quote['sell_amount_minor'];
        $app = is_array($quote['app'] ?? null) ? $quote['app'] : [];
        $countryRow = is_array($quote['country'] ?? null) ? $quote['country'] : [];
        $link = $replaces ? ['replaces_order_id' => $replaces->id] : [];
        $noun = $replaces ? 'replacement number' : 'number';
        $reference = 'sn_'.Str::uuid();

        $payment = Payment::create([
            'user_id' => $userId,
            'provider' => 'wallet',
            'provider_reference' => $reference,
            'status' => 'created',
            'asset_type' => 'social_number',
            'asset_id' => $product,
            'bundle_id' => $country,
            'package_type' => 'SMS_ACTIVATION',
            'currency' => 'USD',
            'amount_minor' => $amountMinor,
            'provider_payload' => ['provider' => 'smspool', 'product' => $product, 'country' => $country, 'operator' => $operator, 'quote' => $quote] + $link,
        ]);

        try {
            $wallet->debit($userId, $amountMinor, 'buy_social_number', [
                'reference' => $reference,
                'provider' => 'smspool',
                'product' => $product,
                'country' => $country,
                'operator' => $operator,
            ] + $link, (int) $payment->id, (int) config('wallet.min_balance_minor', 0));
        } catch (Throwable $e) {
            $payment->update(['status' => 'failed_insufficient_wallet', 'fulfillment_payload' => ['ok' => false, 'error' => $e->getMessage()]]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $refund = function (string $reason, array $fulfillment) use ($wallet, $userId, $amountMinor, $reference, $payment, $link) {
            try {
                $wallet->credit($userId, $amountMinor, 'refund', ['reference' => $reference, 'provider' => 'smspool', 'reason' => $reason] + $link, (int) $payment->id);
            } catch (Throwable) {
            }
            $payment->update(['status' => 'paid_failed_provision_refunded', 'fulfillment_payload' => $fulfillment]);
        };

        $buy = $this->smsPool->buyNumber((string) ($app['service'] ?? ''), $country);
        if (($buy['ok'] ?? false) !== true) {
            $error = (string) ($buy['error'] ?? ucfirst($noun).' purchase failed.');
            $refund($error, ['ok' => false, 'error' => $error]);

            return response()->json(['message' => ucfirst($noun).' purchase failed. Wallet was refunded.'], 502);
        }

        $number = (array) data_get($buy, 'json.data', []);
        $providerOrderId = (string) ($number['orderId'] ?? '');
        $phone = (string) ($number['phoneNumber'] ?? '');
        if ($providerOrderId === '' || $providerOrderId === '-1' || $phone === '') {
            $refund("The provider did not return a usable {$noun}.", ['ok' => false, 'payload' => $number]);

            return response()->json(['message' => "The provider did not return a usable {$noun}. Wallet was refunded."], 502);
        }

        $order = SocialNumberOrder::create([
            'user_id' => $userId,
            'payment_id' => $payment->id,
            'provider' => 'smspool',
            'provider_order_id' => $providerOrderId,
            'status' => 'PENDING',
            'product' => $product,
            'product_name' => (string) ($app['name'] ?? $replaces?->product_name ?? $product),
            'service_code' => (string) ($app['service'] ?? $replaces?->service_code ?? ''),
            'country' => $country,
            'country_name' => (string) ($countryRow['name'] ?? $replaces?->country_name ?? $country),
            'operator' => $operator,
            'phone' => $phone,
            'provider_cost_minor' => (int) ($quote['provider_cost_minor'] ?? 0),
            'sell_amount_minor' => $amountMinor,
            'currency' => 'USD',
            'provider_payload' => ['api_version' => 'v2', 'buy' => $number, 'quote' => $quote] + $link,
            'ordered_at' => now(),
        ]);

        $payment->update([
            'status' => 'paid',
            'asset_id' => (string) $order->id,
            'fulfillment_payload' => ['ok' => true, 'order_id' => $order->id, 'provider_order_id' => $providerOrderId, 'phone' => $phone] + $link,
        ]);

        return $this->orderResponse($order);
    }

    /** One SMSPool poll: store a received code, or expire the order if the provider gave up. */
    private function poll(SocialNumberOrder $order, WalletService $wallet, bool $expired, array $context): void
    {
        $res = $this->smsPool->checkSms((string) $order->provider_order_id);
        if (($res['ok'] ?? false) !== true) {
            Log::warning('SocialOTP provider poll failed', $context + ['provider_error' => $res['error'] ?? null]);

            return;
        }

        $json = is_array($res['json'] ?? null) ? $res['json'] : [];
        // Keep poll metadata only: message text/codes live in the encrypted sms_* columns, never in this plaintext JSON.
        $safeJson = \Illuminate\Support\Arr::except($json, ['sms', 'full_sms', 'text', 'code', 'message', 'raw']);
        $payload = array_merge((array) $order->provider_payload, ['last_sms_poll' => $safeJson]);
        $response = (string) ($json['response'] ?? $json['responce'] ?? '');
        [$code, $text] = SocialNumbers::extractSmsCode($json);

        if ($response === '1' || $code !== '') {
            $order->fill([
                'status' => 'RECEIVED',
                'sms_code' => $code,
                'sms_text' => $text,
                'sms_sender' => (string) ($json['sender'] ?? $order->product_name),
                'sms_received_at' => $order->sms_received_at ?: now(),
                'provider_payload' => array_merge($payload, ['last_sms' => $safeJson]),
            ])->save();

            // Never log the code or the message text: logs are less protected than the encrypted columns.
            Log::info(sprintf('[SocialOTP] ORDER #%s RECEIVED | Product: %s | Country: %s | Elapsed: %ss',
                $order->id, $order->product_name, $order->country, $context['elapsed_seconds'] ?? '?'), $context);

            return;
        }

        $order->provider_payload = $payload;

        if ($response === '3' && ($expired || data_get($json, 'refunded'))) {
            $this->smsPool->release((string) $order->provider_order_id);
            $this->close($order, $wallet, 'TIMEOUT', 'Number expired before the SMS arrived.');
            Log::warning('SocialOTP timeout (provider expired)', $context);
        } elseif (in_array($response, ['2', '3', '4'], true)) {
            $order->status = $order->sms_code ? 'RECEIVED' : 'PENDING';
            $order->save();
        }
    }

    /** User-initiated cancel/ban: release at SMSPool and refund, unless already closed. */
    private function releaseByUser(SocialNumberOrder $order, WalletService $wallet, string $status, string $reason): JsonResponse
    {
        if (! in_array($order->status, self::CLOSED, true)) {
            $this->smsPool->release((string) $order->provider_order_id);
            $this->close($order, $wallet, $status, $reason, now());
        }

        return $this->orderResponse($order);
    }

    /** Refund (idempotent, see SocialNumbers::refundOrder) and mark the order closed. */
    private function close(SocialNumberOrder $order, WalletService $wallet, string $status, string $reason, $canceledAt = null): void
    {
        SocialNumbers::refundOrder($order, $wallet, $reason);
        $order->status = $status;
        $order->canceled_at = $canceledAt ?? ($order->canceled_at ?: now());
        $order->save();
    }

    private function ownedOrder(int $id): SocialNumberOrder
    {
        return SocialNumberOrder::query()->where('user_id', Auth::id())->findOrFail($id);
    }

    private function orderResponse(SocialNumberOrder $order): JsonResponse
    {
        return response()->json(['ok' => true, 'order' => SocialNumbers::orderPayload($order, $this->smsPool)]);
    }

    private function notConfigured(): ?JsonResponse
    {
        return $this->smsPool->isConfigured() ? null : response()->json(['message' => 'The number service is not configured.'], 500);
    }
}
