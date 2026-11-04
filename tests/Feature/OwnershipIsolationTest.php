<?php

namespace Tests\Feature;

use App\Models\SocialNumberOrder;
use App\Models\SocialNumberRental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Two-user checks: user B must never read, change or cancel user A's records through any id-based endpoint. */
class OwnershipIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = User::factory()->create(['wallet_balance_minor' => 100000]);
        $this->bob = User::factory()->create(['wallet_balance_minor' => 100000]);
    }

    private function alicesOrder(): SocialNumberOrder
    {
        return SocialNumberOrder::create([
            'user_id' => $this->alice->id, 'provider' => 'smspool', 'provider_order_id' => 'A1', 'status' => 'PENDING',
            'product' => 'whatsapp', 'product_name' => 'WhatsApp', 'service_code' => 'whatsapp', 'country' => 'US', 'country_name' => 'United States',
            'phone' => '+12025550100', 'sms_code' => '123456', 'sell_amount_minor' => 500,
        ]);
    }

    private function alicesRental(): SocialNumberRental
    {
        return SocialNumberRental::create([
            'user_id' => $this->alice->id, 'provider' => 'smspool_rent', 'provider_order_id' => 'R1', 'status' => 'active',
            'product' => 'whatsapp', 'product_name' => 'WhatsApp', 'service_code' => 'whatsapp', 'country' => 'US',
            'phone' => '2025550100', 'auto_renew' => true, 'monthly_amount_minor' => 500,
        ]);
    }

    public function test_other_users_cannot_touch_a_social_number_order(): void
    {
        $o = $this->alicesOrder();
        $this->actingAs($this->bob);

        $this->getJson("/api/social-numbers/check/{$o->id}")->assertNotFound();
        foreach (['finish', 'cancel', 'ban', 'try-another'] as $action) {
            $this->postJson("/api/social-numbers/orders/{$o->id}/{$action}")->assertNotFound();
        }
        $this->assertSame('PENDING', $o->fresh()->status);
        $this->assertSame(100000, (int) $this->bob->fresh()->wallet_balance_minor); // no refund leaked to Bob
    }

    public function test_order_list_only_contains_own_orders(): void
    {
        $this->alicesOrder();
        $this->actingAs($this->bob)->getJson('/api/social-numbers/orders')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_other_users_cannot_touch_a_rental(): void
    {
        $r = $this->alicesRental();
        $this->actingAs($this->bob);

        $this->getJson("/api/social-rentals/{$r->id}/sms")->assertForbidden();
        $this->postJson("/api/social-rentals/{$r->id}/activate")->assertForbidden();
        $this->postJson("/api/social-rentals/{$r->id}/cancel")->assertForbidden();
        $this->assertTrue((bool) $r->fresh()->auto_renew);
        $this->getJson('/api/social-rentals')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_virtual_number_subscription_endpoints_are_owner_only(): void
    {
        $sub = \App\Models\VirtualNumberSubscription::query()->create([
            'user_id' => $this->alice->id,
            'virtual_number_product_id' => \App\Models\VirtualNumberProduct::query()->create(['country_iso' => 'US', 'label' => 'US number', 'monthly_amount_minor' => 500, 'currency' => 'USD', 'active' => true])->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->bob);

        $this->getJson("/api/virtual-numbers/{$sub->id}/messages")->assertForbidden();
        $this->getJson("/api/virtual-numbers/{$sub->id}/settings")->assertForbidden();
        $this->postJson("/api/virtual-numbers/{$sub->id}/cancel")->assertForbidden();
        $this->postJson("/api/virtual-numbers/{$sub->id}/messages/send", ['to' => '+12025550111', 'body' => 'hi'])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $o = $this->alicesOrder();
        $this->getJson("/api/social-numbers/check/{$o->id}")->assertUnauthorized();
        $this->getJson('/api/wallet/balance')->assertUnauthorized();
        $this->getJson('/api/social-rentals')->assertUnauthorized();
    }
}
