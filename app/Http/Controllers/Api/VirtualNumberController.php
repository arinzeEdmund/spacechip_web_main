<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Models\Payment;
use App\Models\VirtualNumberMessage;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Services\PaystackService;
use App\Services\TwilioService;
use App\Services\VirtualNumberCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Twilio virtual numbers: country and number discovery, Paystack checkout (with a saved card
 * for monthly renewal), subscription management and the SMS/voice inbox.
 */
class VirtualNumberController extends Controller
{
    public function __construct(private VirtualNumberCatalog $catalog) {}

    // ─── Discovery ──────────────────────────────────────────────────────────────

    /** Paginated, searchable list of countries Twilio sells numbers in, with our starting price. */
    public function countries(Request $request, TwilioService $twilio): JsonResponse
    {
        if (! $twilio->isConfigured()) {
            return response()->json(['message' => 'Twilio is not configured.'], 500);
        }

        if (($items = $this->catalog->countries($error)) === null) {
            return response()->json(['message' => $error], 502);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(10, min(120, (int) $request->query('per_page', 60)));
        $q = mb_strtolower(trim((string) $request->query('q', '')));
        $dashboard = $request->query('context') === 'dashboard';
        [$defaultCurrency, $defaultMinor] = $this->catalog->defaultPrice();

        if ($q !== '') {
            $items = array_values(array_filter($items, fn ($row) => is_array($row) && (
                str_contains(mb_strtolower((string) ($row['country_name'] ?? '')), $q)
                || str_contains(mb_strtolower((string) ($row['country_code'] ?? '')), $q)
            )));
        }

        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        $codes = array_values(array_filter(array_map(fn ($r) => is_array($r) ? strtoupper((string) ($r['country_code'] ?? '')) : '', $slice)));
        $minPrices = VirtualNumberProduct::query()
            ->selectRaw('country_iso, MIN(monthly_amount_minor) as amt, MIN(currency) as cur')
            ->where('active', true)
            ->whereIn('country_iso', $codes)
            ->groupBy('country_iso')
            ->get()
            ->keyBy('country_iso');

        $payload = [];
        foreach ($slice as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = strtoupper((string) ($row['country_code'] ?? ''));
            $min = $minPrices->get($code);
            $currency = strtoupper((string) ($min->cur ?? $defaultCurrency));
            $amount = (int) ($min->amt ?? $defaultMinor);
            if ($currency === '' || $amount <= 0) {
                [$currency, $amount] = [$defaultCurrency, $defaultMinor];
            }

            $payload[] = [
                'country_code' => $code,
                'country_name' => (string) ($row['country_name'] ?? $code),
                'flag' => preg_match('/^[A-Z]{2}$/', $code) ? mb_chr(ord($code[0]) + 127397).mb_chr(ord($code[1]) + 127397) : '☎️',
                'starting_price_formatted' => $currency.' '.number_format($amount / 100, 2),
                'url' => route($dashboard ? 'dashboard.virtual.country' : 'virtual.country', ['country' => $code]),
            ];
        }

        return response()->json([
            'ok' => true,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => ($offset + count($slice)) < $total,
            'items' => $payload,
        ]);
    }

    /** Country detail: ensures a sellable product exists and quotes Twilio's cheapest number type (else the default price). */
    public function country(string $country, TwilioService $twilio): JsonResponse
    {
        if (! $twilio->isConfigured()) {
            return response()->json(['message' => 'Twilio is not configured.'], 500);
        }

        $country = strtoupper(trim($country));
        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            return response()->json(['message' => 'Invalid country code.'], 422);
        }

        if (($countries = $this->catalog->countries($error)) === null) {
            return response()->json(['message' => $error], 502);
        }

        $countryName = $this->catalog->countryName($country, $countries);
        $product = $this->catalog->productFor($country, $countryName);
        [$fromCurrency, $fromMinor] = $this->catalog->cheapestLivePrice($country, $this->catalog->defaultPrice()[0]) ?? $this->catalog->defaultPrice();

        return response()->json([
            'ok' => true,
            'country_code' => $country,
            'country_name' => $countryName,
            'product_id' => (int) $product->id,
            'starting_from' => $twilio->formatMoney($fromMinor, $fromCurrency),
        ]);
    }

    /** Numbers available to buy for a product, each priced by its number type (cached 30s). */
    public function available(Request $request, TwilioService $twilio): JsonResponse
    {
        if (! $twilio->isConfigured()) {
            return response()->json(['message' => 'Twilio is not configured.'], 500);
        }

        $productId = (int) $request->query('product_id', 0);
        $limit = (int) $request->query('limit', 20);
        $page = (int) $request->query('page', 0);
        if ($productId <= 0) {
            return response()->json(['message' => 'Missing product_id.'], 422);
        }

        $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        $cacheKey = 'twilio.available.any.v1.'.sha1(implode('|', [$product->id, $product->country_iso, (int) $product->cap_sms, (int) $product->cap_voice, $limit, $page]));
        if (is_array($cached = Cache::get($cacheKey))) {
            return response()->json($cached);
        }

        $res = $twilio->availableAnyNumbers(
            (string) $product->country_iso,
            false,
            false,
            $limit,
            array_merge(is_array($product->twilio_search_filters) ? $product->twilio_search_filters : [], $page > 0 ? ['Page' => $page] : []),
        );
        if (($res['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($res['error'] ?? 'Unable to fetch numbers.')], 502);
        }

        $pricing = $twilio->phoneNumberPricing((string) $product->country_iso);
        $priced = ($pricing['ok'] ?? false) === true;
        $currency = $priced ? (string) ($pricing['currency'] ?? 'USD') : 'USD';
        $prices = $priced && is_array($pricing['prices_minor'] ?? null) ? $pricing['prices_minor'] : [];

        $items = array_map(function ($it) use ($twilio, $prices, $currency) {
            if (! is_array($it)) {
                return $it;
            }
            $minor = is_numeric($prices[$it['number_type'] ?? ''] ?? null) ? (int) $prices[$it['number_type']] : null;

            return [
                ...$it,
                'monthly_fee_minor' => $minor,
                'monthly_fee_currency' => $currency,
                'monthly_fee_formatted' => $minor > 0 ? $twilio->formatMoney($minor, $currency) : null,
            ];
        }, $res['items'] ?? []);

        $payload = [
            'ok' => true,
            'items' => $items,
            'next_page_uri' => null,
            'has_more' => (bool) ($res['has_more'] ?? false),
            'types_tried' => $res['types_tried'] ?? [],
            'modes_tried' => $res['modes_tried'] ?? [],
        ];
        Cache::put($cacheKey, $payload, now()->addSeconds(30));

        return response()->json($payload);
    }

    // ─── Checkout (Paystack) ────────────────────────────────────────────────────

    public function paystackInitialize(Request $request, PaystackService $paystack): JsonResponse
    {
        $productId = (int) $request->input('product_id', 0);
        $phoneNumber = trim((string) $request->input('phone_number', ''));
        $numberType = VirtualNumberCatalog::numberType($request->input('number_type'));
        if ($productId <= 0 || $phoneNumber === '') {
            return response()->json(['message' => 'Missing checkout parameters.'], 422);
        }

        $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        [$priceCurrency, $priceMinor] = $this->catalog->monthlyPrice($product, $numberType);
        if ($priceMinor <= 0) {
            return response()->json(['message' => 'Invalid amount.'], 422);
        }

        // Charge: Paystack's settlement currency; USD prices convert to NGN at the configured rate.
        $currency = strtoupper((string) config('services.paystack.currency', 'NGN')) ?: 'NGN';
        $rate = (float) config('virtual_numbers.usd_to_ngn_rate', 0);
        $converts = $currency === 'NGN' && $priceCurrency === 'USD';
        $amountMinor = $priceMinor;
        if ($converts) {
            if ($rate <= 0) {
                return response()->json(['message' => 'USD payments are not supported on your Paystack account. Set VIRTUAL_NUMBER_USD_TO_NGN_RATE to charge in NGN equivalent.'], 422);
            }
            $amountMinor = (int) round(($priceMinor / 100) * $rate * 100);
        }

        $email = trim((string) config('services.paystack.owner_email', '')) ?: trim((string) (Auth::user()?->email ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json(['message' => 'Please enter a valid email address.'], 422);
        }

        $reference = (string) Str::uuid();
        Payment::updateOrCreate(['provider' => 'paystack', 'provider_reference' => $reference], [
            'user_id' => Auth::id(),
            'status' => 'initialized',
            'asset_type' => 'virtual_number',
            'asset_id' => (string) $product->id,
            'bundle_id' => (string) $product->id,
            'package_type' => 'MONTHLY',
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'provider_payload' => [
                'virtual_number' => [
                    'product_id' => $product->id,
                    'phone_number' => $phoneNumber,
                    'number_type' => $numberType ?: null,
                    'country_iso' => (string) $product->country_iso,
                    'cap_sms' => (bool) $product->cap_sms,
                    'cap_voice' => (bool) $product->cap_voice,
                    'twilio_price_currency' => $priceCurrency,
                    'twilio_price_minor' => $priceMinor,
                    'paystack_charge_currency' => $currency,
                    'paystack_charge_minor' => $amountMinor,
                    'usd_to_ngn_rate' => $converts ? $rate : null,
                    'paystack_email' => $email,
                ],
            ],
        ]);

        if (! $paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
        }

        try {
            $response = $paystack->initialize([
                'email' => $email,
                'amount' => $amountMinor,
                'currency' => $currency,
                'reference' => $reference,
                'metadata' => [
                    'purpose' => 'virtual_number_subscription',
                    'product_id' => (int) $product->id,
                    'phone_number' => $phoneNumber,
                    'number_type' => $numberType ?: null,
                ],
            ]);
        } catch (Throwable) {
            return response()->json(['message' => 'Failed to initialize Paystack transaction.'], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'message' => trim((string) (data_get($response->json(), 'message') ?? '')) ?: 'Failed to initialize Paystack transaction.',
                'paystack' => ['status' => $response->status(), 'currency' => $currency, 'amount_minor' => $amountMinor],
            ], 502);
        }

        $accessCode = data_get($response->json(), 'data.access_code');
        if (! is_string($accessCode) || $accessCode === '') {
            return response()->json(['message' => 'Paystack did not return an access code.'], 502);
        }

        return response()->json(['reference' => $reference, 'access_code' => $accessCode, 'amount' => $amountMinor, 'currency' => $currency, 'email' => $email]);
    }

    /** Confirm the charge, buy the number from Twilio and start the subscription with the saved card. */
    public function paystackVerify(Request $request, TwilioService $twilio, PaystackService $paystack): JsonResponse
    {
        $reference = trim((string) $request->input('reference', ''));
        if ($reference === '') {
            return response()->json(['message' => 'Missing reference.'], 422);
        }

        $payment = Payment::where('provider', 'paystack')->where('provider_reference', $reference)->first();
        if (! $payment || (int) $payment->user_id !== (int) Auth::id() || (string) $payment->asset_type !== 'virtual_number') {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        if ((string) $payment->status === 'fulfilled' && is_array($payment->fulfillment_payload)) {
            return response()->json(['ok' => true, 'subscription' => $payment->fulfillment_payload]);
        }

        if (! $paystack->isConfigured()) {
            return response()->json(['message' => 'Paystack secret key is not configured.'], 500);
        }

        try {
            $response = $paystack->verify($reference);
        } catch (Throwable) {
            return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'Failed to verify Paystack transaction.'], 502);
        }

        $tx = (array) data_get($response->json(), 'data', []);
        if ((string) ($tx['status'] ?? '') !== 'success') {
            return response()->json(['message' => 'Payment not successful.'], 422);
        }

        // The payer must be the address we initialised with (owner email or the user's own).
        $vn = (array) data_get($payment->provider_payload, 'virtual_number', []);
        $payer = trim(mb_strtolower((string) data_get($tx, 'customer.email', '')));
        $expectedEmail = trim(mb_strtolower((string) ($vn['paystack_email'] ?? ''))) ?: trim(mb_strtolower((string) (Auth::user()?->email ?? '')));
        if ($payer === '' || $payer !== $expectedEmail) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        $txCurrency = strtoupper((string) ($tx['currency'] ?? ''));
        $txAmount = (int) ($tx['amount'] ?? 0);
        if ($txCurrency === '' || $txAmount <= 0 || $txCurrency !== strtoupper((string) $payment->currency) || $txAmount !== (int) $payment->amount_minor) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        $phoneNumber = trim((string) ($vn['phone_number'] ?? ''));
        $productId = (int) ($vn['product_id'] ?? 0);
        if ($phoneNumber === '' || $productId <= 0) {
            return response()->json(['message' => 'Missing subscription details.'], 422);
        }

        $product = VirtualNumberProduct::query()->where('active', true)->find($productId);
        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        // Renewals charge this authorization (see RenewVirtualNumbers), so it's mandatory.
        $authCode = trim((string) data_get($tx, 'authorization.authorization_code', ''));
        $customerCode = trim((string) data_get($tx, 'customer.customer_code', ''));
        if ($authCode === '' || $customerCode === '') {
            return response()->json(['message' => 'Subscription billing authorization missing.'], 502);
        }

        $purchase = $twilio->purchaseNumber(
            $phoneNumber,
            $product->cap_sms ? (string) (config('services.twilio.sms_webhook_url') ?: url('/api/twilio/sms')) : null,
            $product->cap_voice ? (string) (config('services.twilio.voice_webhook_url') ?: url('/api/twilio/voice')) : null,
        );
        if (($purchase['ok'] ?? false) !== true) {
            $payment->update(['status' => 'paid_failed_provision', 'fulfillment_payload' => ['ok' => false, 'error' => (string) ($purchase['error'] ?? 'Twilio provisioning failed.')]]);

            return response()->json(['message' => 'Number provisioning failed.'], 502);
        }

        $now = now();
        $sub = VirtualNumberSubscription::create([
            'user_id' => Auth::id(),
            'virtual_number_product_id' => $product->id,
            'status' => 'active',
            'phone_number' => (string) ($purchase['phone_number'] ?? $phoneNumber),
            'country_iso' => (string) $product->country_iso,
            'cap_sms' => (bool) $product->cap_sms,
            'cap_voice' => (bool) $product->cap_voice,
            'provider' => 'twilio',
            'twilio_phone_number_sid' => (string) ($purchase['sid'] ?? ''),
            'currency' => (string) $payment->currency,
            'monthly_amount_minor' => (int) $payment->amount_minor,
            'current_period_start' => $now,
            'current_period_end' => $now->copy()->addMonth(),
            'paystack_customer_code' => $customerCode,
            'paystack_authorization_code' => $authCode,
            'paystack_email' => $expectedEmail ?: null,
            'last_charge_reference' => $reference,
            'renewal_failed_count' => 0,
        ]);

        $payload = [
            'ok' => true,
            'subscription_id' => $sub->id,
            'phone_number' => $sub->phone_number,
            'status' => $sub->status,
            'current_period_end' => $sub->current_period_end?->toIso8601String(),
        ];
        $payment->update(['status' => 'fulfilled', 'fulfillment_payload' => $payload]);

        $to = trim((string) (Auth::user()?->email ?? ''));
        if (filter_var($to, FILTER_VALIDATE_EMAIL) !== false) {
            try {
                Mail::to($to)->send(NoticeMail::numberActivated($sub, $product));
            } catch (Throwable) {
            }
        }

        return response()->json(['ok' => true, 'subscription' => $payload]);
    }

    // ─── Subscriptions & inbox ──────────────────────────────────────────────────

    public function my(): JsonResponse
    {
        $items = VirtualNumberSubscription::with('product')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (VirtualNumberSubscription $s) => [
                'id' => (int) $s->id,
                'status' => (string) $s->status,
                'phone_number' => (string) ($s->phone_number ?? ''),
                'country_iso' => (string) ($s->country_iso ?? $s->product?->country_iso ?? ''),
                'plan_label' => (string) ($s->product?->label ?: 'Virtual Number'),
                'currency' => (string) ($s->currency ?? 'USD'),
                'monthly_amount_minor' => (int) ($s->monthly_amount_minor ?? 0),
                'current_period_end' => $s->current_period_end?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /** Release the number at Twilio and stop renewals. Idempotent. */
    public function cancel(VirtualNumberSubscription $subscription, TwilioService $twilio): JsonResponse
    {
        $this->authorizeOwner($subscription);

        if ($subscription->status !== 'canceled') {
            if ($sid = (string) ($subscription->twilio_phone_number_sid ?? '')) {
                $twilio->releaseNumber($sid);
            }
            $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function messages(VirtualNumberSubscription $subscription, Request $request): JsonResponse
    {
        $this->authorizeOwner($subscription);

        $items = VirtualNumberMessage::query()
            ->where('virtual_number_subscription_id', $subscription->id)
            ->orderByDesc('id')
            ->limit(max(1, min(200, (int) $request->query('per_page', 50))))
            ->get()
            ->map(fn (VirtualNumberMessage $m) => $this->messagePayload($m) + [
                'media_count' => is_array($m->media) ? count($m->media) : 0,
                'has_recording' => $m->message_type === 'voicemail' && trim((string) ($m->recording_url ?? '')) !== '',
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'items' => $items]);
    }

    public function settings(VirtualNumberSubscription $subscription): JsonResponse
    {
        $this->authorizeOwner($subscription);

        return response()->json(['ok' => true, 'settings' => $this->forwardingSettings($subscription)]);
    }

    /** Where inbound SMS/voicemail are forwarded. Empty values turn forwarding off. */
    public function updateSettings(VirtualNumberSubscription $subscription, Request $request): JsonResponse
    {
        $this->authorizeOwner($subscription);

        $email = trim((string) $request->input('forward_to_email', ''));
        $phone = trim((string) $request->input('forward_to_phone', ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json(['message' => 'Invalid forwarding email.'], 422);
        }
        if ($phone !== '' && ! preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            return response()->json(['message' => 'Invalid forwarding phone. Use E.164 format like +14155552671.'], 422);
        }

        $subscription->update(['forward_to_email' => $email ?: null, 'forward_to_phone' => $phone ?: null]);

        return response()->json(['ok' => true, 'settings' => $this->forwardingSettings($subscription)]);
    }

    public function sendMessage(VirtualNumberSubscription $subscription, Request $request, TwilioService $twilio): JsonResponse
    {
        $this->authorizeOwner($subscription);

        $to = trim((string) $request->input('to', ''));
        $body = trim((string) $request->input('body', ''));
        $mediaUrls = $request->input('media_urls', []);
        $mediaUrls = is_array($mediaUrls) ? array_values(array_filter(array_map(fn ($u) => trim((string) $u), $mediaUrls))) : [];

        if ($to === '' || ($body === '' && $mediaUrls === [])) {
            return response()->json(['message' => 'Missing to/body.'], 422);
        }
        if (! $twilio->isConfigured()) {
            return response()->json(['message' => 'Twilio is not configured.'], 500);
        }

        $from = (string) ($subscription->phone_number ?? '');
        $res = $twilio->sendMessage($from, $to, $body, $mediaUrls);
        if (($res['ok'] ?? false) !== true) {
            return response()->json(['message' => (string) ($res['error'] ?? 'Failed to send message.')], 502);
        }

        $message = VirtualNumberMessage::create([
            'virtual_number_subscription_id' => $subscription->id,
            'direction' => 'outbound',
            'message_type' => $mediaUrls === [] ? 'sms' : 'mms',
            'from' => $from,
            'to' => $to,
            'body' => $body,
            'twilio_message_sid' => ((string) ($res['sid'] ?? '')) ?: null,
            'media' => $mediaUrls === [] ? null : array_map(fn ($u) => ['url' => $u], $mediaUrls),
            'raw' => $res['raw'] ?? null,
        ]);

        return response()->json(['ok' => true, 'message' => $this->messagePayload($message)]);
    }

    /** Proxy an MMS attachment (Twilio media URLs need our account credentials). */
    public function media(VirtualNumberMessage $message, int $index, TwilioService $twilio)
    {
        $this->authorizeOwner($message->subscription);

        $url = trim((string) data_get($message->media, "{$index}.url", ''));
        if ($url === '') {
            abort(404);
        }

        return $this->proxy($twilio, $url, 'Failed to fetch media.');
    }

    /** Proxy a voicemail recording as MP3. */
    public function recording(VirtualNumberMessage $message, TwilioService $twilio)
    {
        $this->authorizeOwner($message->subscription);

        $url = trim((string) ($message->recording_url ?? ''));
        if ($url === '') {
            abort(404);
        }

        return $this->proxy($twilio, str_ends_with($url, '.mp3') ? $url : $url.'.mp3', 'Failed to fetch recording.', 'audio/mpeg');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function authorizeOwner(?VirtualNumberSubscription $subscription): void
    {
        if (! $subscription || (int) $subscription->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function forwardingSettings(VirtualNumberSubscription $subscription): array
    {
        return [
            'forward_to_email' => (string) ($subscription->forward_to_email ?? ''),
            'forward_to_phone' => (string) ($subscription->forward_to_phone ?? ''),
        ];
    }

    private function messagePayload(VirtualNumberMessage $m): array
    {
        return [
            'id' => (int) $m->id,
            'direction' => (string) $m->direction,
            'message_type' => (string) ($m->message_type ?? 'sms'),
            'from' => (string) ($m->from ?? ''),
            'to' => (string) ($m->to ?? ''),
            'body' => (string) ($m->body ?? ''),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function proxy(TwilioService $twilio, string $url, string $error, ?string $contentType = null)
    {
        try {
            $res = $twilio->fetchProtectedUrl($url);
        } catch (Throwable) {
            return response($error, 502);
        }

        if (! $res->successful()) {
            return response($error, 502);
        }

        return response($res->body(), 200)->header('Content-Type', $contentType ?? ($res->header('Content-Type') ?: 'application/octet-stream'));
    }
}
