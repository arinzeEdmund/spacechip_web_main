<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_already_verified_account_cannot_obtain_a_token_without_a_valid_otp(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => '000000',
        ]);

        $response->assertStatus(409);
        $response->assertJsonMissingPath('token');
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_unverified_account_with_correct_otp_receives_a_token_and_becomes_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill([
            'email_otp'            => '123456',
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => '123456',
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('token_type', 'Bearer');
        $this->assertNotEmpty($response->json('token'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNull($user->fresh()->email_otp);
    }

    public function test_unverified_account_with_wrong_otp_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill([
            'email_otp'            => '123456',
            'email_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => '654321',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_unverified_account_with_expired_otp_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $user->forceFill([
            'email_otp'            => '123456',
            'email_otp_expires_at' => now()->subMinute(),
        ])->save();

        $response = $this->postJson('/api/auth/verify-otp', [
            'user_id' => $user->id,
            'otp'     => '123456',
        ]);

        $response->assertStatus(422);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
