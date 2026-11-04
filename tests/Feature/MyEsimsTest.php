<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\AiraloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "My eSIMs": listing (valid/expired, dedupe by ICCID) and syncing pending deliveries. */
class MyEsimsTest extends TestCase
{
    use RefreshDatabase;

    private function payment(User $user, array $fulfillment, string $validity, string $createdAt, string $provider = 'paystack'): Payment
    {
        $p = Payment::create([
            'user_id' => $user->id, 'provider' => $provider, 'provider_reference' => uniqid(), 'status' => 'fulfilled',
            'asset_type' => 'country', 'asset_id' => 'us', 'bundle_id' => 'b1', 'package_type' => 'DATA-ONLY', 'currency' => 'USD', 'amount_minor' => 450,
            'provider_payload' => ['asset' => ['name' => 'USA'], 'bundle' => ['name' => 'US 1GB', 'validity' => $validity]],
            'fulfillment_payload' => $fulfillment,
        ]);
        $p->forceFill(['created_at' => $createdAt])->save();

        return $p;
    }

    public function test_lists_valid_and_expired_esims_and_syncs_pending_ones(): void
    {
        $user = User::factory()->create();
        $this->payment($user, ['ok' => true, 'iccid' => '111', 'lpa' => 'LPA:1$x$A', 'airalo' => []], '7 days', now()->subDay());
        $this->payment($user, ['ok' => true, 'iccid' => '111', 'lpa' => 'LPA:1$x$OLD', 'airalo' => []], '7 days', now()->subDays(2)); // older top-up, same ICCID
        $this->payment($user, ['ok' => true, 'iccid' => '222', 'activation_code' => 'C', 'smdp_address' => 's', 'airalo' => []], '30 days', now()->subDays(40));
        $pending = $this->payment($user, ['ok' => false, 'pending' => true, 'esim_id' => 'e9', 'airalo' => []], '7 days', now());

        $valid = $this->actingAs($user)->getJson('/api/my-esims?filter=valid')->assertOk();
        $this->assertSame(1, $valid->json('total'));
        $this->assertSame(['111', 'LPA:1$x$A', 'valid', 'US 1GB', true], [$valid->json('items.0.esim.iccid'), $valid->json('items.0.esim.qr_payload'), $valid->json('items.0.status'), $valid->json('items.0.bundle.name'), $valid->json('items.0.esim.can_renew')]);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $valid->json('items.0.esim.qr_code_data_url'));

        $expired = $this->actingAs($user)->getJson('/api/my-esims?filter=expired')->assertOk();
        $this->assertSame(['222', 'LPA:1$s$C', 'expired'], [$expired->json('items.0.esim.iccid'), $expired->json('items.0.esim.qr_payload'), $expired->json('items.0.status')]);

        $this->app->instance(AiraloService::class, new class extends AiraloService
        {
            public function __construct() {}

            public function getEsimDetails(string $iccid): array
            {
                return ['ok' => true, 'esim_id' => 'e9', 'iccid' => '333', 'lpa' => 'LPA:1$y$Z', 'smdp_address' => 'y', 'esim_status' => 'ACTIVE', 'raw' => ['id' => 'e9']];
            }
        });

        $this->actingAs($user)->postJson('/api/my-esims/sync')->assertOk()->assertExactJson(['ok' => true, 'updated' => 1]);

        $f = $pending->fresh()->fulfillment_payload;
        $this->assertSame([true, true, '333', 'LPA:1$y$Z', 'ACTIVE', '', ['id' => 'e9']], [$f['ok'], $f['synced'], $f['iccid'], $f['lpa'], $f['esim_status'], $f['puk_code'], $f['airalo']]);
        $this->assertSame(2, $this->actingAs($user)->getJson('/api/my-esims?filter=valid')->json('total'));
    }
}
