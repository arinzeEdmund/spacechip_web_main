<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\VirtualNumberProduct;
use App\Services\AiraloService;
use App\Services\CryptomusService;
use App\Services\TwilioService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * End-to-end checks for paying for an eSIM by card (Paystack), crypto (Cryptomus) and wallet:
 * payment verification, provisioning through Airalo, the stored record and the delivery email.
 * Airalo is replaced by a deterministic stub; Paystack is faked at the HTTP layer.
 */
class EsimPaymentFlowsTest extends TestCase
{
    use RefreshDatabase;

    public const ICCID = '8901234567890123456';

    private User $user;

    /** Calls the stub received, for asserting which Airalo operation a flow chose. */
    public array $airaloCalls = [];

    /** What the stubbed Airalo purchase returns. */
    public array $purchaseResult = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'services.paystack.secret_key' => '"sk_test"', 'services.cryptomus.payment_key' => 'pk']);
        Cache::flush();

        $this->user = User::factory()->create(['email' => 'buyer@example.com']);
        $this->purchaseResult = $this->delivered();

        $test = $this;
        $this->app->instance(AiraloService::class, new class($test) extends AiraloService
        {
            public function __construct(private EsimPaymentFlowsTest $t) {}

            public function getAssetDetails(string $type, string $id, ?string $packageType = 'DATA-ONLY'): ?array
            {
                return ['name' => 'United States', 'code' => 'US', 'flag' => '🇺🇸', 'flag_url' => '', 'bundles' => [
                    ['id' => 'b1', 'name' => 'US 1GB', 'data' => '1 GB', 'validity' => '7 days', 'price' => 4.5, 'price_formatted' => '$4.50'],
                ]];
            }

            public function fulfillEsim(string $packageId, string $reference, string $customerEmail, string $packageType = 'DATA-ONLY'): array
            {
                $this->t->airaloCalls[] = ['fulfill', $packageId];

                return $this->t->purchaseResult;
            }

            public function topupEsim(string $iccid, string $packageId, string $reference, string $customerEmail): array
            {
                $this->t->airaloCalls[] = ['topup', $iccid, $packageId];

                return $this->t->purchaseResult;
            }

            public function getEsimDetails(string $iccid): array
            {
                $this->t->airaloCalls[] = ['details', $iccid];

                return ['ok' => true, 'esim_id' => 's1', 'iccid' => EsimPaymentFlowsTest::ICCID, 'can_renew' => true, 'activation_code' => 'LPA:1$rsp.airalo.com$CODE', 'lpa' => 'LPA:1$rsp.airalo.com$CODE', 'qr_code_url' => 'https://qr.test/1', 'smdp_address' => 'rsp.airalo.com', 'esim_status' => 'NOT_ACTIVE', 'raw' => ['id' => 's1']];
            }
        });
    }

    private function delivered(): array
    {
        return ['ok' => true, 'esim_id' => 's1', 'order_id' => 77, 'iccid' => self::ICCID, 'activation_code' => 'LPA:1$rsp.airalo.com$CODE', 'lpa' => 'LPA:1$rsp.airalo.com$CODE', 'qr_code_url' => 'https://qr.test/1', 'smdp_address' => 'rsp.airalo.com', 'direct_installation_link_ios' => 'https://ios.test', 'raw' => ['id' => 77]];
    }

    private function payment(array $overrides = []): Payment
    {
        return Payment::create($overrides + [
            'user_id' => $this->user->id,
            'provider' => 'paystack',
            'provider_reference' => 'ref-1',
            'status' => 'initialized',
            'asset_type' => 'country',
            'asset_id' => 'united-states',
            'bundle_id' => 'b1',
            'package_type' => 'DATA-ONLY',
            'currency' => 'NGN',
            'amount_minor' => 700000,
        ]);
    }

    private function fakePaystackVerify(array $data = []): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => $data + [
            'status' => 'success',
            'reference' => 'ref-1',
            'currency' => 'NGN',
            'amount' => 700000,
            'customer' => ['email' => 'Buyer@Example.com'],
            'metadata' => ['type' => 'country', 'id' => 'united-states', 'bundle' => 'b1', 'package_type' => 'DATA-ONLY'],
        ]])]);
    }

    /** @return array<int, Email> */
    private function sentMail(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    private function assertEsimEmailSent(string $to = 'buyer@example.com'): void
    {
        $mail = $this->sentMail();
        $this->assertCount(1, $mail);
        $this->assertSame($to, $mail[0]->getTo()[0]->getAddress());
        $this->assertSame('Your Spacechip eSIM is ready', $mail[0]->getSubject());
        $this->assertStringContainsString(self::ICCID, $mail[0]->getHtmlBody());
        $this->assertStringContainsString('rsp.airalo.com', $mail[0]->getHtmlBody());
        $this->assertSame(['esim-qr.svg'], array_map(fn ($a) => $a->getFilename(), $mail[0]->getAttachments()));
    }

    // ─── Paystack ───────────────────────────────────────────────────────────────

    public function test_paystack_initialize_creates_payment_and_returns_access_code(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['data' => ['access_code' => 'AC_1']])]);

        $res = $this->actingAs($this->user)->postJson('/api/paystack/initialize', ['type' => 'country', 'id' => 'united-states', 'bundle' => 'b1', 'currency' => 'USD']);

        $res->assertOk()->assertJson(['access_code' => 'AC_1', 'amount' => 450, 'currency' => 'USD', 'email' => 'buyer@example.com']);
        $payment = Payment::where('provider_reference', $res->json('reference'))->firstOrFail();
        $this->assertSame(['initialized', 450, 'USD'], [$payment->status, (int) $payment->amount_minor, $payment->currency]);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_test') && $r['metadata']['bundle'] === 'b1');
    }

    public function test_paystack_initialize_rejects_topup_of_unrenewable_esim(): void
    {
        $this->payment(['fulfillment_payload' => ['ok' => true, 'esim_id' => 's1', 'can_renew' => false]]);

        $this->actingAs($this->user)
            ->postJson('/api/paystack/initialize', ['type' => 'country', 'id' => 'united-states', 'bundle' => 'b1', 'topup_esim_id' => 's1'])
            ->assertStatus(422)->assertJson(['message' => 'This eSIM cannot be renewed.']);
    }

    public function test_paystack_verify_provisions_stores_and_emails(): void
    {
        $this->payment();
        $this->fakePaystackVerify();

        $res = $this->actingAs($this->user)->postJson('/api/paystack/verify', ['reference' => 'ref-1']);

        $res->assertOk()->assertJson(['ok' => true, 'fulfillment' => ['ok' => true, 'synced' => true, 'iccid' => self::ICCID, 'esim_id' => 's1', 'order_id' => '77', 'lpa' => 'LPA:1$rsp.airalo.com$CODE', 'direct_installation_link_ios' => 'https://ios.test']]);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $res->json('fulfillment.qr_code_data_url'));

        $payment = Payment::firstOrFail();
        $this->assertSame('fulfilled', $payment->status);
        $this->assertSame(self::ICCID, $payment->fulfillment_payload['iccid']);
        $this->assertTrue($payment->fulfillment_payload['ok']);
        $this->assertSame([['fulfill', 'b1'], ['details', 's1']], $this->airaloCalls);
        $this->assertEsimEmailSent();
        $this->assertStringContainsString('US 1GB', $this->sentMail()[0]->getHtmlBody());

        // A second verify is served from the stored result without provisioning again.
        $this->actingAs($this->user)->postJson('/api/paystack/verify', ['reference' => 'ref-1'])->assertOk()->assertJsonPath('fulfillment.iccid', self::ICCID);
        $this->assertCount(2, $this->airaloCalls);
    }

    public function test_paystack_verify_rejects_amount_mismatch(): void
    {
        $this->payment();
        $this->fakePaystackVerify(['amount' => 1]);

        $this->actingAs($this->user)->postJson('/api/paystack/verify', ['reference' => 'ref-1'])->assertNotFound();
        $this->assertSame([], $this->airaloCalls);
    }

    public function test_paystack_verify_reports_provider_failure_without_marking_fulfilled(): void
    {
        $this->payment();
        $this->fakePaystackVerify();
        $this->purchaseResult = ['ok' => false, 'error' => 'out of stock'];

        $this->actingAs($this->user)->postJson('/api/paystack/verify', ['reference' => 'ref-1'])
            ->assertOk()
            ->assertJson(['ok' => true, 'fulfillment' => ['ok' => false, 'message' => 'Payment confirmed, but eSIM fulfillment failed: out of stock']]);

        $this->assertSame('initialized', Payment::firstOrFail()->status);
        $this->assertSame([], $this->sentMail());
    }

    public function test_paystack_verify_records_pending_purchase(): void
    {
        $this->payment();
        $this->fakePaystackVerify();
        $this->purchaseResult = ['ok' => false, 'pending' => true, 'esim_id' => 's9', 'order_id' => 5, 'error' => 'not ready'];

        $this->actingAs($this->user)->postJson('/api/paystack/verify', ['reference' => 'ref-1'])
            ->assertOk()->assertJson(['fulfillment' => ['ok' => false, 'pending' => true, 'esim_id' => 's9', 'bundle' => 'b1']]);

        $payment = Payment::firstOrFail();
        $this->assertSame('paid_pending_fulfillment', $payment->status);
        $this->assertSame(['ok' => false, 'pending' => true, 'esim_id' => 's9', 'order_id' => 5], array_intersect_key($payment->fulfillment_payload, array_flip(['ok', 'pending', 'esim_id', 'order_id'])));
    }

    public function test_paystack_status_poll_completes_a_pending_order(): void
    {
        $this->payment(['status' => 'paid_pending_fulfillment']);
        Cache::put('paystack.fulfillment.v1.'.sha1('ref-1'), [
            'ok' => true, 'status' => 'success', 'reference' => 'ref-1', 'data' => ['currency' => 'NGN', 'amount' => 700000],
            'fulfillment' => ['ok' => false, 'pending' => true, 'attempted_at' => now()->subMinute()->toIso8601String(), 'type' => 'country', 'id' => 'united-states', 'bundle' => 'b1', 'package_type' => 'DATA-ONLY', 'esim_id' => 's1'],
        ], now()->addHour());

        $this->actingAs($this->user)->postJson('/api/paystack/status', ['reference' => 'ref-1'])
            ->assertOk()->assertJson(['ok' => true, 'fulfillment' => ['ok' => true, 'synced' => true, 'iccid' => self::ICCID, 'esim_id' => 's1']]);

        $this->assertSame('fulfilled', Payment::firstOrFail()->status);
        $this->assertSame([['details', 's1']], $this->airaloCalls);
        $this->assertEsimEmailSent();
    }

    public function test_paystack_status_poll_is_throttled(): void
    {
        $this->payment(['status' => 'paid_pending_fulfillment']);
        $pending = ['ok' => true, 'fulfillment' => ['ok' => false, 'pending' => true, 'attempted_at' => now()->toIso8601String(), 'esim_id' => 's1']];
        Cache::put('paystack.fulfillment.v1.'.sha1('ref-1'), $pending, now()->addHour());

        $this->actingAs($this->user)->postJson('/api/paystack/status', ['reference' => 'ref-1'])->assertOk()->assertExactJson($pending);
        $this->assertSame([], $this->airaloCalls);
    }

    // ─── Cryptomus ──────────────────────────────────────────────────────────────

    private function signedWebhook(array $data): array
    {
        return $data + ['sign' => md5(base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)).'pk')];
    }

    public function test_cryptomus_webhook_rejects_bad_signature(): void
    {
        $this->postJson('/api/cryptomus/webhook', ['order_id' => 'cm-1', 'status' => 'paid', 'is_final' => true, 'sign' => 'nope'])->assertStatus(401);
    }

    public function test_cryptomus_webhook_fulfils_paid_order(): void
    {
        $this->payment(['provider' => 'cryptomus', 'provider_reference' => 'cm-1', 'status' => 'created', 'currency' => 'USD', 'amount_minor' => 450]);

        $this->postJson('/api/cryptomus/webhook', $this->signedWebhook(['order_id' => 'cm-1', 'status' => 'paid', 'is_final' => true]))
            ->assertOk()->assertJson(['ok' => true]);

        $payment = Payment::firstOrFail();
        $this->assertSame('paid', $payment->status);
        $this->assertEquals(['ok' => true, 'synced' => true, 'iccid' => self::ICCID, 'esim_id' => 's1', 'order_id' => '77'], array_intersect_key($payment->fulfillment_payload, array_flip(['ok', 'synced', 'iccid', 'esim_id', 'order_id'])));
        $this->assertSame([['fulfill', 'b1'], ['details', 's1']], $this->airaloCalls);
        $this->assertEsimEmailSent();
    }

    public function test_cryptomus_webhook_ignores_unfinished_payment(): void
    {
        $this->payment(['provider' => 'cryptomus', 'provider_reference' => 'cm-1', 'status' => 'created']);

        $this->postJson('/api/cryptomus/webhook', $this->signedWebhook(['order_id' => 'cm-1', 'status' => 'check', 'is_final' => false]))->assertOk();

        $this->assertSame('check', Payment::firstOrFail()->status);
        $this->assertSame([], $this->airaloCalls);
    }

    public function test_cryptomus_status_poll_fulfils_paid_order(): void
    {
        $this->payment(['provider' => 'cryptomus', 'provider_reference' => 'cm-1', 'status' => 'created', 'currency' => 'USD', 'amount_minor' => 450]);
        $this->app->instance(CryptomusService::class, new class extends CryptomusService
        {
            public function paymentInfo(array $payload): ?array
            {
                return ['state' => 0, 'result' => ['status' => 'paid', 'is_final' => true]];
            }
        });

        $this->actingAs($this->user)->postJson('/api/cryptomus/status', ['order_id' => 'cm-1'])
            ->assertOk()->assertExactJson(['order_id' => 'cm-1', 'status' => 'paid', 'is_final' => true, 'fulfilled' => true, 'synced' => true, 'fulfillment_error' => '']);

        $this->assertSame(self::ICCID, Payment::firstOrFail()->fulfillment_payload['iccid']);
        $this->assertEsimEmailSent();
    }

    // ─── Wallet ─────────────────────────────────────────────────────────────────

    public function test_wallet_pays_for_esim(): void
    {
        app(WalletService::class)->credit($this->user->id, 1000, 'deposit');

        $this->actingAs($this->user)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'united-states', 'bundle' => 'b1'])
            ->assertOk()->assertJson(['ok' => true, 'fulfillment' => ['ok' => true, 'iccid' => self::ICCID, 'esim_id' => 's1']]);

        $this->assertSame(550, app(WalletService::class)->balanceMinor($this->user->id));
        $this->assertSame('fulfilled', Payment::where('provider', 'wallet')->firstOrFail()->status);
    }

    public function test_wallet_refunds_when_provisioning_fails(): void
    {
        app(WalletService::class)->credit($this->user->id, 1000, 'deposit');
        $this->purchaseResult = ['ok' => false, 'error' => 'out of stock'];

        $this->actingAs($this->user)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'united-states', 'bundle' => 'b1'])
            ->assertStatus(502)->assertJson(['message' => 'Payment confirmed, but fulfillment failed. Wallet was refunded.']);

        $this->assertSame(1000, app(WalletService::class)->balanceMinor($this->user->id));
        $this->assertSame('failed_refunded', Payment::where('provider', 'wallet')->firstOrFail()->status);
    }

    public function test_wallet_buys_virtual_number_and_refunds_on_provisioning_failure(): void
    {
        app(WalletService::class)->credit($this->user->id, 2000, 'deposit');
        $product = VirtualNumberProduct::query()->forceCreate(['country_iso' => 'US', 'label' => 'US Local', 'monthly_amount_minor' => 300, 'active' => true]);
        $twilio = new class extends TwilioService
        {
            public bool $fail = false;

            public function isConfigured(): bool
            {
                return true;
            }

            public function phoneNumberPricing(string $countryIso): array
            {
                return ['ok' => true, 'prices_minor' => ['Local' => 450]];
            }

            public function purchaseNumber(string $phoneNumber, ?string $smsUrl, ?string $voiceUrl): array
            {
                return $this->fail ? ['ok' => false, 'error' => 'gone'] : ['ok' => true, 'phone_number' => $phoneNumber, 'sid' => 'PN1'];
            }
        };
        $this->app->instance(TwilioService::class, $twilio);

        $this->actingAs($this->user)->postJson('/api/wallet/pay/virtual-number', ['product_id' => $product->id, 'phone_number' => '+15550001111', 'number_type' => 'Local'])
            ->assertOk()->assertJson(['ok' => true, 'subscription' => ['phone_number' => '+15550001111', 'status' => 'active']]);
        $this->assertSame(1550, app(WalletService::class)->balanceMinor($this->user->id));
        $mail = $this->sentMail();
        $this->assertSame('Your Spacechip virtual number is active', $mail[0]->getSubject());
        $this->assertStringContainsString('+15550001111', $mail[0]->getHtmlBody());

        $twilio->fail = true;
        $this->actingAs($this->user)->postJson('/api/wallet/pay/virtual-number', ['product_id' => $product->id, 'phone_number' => '+15550002222', 'number_type' => 'Local'])
            ->assertStatus(502)->assertJson(['message' => 'Number provisioning failed. Wallet was refunded.']);
        $this->assertSame(1550, app(WalletService::class)->balanceMinor($this->user->id));
    }

    public function test_wallet_deposit_verify_credits_once_and_emails(): void
    {
        $this->payment(['asset_type' => 'wallet_deposit', 'bundle_id' => 'wallet', 'package_type' => 'deposit', 'provider_payload' => ['wallet' => ['usd_amount_minor' => 500]]]);
        $this->fakePaystackVerify();

        $this->actingAs($this->user)->postJson('/api/wallet/deposit/paystack/verify', ['reference' => 'ref-1'])
            ->assertOk()->assertJson(['ok' => true, 'wallet' => ['credited_usd_minor' => 500, 'balance_usd_minor' => 500]]);
        $this->actingAs($this->user)->postJson('/api/wallet/deposit/paystack/verify', ['reference' => 'ref-1'])
            ->assertOk()->assertJson(['already_fulfilled' => true]);

        $this->assertSame(500, app(WalletService::class)->balanceMinor($this->user->id));
        $mail = $this->sentMail();
        $this->assertCount(1, $mail);
        $this->assertSame('Wallet funded successfully', $mail[0]->getSubject());
        $this->assertStringContainsString('$5.00', $mail[0]->getHtmlBody());
    }
}
