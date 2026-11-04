<?php

namespace Tests\Feature;

use App\Mail\AdminLoginCodeMail;
use App\Models\User;
use App\Services\Admin\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSignInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
        $this->admin = User::factory()->create(['email' => 'boss@example.com', 'password' => 'correct-horse-battery']);
        $this->admin->forceFill(['is_admin' => true])->save();
    }

    private function signInPassword(string $password = 'correct-horse-battery', ?string $email = null)
    {
        return $this->post('/admin/login', ['email' => $email ?? 'boss@example.com', 'password' => $password]);
    }

    private function emailedCode(): string
    {
        $code = null;
        Mail::assertSent(AdminLoginCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return (string) $code;
    }

    public function test_login_page_is_separate_and_public(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Admin console');
    }

    public function test_password_alone_is_not_enough(): void
    {
        $this->signInPassword()->assertRedirect(route('admin.two-factor'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_emailed_code_completes_sign_in_and_is_single_use(): void
    {
        $this->signInPassword();
        $code = $this->emailedCode();

        $this->post('/admin/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/admin/two-factor', ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk();
        $this->assertAuthenticatedAs($this->admin, 'admin');
        $this->assertGuest('web'); // customer side stays signed out

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->signInPassword();
        $this->post('/admin/two-factor', ['code' => $code])->assertSessionHasErrors('code'); // old code is dead
    }

    public function test_authenticator_and_email_codes_are_interchangeable(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $this->admin->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        // Authenticator code works.
        $this->signInPassword();
        Mail::assertNothingSent(); // enrolled admins are not emailed unless they ask
        $this->post('/admin/two-factor', ['code' => $totp->code($secret)])->assertRedirect(route('admin.dashboard'));
        $this->post('/admin/logout');

        // Same TOTP code can't be replayed.
        $this->signInPassword();
        $this->post('/admin/two-factor', ['code' => $totp->code($secret)])->assertSessionHasErrors('code');

        // Emailed code works for the same admin.
        $this->post('/admin/two-factor/email')->assertSessionHas('status');
        $this->post('/admin/two-factor', ['code' => $this->emailedCode()])->assertRedirect(route('admin.dashboard'));
    }

    public function test_totp_matches_rfc6238_test_vector(): void
    {
        // RFC 6238 appendix B, SHA-1, secret "12345678901234567890", T=59 -> 94287082 (6 digits: 287082).
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', app(Totp::class)->code($secret, intdiv(59, 30)));
    }

    public function test_wrong_password_non_admin_and_unknown_accounts_get_the_same_error(): void
    {
        $customer = User::factory()->create(['email' => 'cust@example.com', 'password' => 'customer-password-1']);

        foreach ([['boss@example.com', 'nope'], ['cust@example.com', 'customer-password-1'], ['ghost@example.com', 'whatever']] as [$email, $pw]) {
            $this->signInPassword($pw, $email)->assertSessionHasErrors(['email' => 'These credentials do not match an admin account.']);
        }
        $this->assertGuest('admin');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->signInPassword('wrong');
        }
        $this->signInPassword('correct-horse-battery')->assertSessionHasErrors('email'); // locked even with the right password
        $this->assertGuest('admin');
    }

    public function test_admin_session_expires_when_idle(): void
    {
        $this->withSession([
            'admin_2fa' => ['user_id' => $this->admin->id, 'at' => now()->timestamp],
            'admin_last_activity' => now()->subMinutes(31)->timestamp,
        ])->actingAs($this->admin, 'admin')->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_customer_login_does_not_open_the_admin_console(): void
    {
        $this->post('/login', ['email' => 'boss@example.com', 'password' => 'correct-horse-battery']);
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_authenticator_can_be_enrolled_from_the_security_page(): void
    {
        $this->withSession(['admin_2fa' => ['user_id' => $this->admin->id, 'at' => now()->timestamp], 'admin_last_activity' => now()->timestamp])
            ->actingAs($this->admin, 'admin');

        $this->post('/admin/security/authenticator/start')->assertRedirect(route('admin.security'));
        $secret = session('admin_totp_pending');
        $this->get('/admin/security')->assertOk()->assertSee('Turn on');

        $this->post('/admin/security/authenticator/confirm', ['code' => '111111'])->assertSessionHasErrors('code');
        $this->post('/admin/security/authenticator/confirm', ['code' => app(Totp::class)->code($secret)])->assertSessionHasNoErrors();
        $this->assertNotNull($this->admin->fresh()->two_factor_confirmed_at);
        $this->assertSame($secret, $this->admin->fresh()->two_factor_secret);
    }
}
