<?php

namespace Tests\Feature;

use App\Models\SocialNumberRental;
use App\Models\User;
use App\Services\Admin\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LongSmsRentalProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.longsms.api_key', 'ls-key');
        config()->set('services.longsms.monthly_cost_usd', 3.0);
        config()->set('services.smspool.api_key', 'sp-key');
        Cache::flush();
    }

    /** The real settings form always posts every switch; a missing checkbox means "off". */
    private function form(array $overrides): array
    {
        $form = ['registrations_enabled' => '1', 'feature_esim_purchases' => '1', 'feature_virtual_numbers' => '1', 'feature_social_numbers' => '1', 'feature_wallet_deposits' => '1'];

        return array_merge($form, $overrides);
    }

    private function useSupplier(string $key): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->put('/admin/settings', $this->form(['social_rental_provider' => $key]))->assertSessionHasNoErrors();
        Cache::flush();
        $this->app['auth']->forgetGuards();
    }

    private function fakeLongSms(): void
    {
        Http::fake(function ($request) {
            return match ($request->data()['action'] ?? '') {
                'previewNumber' => Http::response(['status' => 'SUCCESS', 'data' => ['number' => '+1604', 'charged' => false]]),
                'getNumber' => Http::response(['status' => 'SUCCESS', 'data' => ['number' => '+16045550100', 'amount' => 2, 'expire_at' => '2026-12-01 00:00:00']]),
                'listInbound' => Http::response(['status' => 'SUCCESS', 'data' => ['items' => [
                    ['number' => '+16045550100', 'sender' => 'Google', 'content' => 'Your code is 123456', 'received_at' => '2026-10-07 12:00:01'],
                    ['number' => '+19998887777', 'sender' => 'Other', 'content' => 'not mine', 'received_at' => '2026-10-07 12:00:02'],
                ], 'next_after_id' => 2, 'has_more' => false]]),
                'setStatus' => Http::response(['status' => 'SUCCESS', 'data' => ['expire_at' => '2027-01-01 00:00:00']]),
                default => Http::response(['status' => 'ERROR', 'error' => ['num' => 1002, 'message' => 'bad']], 400),
            };
        });
    }

    public function test_smspool_is_the_default_supplier(): void
    {
        $this->assertSame('smspool', app(SettingsService::class)->get('social_rental_provider'));
    }

    public function test_switching_supplier_is_refused_without_an_api_key(): void
    {
        config()->set('services.longsms.api_key', '');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/settings', $this->form(['social_rental_provider' => 'longsms']))->assertSessionHasErrors('social_rental_provider');
        $this->assertSame('smspool', app(SettingsService::class)->get('social_rental_provider'));
    }

    public function test_invalid_supplier_value_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/settings', $this->form(['social_rental_provider' => 'nope']))->assertSessionHasErrors('social_rental_provider');
    }

    public function test_longsms_rental_quote_buy_inbox_and_same_margin(): void
    {
        $this->fakeLongSms();
        $this->useSupplier('longsms');
        $user = User::factory()->create(['wallet_balance_minor' => 5000000]);

        $apps = $this->actingAs($user)->getJson('/api/social-rentals/apps')->assertOk();
        $this->assertContains('whatsapp', array_column($apps->json('items'), 'key'));
        $this->actingAs($user)->getJson('/api/social-rentals/countries')->assertOk()->assertJsonPath('items.0.iso', 'US');

        // $3.00 cost x 1.35 rental markup = $4.05, below the $5.00 rental minimum -> $5.00 (same rule as SMSPool rentals).
        $quote = $this->actingAs($user)->getJson('/api/social-rentals/quote?country=US&product=whatsapp')->assertOk();
        $this->assertSame('$5.00', $quote->json('monthly_amount_formatted'));

        $before = (int) $user->fresh()->wallet_balance_minor;
        $buy = $this->actingAs($user)->postJson('/api/social-rentals/buy', ['product' => 'whatsapp', 'country' => 'US'])->assertOk();
        $buy->assertJsonPath('rental.provider', 'longsms_rent')->assertJsonPath('rental.phone', '+16045550100');
        $this->assertSame($before - 500, (int) $user->fresh()->wallet_balance_minor);

        $rental = SocialNumberRental::query()->firstOrFail();
        $this->assertSame(200, (int) $rental->provider_cost_minor); // real amount charged by LongSMS
        $this->assertSame('2026-12-01', $rental->current_period_end->toDateString());

        $sms = $this->actingAs($user)->getJson("/api/social-rentals/{$rental->id}/sms")->assertOk();
        $this->assertCount(1, $sms->json('rental.sms'));
        $this->assertSame('Your code is 123456', $sms->json('rental.sms.0.text'));
    }

    public function test_provider_failure_refunds_the_wallet(): void
    {
        Http::fake(fn ($request) => ($request->data()['action'] ?? '') === 'previewNumber'
            ? Http::response(['status' => 'SUCCESS', 'data' => ['number' => '+1']])
            : Http::response(['status' => 'ERROR', 'error' => ['num' => 1201, 'message' => 'Insufficient balance']], 402));
        $this->useSupplier('longsms');
        $user = User::factory()->create(['wallet_balance_minor' => 5000000]);

        $before = (int) $user->fresh()->wallet_balance_minor;
        $res = $this->actingAs($user)->postJson('/api/social-rentals/buy', ['product' => 'whatsapp', 'country' => 'US'])->assertStatus(502);
        $this->assertStringContainsString('temporarily unavailable', $res->json('message')); // our supplier balance is never exposed
        $this->assertSame($before, (int) $user->fresh()->wallet_balance_minor);
    }

    public function test_no_price_means_no_sale(): void
    {
        config()->set('services.longsms.monthly_cost_usd', null);
        Http::fake(fn () => Http::response(['status' => 'SUCCESS', 'data' => ['number' => '+1', 'charged' => false]]));
        $this->useSupplier('longsms');
        $user = User::factory()->create(['wallet_balance_minor' => 5000000]);

        $this->actingAs($user)->getJson('/api/social-rentals/quote?country=US&product=whatsapp')->assertStatus(422);
    }

    public function test_existing_rentals_keep_their_own_supplier_after_switching(): void
    {
        $this->fakeLongSms();
        $this->useSupplier('longsms');
        $user = User::factory()->create(['wallet_balance_minor' => 5000000]);
        $this->actingAs($user)->postJson('/api/social-rentals/buy', ['product' => 'whatsapp', 'country' => 'US'])->assertOk();
        $rental = SocialNumberRental::query()->firstOrFail();

        $this->useSupplier('smspool'); // switch away
        $this->actingAs($user)->getJson("/api/social-rentals/{$rental->id}/sms")->assertOk();
        $this->assertSame('Your code is 123456', $rental->fresh()->sms_messages[0]['text']); // still read from LongSMS

        // ... and renewals still go to LongSMS, not SMSPool.
        $rental->update(['current_period_end' => now()->addHour()]);
        $this->artisan('social-rentals:renew')->assertSuccessful();
        $this->assertSame('2027-01-01', $rental->fresh()->current_period_end->toDateString());
    }
}
