<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Mail::fake();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'a-strong-passw0rd',
            'password_confirmation' => 'a-strong-passw0rd',
            'accept_terms' => '1',
        ]);

        // Registration defers login until the emailed OTP is verified.
        $this->assertGuest();
        $response->assertRedirect(route('verification.otp', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $user->email_otp); // stored hashed, never the code
        Mail::assertSent(OtpVerificationMail::class);
    }
}
