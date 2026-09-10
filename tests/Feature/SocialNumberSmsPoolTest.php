<?php

namespace Tests\Feature;

use App\Models\SocialNumberOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialNumberSmsPoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.smspool.api_key', 'test-key');
        config()->set('services.smspool.base_url', 'https://api.smspool.net');
        Cache::flush();
    }

    private function fakeCatalog(array $extra = []): void
    {
        Http::fake(array_merge([
            'api.smspool.net/country/retrieve_all' => Http::response([
                ['ID' => 1, 'name' => 'United States', 'short_name' => 'US', 'region' => 'North America'],
                ['ID' => 2, 'name' => 'United Kingdom', 'short_name' => 'GB', 'region' => 'Europe'],
            ]),
            'api.smspool.net/service/retrieve_all' => Http::response([
                ['ID' => 1012, 'name' => 'WhatsApp', 'favourite' => 0],
                ['ID' => 907, 'name' => 'Telegram', 'favourite' => 0],
            ]),
        ], $extra));
    }

    public function test_apps_grid_reports_real_coverage_from_the_api(): void
    {
        $user = User::factory()->create();

        $this->fakeCatalog([
            'api.smspool.net/request/suggested_countries' => Http::response([
                ['pool' => 7, 'country_id' => 1, 'name' => 'United States', 'short_name' => 'US', 'price' => '0.30'],
                ['pool' => 7, 'country_id' => 2, 'name' => 'United Kingdom', 'short_name' => 'GB', 'price' => '0.50'],
            ]),
        ]);

        $res = $this->actingAs($user)->getJson('/api/social-numbers/apps');
        $res->assertOk();
        $items = collect($res->json('items'));

        $this->assertGreaterThanOrEqual(12, $items->count());
        $wa = $items->firstWhere('key', 'whatsapp');
        $this->assertSame('2 countries', $wa['coverage']);
        $this->assertSame(2, $wa['qty']);
        $this->assertTrue($wa['available']);
        $this->assertNotNull($wa['price']);
    }

    public function test_services_endpoint_lists_and_filters_the_full_catalogue(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.smspool.net/service/retrieve_all' => Http::response([
                ['ID' => 1, 'name' => 'Airbnb', 'favourite' => 0],
                ['ID' => 2, 'name' => 'Bumble', 'favourite' => 0],
                ['ID' => 3, 'name' => 'Coinbase', 'favourite' => 0],
            ]),
        ]);

        $all = $this->actingAs($user)->getJson('/api/social-numbers/services?limit=2');
        $all->assertOk()->assertJsonPath('total', 3)->assertJsonPath('has_more', true);
        $this->assertCount(2, $all->json('items'));

        $filtered = $this->actingAs($user)->getJson('/api/social-numbers/services?q=bumb');
        $filtered->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.name', 'Bumble');
        $this->assertSame('2', $filtered->json('items.0.key'));
    }

    public function test_arbitrary_catalogue_service_can_be_quoted(): void
    {
        $user = User::factory()->create();
        $this->fakeCatalog([
            'api.smspool.net/service/retrieve_all' => Http::response([
                ['ID' => 142, 'name' => 'Bumble', 'favourite' => 0],
            ]),
            'api.smspool.net/pool/retrieve_valid' => Http::response([
                ['pool' => 7, 'name' => 'Foxtrot', 'price' => '0.16'],
            ]),
            'api.smspool.net/sms/stock' => Http::response(['success' => 1, 'amount' => 16720]),
        ]);

        $res = $this->actingAs($user)->getJson('/api/social-numbers/prices?country=US&product=142');
        $res->assertOk()->assertJsonPath('total_count', 16720)->assertJsonPath('product', '142');
    }

    public function test_rental_purchase_hides_provider_balance_errors_and_refunds(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 5000000]);

        $this->fakeCatalog([
            'api.smspool.net/service/retrieve_all' => Http::response([['ID' => 1012, 'name' => 'WhatsApp', 'favourite' => 0]]),
            'api.smspool.net/rental/retrieve_all' => Http::response([
                'success' => 1,
                'data' => [['ID' => 6, 'name' => 'United States', 'tag' => 'United States', 'region' => 'North America', 'pricing' => ['30' => 20], 'pool' => 7, 'is_refundable' => 1]],
            ]),
            'api.smspool.net/rental/retrieve_services' => Http::response([['ID' => 1012, 'name' => 'WhatsApp', 'pool' => 7]]),
            'api.smspool.net/rental/stock' => Http::response(['success' => 1, 'count' => 5]),
            'api.smspool.net/purchase/rental' => Http::response([
                'success' => 0,
                'message' => 'You do not have enough balance, please top up your account with 15.29 dollars in order to purchase this rental.',
            ]),
        ]);

        $before = $user->wallet_balance_minor;
        $res = $this->actingAs($user)->postJson('/api/social-rentals/buy', ['product' => 'whatsapp', 'country' => 'US']);

        $res->assertStatus(502);
        $this->assertStringNotContainsStringIgnoringCase('top up', $res->json('message'));
        $this->assertStringNotContainsStringIgnoringCase('balance', $res->json('message'));
        $this->assertSame($before, (int) $user->fresh()->wallet_balance_minor); // debited then refunded
    }

    public function test_otp_buy_then_receive_then_finish(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 500000]);

        $this->fakeCatalog([
            'api.smspool.net/pool/retrieve_valid' => Http::response([
                ['pool' => 7, 'name' => 'Foxtrot', 'price' => '0.30'],
                ['pool' => 2, 'name' => 'Bravo', 'price' => '0.24'],
            ]),
            'api.smspool.net/sms/stock' => Http::response(['success' => 1, 'amount' => 42]),
            'api.smspool.net/purchase/sms' => Http::response([
                'success' => 1,
                'number' => 12025550123,
                'cc' => '1',
                'phonenumber' => '2025550123',
                'order_id' => 'ABCDEFGH',
                'pool' => 2,
                'expires_in' => 1200,
                'expiration' => now()->addMinutes(20)->timestamp,
                'cost' => '0.24',
                'cost_in_cents' => 24,
            ]),
            'api.smspool.net/sms/check' => Http::response([
                'status' => 3, 'sms' => '558123', 'full_sms' => 'Your code is 558123',
                'expiration' => now()->addMinutes(20)->timestamp,
            ]),
        ]);

        $prices = $this->actingAs($user)->getJson('/api/social-numbers/prices?country=US&product=whatsapp');
        $prices->assertOk()->assertJsonPath('total_count', 42);
        $this->assertSame('$1.00', $prices->json('min_cost')); // 0.24 * 1.4 = 0.336 -> min 100 minor

        $buy = $this->actingAs($user)->postJson('/api/social-numbers/buy', [
            'product' => 'whatsapp', 'country' => 'US',
        ]);
        $buy->assertOk()->assertJsonPath('order.status', 'PENDING');
        $orderId = $buy->json('order.id');
        $this->assertSame('+12025550123', $buy->json('order.phone_display'));

        $check = $this->actingAs($user)->getJson("/api/social-numbers/check/{$orderId}");
        $check->assertOk()->assertJsonPath('order.status', 'RECEIVED');
        $this->assertSame('558123', $check->json('order.sms.0.code'));

        $finish = $this->actingAs($user)->postJson("/api/social-numbers/orders/{$orderId}/finish");
        $finish->assertOk()->assertJsonPath('order.status', 'FINISHED');
    }

    public function test_provider_refund_status_times_out_and_refunds_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 500000]);

        $this->fakeCatalog([
            'api.smspool.net/pool/retrieve_valid' => Http::response([['pool' => 2, 'name' => 'Bravo', 'price' => '0.24']]),
            'api.smspool.net/sms/stock' => Http::response(['success' => 1, 'amount' => 10]),
            'api.smspool.net/purchase/sms' => Http::response([
                'success' => 1, 'number' => 12025550999, 'cc' => '1', 'phonenumber' => '2025550999',
                'order_id' => 'DEADBEEF', 'pool' => 2, 'expires_in' => 1200,
                'expiration' => now()->addMinutes(20)->timestamp, 'cost_in_cents' => 24,
            ]),
            'api.smspool.net/sms/check' => Http::response(['status' => 6, 'message' => 'This order has been refunded', 'time_left' => 900]),
            'api.smspool.net/sms/cancel' => Http::response(['success' => 1, 'message' => 'refunded']),
        ]);

        $buy = $this->actingAs($user)->postJson('/api/social-numbers/buy', ['product' => 'whatsapp', 'country' => 'US']);
        $buy->assertOk();
        $orderId = $buy->json('order.id');
        $balanceAfterBuy = $user->fresh()->wallet_balance_minor;

        $check = $this->actingAs($user)->getJson("/api/social-numbers/check/{$orderId}");
        $check->assertOk()->assertJsonPath('order.status', 'TIMEOUT');

        $this->assertGreaterThan($balanceAfterBuy, $user->fresh()->wallet_balance_minor);
        $this->assertNotNull(SocialNumberOrder::find($orderId)->refunded_at);
    }

    public function test_rental_buy_then_messages(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 5000000]);

        $this->fakeCatalog([
            'api.smspool.net/rental/retrieve_all' => Http::response([
                'success' => 1,
                'data' => [
                    ['ID' => 6, 'name' => 'United States', 'tag' => 'United States', 'region' => 'North America', 'pricing' => ['30' => 20], 'pool' => 7, 'is_refundable' => 1],
                ],
            ]),
            'api.smspool.net/rental/retrieve_services' => Http::response([
                ['ID' => 1012, 'name' => 'WhatsApp', 'pool' => 7],
            ]),
            'api.smspool.net/rental/stock' => Http::response(['success' => 1, 'count' => 12]),
            'api.smspool.net/purchase/rental' => Http::response([
                'success' => 1, 'message' => 'ordered', 'phonenumber' => '12025551234',
                'days' => 30, 'rental_code' => 'RENT1234', 'expiry' => now()->addDays(30)->timestamp,
            ]),
            'api.smspool.net/rental/retrieve_messages' => Http::response([
                'success' => 1,
                'messages' => [
                    ['ID' => 1, 'message' => 'Your verification code is: 4321', 'sender' => '4321', 'timestamp' => '2026-01-06 17:37:53'],
                ],
                'source' => 7,
            ]),
        ]);

        $quote = $this->actingAs($user)->getJson('/api/social-rentals/quote?country=US&product=whatsapp');
        $quote->assertOk()->assertJsonPath('count', 12);
        $this->assertSame('$27.00', $quote->json('monthly_amount_formatted')); // 20 * 1.35

        $buy = $this->actingAs($user)->postJson('/api/social-rentals/buy', ['product' => 'whatsapp', 'country' => 'US']);
        $buy->assertOk()->assertJsonPath('rental.provider_order_id', 'RENT1234');
        $rentalId = $buy->json('rental.id');

        $sms = $this->actingAs($user)->getJson("/api/social-rentals/{$rentalId}/sms");
        $sms->assertOk();
        $this->assertSame('Your verification code is: 4321', $sms->json('rental.sms.0.text'));
    }
}
