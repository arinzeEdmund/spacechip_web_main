<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present(): void
    {
        $res = $this->get('/');
        $res->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertTrue($res->headers->has('Permissions-Policy'));
    }

    public function test_otp_is_stored_hashed_and_locks_after_five_wrong_tries(): void
    {
        $user = User::factory()->unverified()->create();
        $otp = $user->issueEmailOtp();
        $this->assertNotSame($otp, $user->fresh()->email_otp);

        $user = $user->fresh();
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($user->checkEmailOtp($otp === '000000' ? '111111' : '000000'));
        }
        $this->assertFalse($user->fresh()->checkEmailOtp($otp)); // even the right code is dead after 5 misses

        $fresh = $user->fresh();
        $new = $fresh->issueEmailOtp(); // a new code resets it
        $this->assertTrue($fresh->fresh()->checkEmailOtp($new));
    }

    public function test_password_rules_require_ten_characters(): void
    {
        $this->post('/register', ['name' => 'A', 'email' => 'a@example.com', 'password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
    }

    public function test_password_change_revokes_mobile_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');
        $this->assertSame(1, $user->tokens()->count());
        $user->update(['password' => 'a-brand-new-password-1']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_mobile_tokens_expire(): void
    {
        $this->assertSame(60 * 24 * 30, (int) config('sanctum.expiration'));
    }

    private function depositFixture(): array
    {
        config(['services.paystack.secret_key' => 'sk_test_x', 'services.paystack.currency' => 'NGN']);
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id, 'provider' => 'paystack', 'provider_reference' => 'ref_123', 'status' => 'created',
            'asset_type' => 'wallet_deposit', 'asset_id' => 'wallet', 'bundle_id' => 'wallet', 'package_type' => 'WALLET',
            'currency' => 'NGN', 'amount_minor' => 1500000,
            'provider_payload' => ['wallet' => ['usd_amount_minor' => 1000]],
        ]);

        return [$user, $payment];
    }

    private function signed(array $body, string $secret = 'sk_test_x'): array
    {
        $raw = json_encode($body);

        return [$raw, ['x-paystack-signature' => hash_hmac('sha512', $raw, $secret), 'CONTENT_TYPE' => 'application/json']];
    }

    public function test_paystack_webhook_rejects_bad_signature(): void
    {
        [$user] = $this->depositFixture();
        [$raw] = $this->signed(['event' => 'charge.success', 'data' => ['reference' => 'ref_123']]);

        $this->call('POST', '/api/paystack/webhook', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => 'nope', 'CONTENT_TYPE' => 'application/json'], $raw)->assertStatus(401);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance_minor);
    }

    public function test_paystack_webhook_credits_once_after_reverifying_with_paystack(): void
    {
        [$user, $payment] = $this->depositFixture();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'currency' => 'NGN', 'amount' => 1500000]])]);
        [$raw, $h] = $this->signed(['event' => 'charge.success', 'data' => ['reference' => 'ref_123', 'amount' => 1]]); // payload amount is ignored

        $server = ['HTTP_X_PAYSTACK_SIGNATURE' => $h['x-paystack-signature'], 'CONTENT_TYPE' => 'application/json'];
        $this->call('POST', '/api/paystack/webhook', [], [], [], $server, $raw)->assertOk();
        $this->call('POST', '/api/paystack/webhook', [], [], [], $server, $raw)->assertOk(); // replay

        $this->assertSame(1000, (int) $user->fresh()->wallet_balance_minor); // credited exactly once
        $this->assertSame('fulfilled', $payment->fresh()->status);
    }

    public function test_paystack_webhook_does_not_credit_when_paystack_disagrees(): void
    {
        [$user] = $this->depositFixture();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'currency' => 'NGN', 'amount' => 100]])]); // underpaid
        [$raw, $h] = $this->signed(['event' => 'charge.success', 'data' => ['reference' => 'ref_123']]);

        $this->call('POST', '/api/paystack/webhook', [], [], [], ['HTTP_X_PAYSTACK_SIGNATURE' => $h['x-paystack-signature'], 'CONTENT_TYPE' => 'application/json'], $raw)->assertOk();
        $this->assertSame(0, (int) $user->fresh()->wallet_balance_minor);
    }

    public function test_airalo_webhook_requires_the_shared_secret_when_configured(): void
    {
        Cache::flush();
        config(['services.airalo.webhook_secret' => 's3cret']);
        $this->postJson('/api/airalo/webhook', ['event' => 'sim.usage.low'])->assertStatus(401);
        $this->postJson('/api/airalo/webhook?token=s3cret', ['event' => 'other'])->assertOk();
    }
}
