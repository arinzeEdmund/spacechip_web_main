<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\SocialNumberRental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialRentalRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.smspool.api_key', 'test-key');
        config()->set('services.smspool.base_url', 'https://api.smspool.net');
    }

    private function makeRental(User $user, array $overrides = []): SocialNumberRental
    {
        return SocialNumberRental::create(array_merge([
            'user_id' => $user->id,
            'provider' => 'smspool_rent',
            'provider_order_id' => 'RENT'.$user->id,
            'status' => 'active',
            'product' => 'whatsapp',
            'product_name' => 'WhatsApp',
            'service_code' => 'WhatsApp',
            'country' => 'GB',
            'country_name' => 'United Kingdom',
            'phone' => '447700900000',
            'phone_country_code' => '',
            'provider_cost_minor' => 2000,
            'monthly_amount_minor' => 2700,
            'currency' => 'USD',
            'auto_renew' => true,
            'current_period_start' => now()->subDays(29),
            'current_period_end' => now()->addDay(),
            'renewal_failed_count' => 0,
            'sms_messages' => [],
            'provider_payload' => ['create' => ['days' => 30], 'quote' => ['days' => 30]],
        ], $overrides));
    }

    public function test_successful_renewal_charges_wallet_and_advances_period(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100000]);
        $rental = $this->makeRental($user);

        Http::fake([
            'api.smspool.net/rental/extend' => Http::response([
                'success' => 1,
                'message' => 'extended',
                'expiration_date' => now()->addDays(31)->timestamp,
            ]),
        ]);

        $this->artisan('social-rentals:renew')->assertExitCode(0);

        $rental->refresh();
        $this->assertSame('active', $rental->status);
        $this->assertSame(0, (int) $rental->renewal_failed_count);
        $this->assertTrue($rental->current_period_end->isAfter(now()->addDays(20)));
        $this->assertSame(97300, (int) $user->fresh()->wallet_balance_minor); // 100000 - 2700
        $this->assertSame('fulfilled', Payment::where('asset_type', 'social_number_rental_renewal')->first()->status);

        Http::assertSent(fn ($req) => str_contains($req->url(), '/rental/extend') && $req['days'] == 30);
    }

    public function test_insufficient_wallet_marks_past_due_without_provider_call(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100]);
        $rental = $this->makeRental($user);
        Http::fake();

        $this->artisan('social-rentals:renew')->assertExitCode(0);

        $rental->refresh();
        $this->assertSame('past_due', $rental->status);
        $this->assertSame(1, (int) $rental->renewal_failed_count);
        $this->assertSame(100, (int) $user->fresh()->wallet_balance_minor);
        Http::assertNothingSent();
    }

    public function test_provider_extend_failure_refunds_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100000]);
        $rental = $this->makeRental($user);

        Http::fake([
            'api.smspool.net/rental/extend' => Http::response(['success' => 0, 'message' => 'This rental could not be found.'], 404),
        ]);

        $this->artisan('social-rentals:renew')->assertExitCode(0);

        $rental->refresh();
        $this->assertSame('past_due', $rental->status);
        $this->assertSame(1, (int) $rental->renewal_failed_count);
        $this->assertSame(100000, (int) $user->fresh()->wallet_balance_minor); // debited then refunded
    }

    public function test_renewal_is_abandoned_after_repeated_failures(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100000]);
        $rental = $this->makeRental($user, [
            'status' => 'past_due',
            'current_period_end' => now()->subDays(2),
            'renewal_failed_count' => 4,
        ]);
        Http::fake();

        $this->artisan('social-rentals:renew')->assertExitCode(0);

        $rental->refresh();
        $this->assertSame('expired', $rental->status);
        $this->assertFalse((bool) $rental->auto_renew);
        $this->assertSame(0, Payment::where('asset_type', 'social_number_rental_renewal')->count());
        $this->assertSame(100000, (int) $user->fresh()->wallet_balance_minor);
        Http::assertNothingSent();
    }

    public function test_canceled_and_not_yet_due_rentals_are_left_alone(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100000]);
        $canceled = $this->makeRental($user, ['provider_order_id' => 'C1', 'status' => 'canceled', 'auto_renew' => false, 'current_period_end' => now()->addDay()]);
        $future = $this->makeRental($user, ['provider_order_id' => 'F1', 'current_period_end' => now()->addDays(20)]);
        Http::fake();

        $this->artisan('social-rentals:renew');

        $this->assertSame('canceled', $canceled->fresh()->status);
        $this->assertSame('active', $future->fresh()->status);
        Http::assertNothingSent();
    }
}
