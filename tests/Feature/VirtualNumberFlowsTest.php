<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\VirtualNumberMessage;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Services\TwilioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Twilio virtual numbers bought with Paystack: checkout, verification/provisioning,
 * and the owner-only inbox endpoints. Twilio is stubbed; Paystack is faked over HTTP.
 */
class VirtualNumberFlowsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private VirtualNumberProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'services.paystack.secret_key' => 'sk_test', 'services.paystack.currency' => 'NGN', 'virtual_numbers.usd_to_ngn_rate' => 1500]);
        Http::preventStrayRequests();
        $this->user = User::factory()->create(['email' => 'owner@example.com']);
        $this->product = VirtualNumberProduct::query()->forceCreate(['country_iso' => 'US', 'label' => 'US Local', 'currency' => 'USD', 'monthly_amount_minor' => 300, 'active' => true]);

        $this->app->instance(TwilioService::class, new class extends TwilioService
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function phoneNumberPricing(string $countryIso): array
            {
                return ['ok' => true, 'currency' => 'USD', 'prices_minor' => ['Local' => 450, 'TollFree' => 900]];
            }

            public function purchaseNumber(string $phoneNumber, ?string $smsUrl, ?string $voiceUrl): array
            {
                return ['ok' => true, 'phone_number' => $phoneNumber, 'sid' => 'PN1'];
            }

            public function releaseNumber(string $sid): array
            {
                return ['ok' => true];
            }

            public array $sent = [];

            public function availableCountries(): array
            {
                return ['ok' => false, 'error' => 'Twilio is down.'];
            }

            public function validateSignature(string $url, array $params, string $signature): bool
            {
                return $signature === 'good';
            }

            public function sendMessage(string $from, string $to, string $body, array $mediaUrls = []): array
            {
                $this->sent[] = [$from, $to, $body];

                return ['ok' => true, 'sid' => 'SM1'];
            }
        });
    }

    public function test_checkout_converts_live_usd_price_to_ngn(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['data' => ['access_code' => 'AC']])]);

        $res = $this->actingAs($this->user)->postJson('/api/virtual-numbers/paystack/initialize', ['product_id' => $this->product->id, 'phone_number' => '+15551112222', 'number_type' => 'Local']);

        $res->assertOk()->assertJson(['access_code' => 'AC', 'amount' => 675000, 'currency' => 'NGN', 'email' => 'owner@example.com']);
        $vn = Payment::firstOrFail()->provider_payload['virtual_number'];
        $this->assertSame([450, 'USD', 1500], [$vn['twilio_price_minor'], $vn['twilio_price_currency'], (int) $vn['usd_to_ngn_rate']]);
    }

    public function test_verify_provisions_number_and_saves_billing_authorization(): void
    {
        Payment::create([
            'user_id' => $this->user->id, 'provider' => 'paystack', 'provider_reference' => 'vn-1', 'status' => 'initialized',
            'asset_type' => 'virtual_number', 'asset_id' => (string) $this->product->id, 'bundle_id' => (string) $this->product->id, 'package_type' => 'MONTHLY',
            'currency' => 'NGN', 'amount_minor' => 675000,
            'provider_payload' => ['virtual_number' => ['product_id' => $this->product->id, 'phone_number' => '+15551112222', 'paystack_email' => 'owner@example.com']],
        ]);
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => [
            'status' => 'success', 'currency' => 'NGN', 'amount' => 675000,
            'customer' => ['email' => 'OWNER@example.com', 'customer_code' => 'CUS_1'],
            'authorization' => ['authorization_code' => 'AUTH_1'],
        ]])]);

        $this->actingAs($this->user)->postJson('/api/virtual-numbers/paystack/verify', ['reference' => 'vn-1'])
            ->assertOk()->assertJson(['ok' => true, 'subscription' => ['phone_number' => '+15551112222', 'status' => 'active']]);

        $sub = VirtualNumberSubscription::firstOrFail();
        $this->assertSame(['AUTH_1', 'CUS_1', 'owner@example.com', 675000], [$sub->paystack_authorization_code, $sub->paystack_customer_code, $sub->paystack_email, (int) $sub->monthly_amount_minor]);
        $this->assertSame('fulfilled', Payment::firstOrFail()->status);
        $this->assertSame('Your Spacechip virtual number is active', app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getSubject());

        // Verifying again returns the stored subscription without buying a second number.
        $this->actingAs($this->user)->postJson('/api/virtual-numbers/paystack/verify', ['reference' => 'vn-1'])->assertOk()->assertJsonPath('subscription.phone_number', '+15551112222');
        $this->assertSame(1, VirtualNumberSubscription::count());
    }

    public function test_inbox_endpoints_are_owner_only_and_settings_validate(): void
    {
        $sub = VirtualNumberSubscription::query()->forceCreate(['user_id' => $this->user->id, 'virtual_number_product_id' => $this->product->id, 'status' => 'active', 'phone_number' => '+15550000000', 'country_iso' => 'US', 'provider' => 'twilio', 'twilio_phone_number_sid' => 'PN9', 'currency' => 'USD', 'monthly_amount_minor' => 300]);
        VirtualNumberMessage::query()->forceCreate(['virtual_number_subscription_id' => $sub->id, 'direction' => 'inbound', 'message_type' => 'sms', 'from' => '+1999', 'to' => '+15550000000', 'body' => 'hi', 'media' => [['url' => 'x']]]);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->getJson("/api/virtual-numbers/{$sub->id}/messages")->assertForbidden();
        $this->actingAs($this->user)->getJson("/api/virtual-numbers/{$sub->id}/messages")
            ->assertOk()->assertJson(['items' => [['body' => 'hi', 'media_count' => 1, 'has_recording' => false, 'direction' => 'inbound']]]);

        $this->actingAs($this->user)->postJson("/api/virtual-numbers/{$sub->id}/settings", ['forward_to_phone' => '555'])->assertStatus(422);
        $this->actingAs($this->user)->postJson("/api/virtual-numbers/{$sub->id}/settings", ['forward_to_email' => 'f@example.com'])
            ->assertOk()->assertExactJson(['ok' => true, 'settings' => ['forward_to_email' => 'f@example.com', 'forward_to_phone' => '']]);

        $this->actingAs($this->user)->postJson("/api/virtual-numbers/{$sub->id}/cancel")->assertOk();
        $this->assertSame('canceled', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->canceled_at);
    }

    public function test_twilio_webhooks_store_and_forward_sms_and_voicemail(): void
    {
        $sub = VirtualNumberSubscription::query()->forceCreate(['user_id' => $this->user->id, 'virtual_number_product_id' => $this->product->id, 'status' => 'active', 'phone_number' => '+15550000000', 'country_iso' => 'US', 'provider' => 'twilio', 'currency' => 'USD', 'monthly_amount_minor' => 300, 'forward_to_email' => 'fwd@example.com', 'forward_to_phone' => '+2348000000000']);
        $twilio = app(TwilioService::class);

        $this->post('/api/twilio/sms', ['To' => '+15550000000', 'From' => '+1999'], ['X-Twilio-Signature' => 'bad'])->assertForbidden();

        $this->post('/api/twilio/sms', ['To' => '+15550000000', 'From' => '+1999', 'Body' => 'code <b>42</b>', 'MessageSid' => 'SM9', 'NumMedia' => 1, 'MediaUrl0' => 'https://m.test/1', 'MediaContentType0' => 'image/png'], ['X-Twilio-Signature' => 'good'])
            ->assertOk()->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $msg = VirtualNumberMessage::firstOrFail();
        $this->assertSame(['mms', 'code <b>42</b>', 'SM9', [['url' => 'https://m.test/1', 'content_type' => 'image/png']]], [$msg->message_type, $msg->body, $msg->twilio_message_sid, $msg->media]);
        $this->assertSame([['+15550000000', '+2348000000000', 'Spacechip SMS from +1999: code <b>42</b>']], $twilio->sent);
        $mail = app('mailer')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
        $this->assertSame(['fwd@example.com', 'New message to your Spacechip number'], [$mail[0]->getTo()[0]->getAddress(), $mail[0]->getSubject()]);
        $this->assertStringContainsString('code &lt;b&gt;42&lt;/b&gt;', $mail[0]->getHtmlBody());

        $this->post('/api/twilio/voice', ['To' => '+19999999999'], ['X-Twilio-Signature' => 'good'])->assertSee('<Reject/>', false);
        $this->post('/api/twilio/voice', ['To' => '+15550000000'], ['X-Twilio-Signature' => 'good'])->assertSee('<Record maxLength="120"', false);

        $recording = ['To' => '+15550000000', 'From' => '+1888', 'RecordingSid' => 'RE1', 'RecordingUrl' => 'https://r.test/RE1', 'RecordingDuration' => 12, 'CallSid' => 'CA1'];
        $this->post('/api/twilio/voice/recording', $recording, ['X-Twilio-Signature' => 'good'])->assertOk();
        $this->post('/api/twilio/voice/recording', $recording, ['X-Twilio-Signature' => 'good'])->assertOk();

        $this->assertSame(1, VirtualNumberMessage::where('message_type', 'voicemail')->count());
        $this->assertSame('Spacechip voicemail from +1888 to +15550000000 (12s). Check dashboard inbox.', end($twilio->sent)[2]);
        $this->assertSame('New voicemail on your Spacechip number', app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getSubject());
    }

    public function test_dashboard_pages_price_from_live_rates_and_create_missing_products(): void
    {
        $this->actingAs($this->user)->get('/dashboard/virtual-numbers/ng')
            ->assertOk()->assertSee('$4.50');
        $this->assertSame(['NG Local Number', 500], [VirtualNumberProduct::where('country_iso', 'NG')->value('label'), (int) VirtualNumberProduct::where('country_iso', 'NG')->value('monthly_amount_minor')]);

        $this->actingAs($this->user)->get("/dashboard/virtual-numbers/checkout?product_id={$this->product->id}&phone_number=%2B15551112222&number_type=TollFree")
            ->assertOk()->assertSee('$9.00');
        $this->actingAs($this->user)->get('/dashboard/virtual-numbers/checkout?product_id=999&phone_number=x')->assertNotFound();

        // A Twilio outage must not be cached as an empty country list.
        $this->assertNull(cache()->get('twilio.countries.v2'));
    }
}
