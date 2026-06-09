<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\AiraloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DuplicateOrderPreventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prevents_duplicate_fulfillment_using_atomic_locks()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'provider_reference' => 'test_ref_123',
            'currency' => 'NGN',
            'amount_minor' => 1000,
            'status' => 'pending',
            'asset_type' => 'esim',
            'asset_id' => 'bundle_1',
            'bundle_id' => 'bundle_1',
            'package_type' => 'DATA-ONLY',
        ]);

        $reference = 'test_ref_123';
        $lockKey = 'fulfillment_lock_'.sha1($reference);

        // Manually acquire the lock to simulate another process working on it
        $lock = Cache::lock($lockKey, 60);
        $lock->get();

        $response = $this->actingAs($user)->postJson('/api/paystack/verify', [
            'reference' => $reference,
        ]);

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Fulfillment in progress, please wait.']);

        $lock->release();
    }

    public function test_it_returns_existing_fulfillment_if_already_processed()
    {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $fulfillmentPayload = ['ok' => true, 'iccid' => '123456789'];
        
        $payment = Payment::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'provider_reference' => 'test_ref_456',
            'currency' => 'NGN',
            'amount_minor' => 1000,
            'status' => 'fulfilled',
            'asset_type' => 'esim',
            'asset_id' => 'bundle_1',
            'bundle_id' => 'bundle_1',
            'package_type' => 'DATA-ONLY',
            'fulfillment_payload' => $fulfillmentPayload,
        ]);

        $response = $this->actingAs($user)->postJson('/api/paystack/verify', [
            'reference' => 'test_ref_456',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'fulfillment' => $fulfillmentPayload,
        ]);
        
        // Ensure no external API calls were made because it returned early
        Http::assertNothingSent();
    }
}
