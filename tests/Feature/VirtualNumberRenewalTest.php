<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VirtualNumberProduct;
use App\Models\VirtualNumberSubscription;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** `virtual-numbers:renew`: wallet debits, saved-card charges and the past-due path. */
class VirtualNumberRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(User $user, array $attributes): VirtualNumberSubscription
    {
        $product = VirtualNumberProduct::query()->forceCreate(['country_iso' => 'US', 'label' => 'US', 'monthly_amount_minor' => 300]);

        return VirtualNumberSubscription::query()->forceCreate($attributes + [
            'user_id' => $user->id, 'virtual_number_product_id' => $product->id, 'status' => 'active', 'phone_number' => '+1555'.random_int(1000000, 9999999),
            'country_iso' => 'US', 'provider' => 'twilio', 'currency' => 'USD', 'monthly_amount_minor' => 300, 'current_period_end' => now()->subMinute(), 'renewal_failed_count' => 2,
        ]);
    }

    public function test_renewals_charge_wallet_or_card_and_flag_failures(): void
    {
        config(['services.paystack.secret_key' => 'sk_test']);
        Http::fake(['api.paystack.co/transaction/charge_authorization' => fn ($r) => $r['authorization_code'] === 'AUTH_OK'
            ? Http::response(['status' => true, 'data' => ['status' => 'success']])
            : Http::response(['status' => true, 'data' => ['status' => 'failed']])]);

        $rich = User::factory()->create();
        $poor = User::factory()->create();
        app(WalletService::class)->credit($rich->id, 1000, 'deposit');

        $walletOk = $this->subscription($rich, ['billing_method' => 'wallet']);
        $walletBroke = $this->subscription($poor, ['billing_method' => 'wallet']);
        $cardOk = $this->subscription($rich, ['billing_method' => 'paystack', 'paystack_authorization_code' => 'AUTH_OK', 'paystack_customer_code' => 'C']);
        $cardDeclined = $this->subscription($rich, ['paystack_authorization_code' => 'AUTH_BAD', 'paystack_customer_code' => 'C']);
        $cardMissing = $this->subscription($rich, ['billing_method' => 'paystack']);

        $this->artisan('virtual-numbers:renew')->expectsOutput('Processed renewals: 5')->assertSuccessful();

        foreach ([$walletOk, $cardOk] as $sub) {
            $sub->refresh();
            $this->assertSame(['active', 0, null], [$sub->status, (int) $sub->renewal_failed_count, $sub->last_renewal_error]);
            $this->assertTrue($sub->current_period_end->isFuture());
            $this->assertStringStartsWith('vn_renew_'.$sub->id.'_', $sub->last_charge_reference);
        }
        $this->assertSame(700, app(WalletService::class)->balanceMinor($rich->id));

        foreach ([[$walletBroke, null], [$cardDeclined, 'Paystack charge not successful.'], [$cardMissing, 'Missing Paystack authorization.']] as [$sub, $error]) {
            $sub->refresh();
            $this->assertSame(['past_due', 3], [$sub->status, (int) $sub->renewal_failed_count]);
            if ($error) {
                $this->assertSame($error, $sub->last_renewal_error);
            }
        }

        $this->artisan('virtual-numbers:renew')->expectsOutput('No renewals due.')->assertSuccessful();
    }
}
