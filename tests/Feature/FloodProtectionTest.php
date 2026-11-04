<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Simulates floods against sign-up, sign-in and password reset and proves they are cut off cheaply. */
class FloodProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
    }

    public function test_a_signup_flood_from_one_ip_creates_only_a_handful_of_accounts_and_emails(): void
    {
        $statuses = [];
        for ($i = 0; $i < 60; $i++) {
            $statuses[] = $this->post('/register', ['name' => 'Bot', 'email' => "bot{$i}@example.com", 'password' => 'a-strong-passw0rd', 'password_confirmation' => 'a-strong-passw0rd', 'accept_terms' => '1'])->getStatusCode();
        }

        $this->assertLessThanOrEqual(config('security.limits.signup_ip_per_min'), User::count()); // 5, not 60
        $this->assertSame(429, end($statuses));
        $this->assertGreaterThanOrEqual(55, count(array_filter($statuses, fn ($s) => $s === 429)));
    }

    public function test_signup_has_a_site_wide_circuit_breaker_against_many_ips(): void
    {
        config(['security.limits.signup_global_per_min' => 10]);
        Cache::flush();
        $accepted = 0;
        for ($i = 0; $i < 40; $i++) { // 40 different "attackers", one request each
            $res = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.'.($i + 1)])->post('/register', ['name' => 'B', 'email' => "b{$i}@example.com", 'password' => 'a-strong-passw0rd', 'password_confirmation' => 'a-strong-passw0rd', 'accept_terms' => '1']);
            $accepted += $res->getStatusCode() === 429 ? 0 : 1;
        }
        $this->assertLessThanOrEqual(10, $accepted); // costs (email, DB) are capped no matter how many IPs
    }

    public function test_signup_rejects_the_same_email_hammered_repeatedly(): void
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])->post('/register', ['name' => 'V', 'email' => 'victim@example.com', 'password' => 'a-strong-passw0rd', 'password_confirmation' => 'a-strong-passw0rd', 'accept_terms' => '1'])->getStatusCode();
        }
        $this->assertGreaterThanOrEqual(5, count(array_filter($codes, fn ($c) => $c === 429)));
    }

    public function test_login_flood_rotating_emails_is_stopped_per_ip_before_any_password_check(): void
    {
        User::factory()->create(['email' => 'real@example.com', 'password' => 'correct-horse-battery-1']);
        $statuses = [];
        for ($i = 0; $i < 50; $i++) { // a different email each time defeats the old "5 tries per email" lock
            $statuses[] = $this->post('/login', ['email' => "guess{$i}@example.com", 'password' => 'whatever12345'])->getStatusCode();
        }

        $limit = config('security.limits.login_ip_per_min');
        $this->assertSame($limit, count(array_filter($statuses, fn ($s) => $s !== 429))); // only 15 ever reach the controller
        $this->assertSame(429, end($statuses));
    }

    public function test_429_responses_tell_clients_when_to_retry(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $res = $this->post('/login', ['email' => "x{$i}@example.com", 'password' => 'whatever12345']);
        }
        $res->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_password_reset_flood_cannot_email_bomb(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        foreach (range(1, 40) as $i) {
            User::factory()->create(['email' => "u{$i}@example.com"]);
            $this->post('/forgot-password', ['email' => "u{$i}@example.com"]);
        }
        \Illuminate\Support\Facades\Notification::assertSentTimes(\Illuminate\Auth\Notifications\ResetPassword::class, config('security.limits.reset_ip_per_min')); // 5 emails, not 40
        $this->post('/forgot-password', ['email' => 'again@example.com'])->assertStatus(429);
    }

    public function test_mobile_api_login_and_register_share_the_same_protection(): void
    {
        $last = null;
        for ($i = 0; $i < 25; $i++) {
            $last = $this->postJson('/api/auth/login', ['email' => "m{$i}@example.com", 'password' => 'whatever12345']);
        }
        $last->assertStatus(429);

        Cache::flush();
        for ($i = 0; $i < 12; $i++) {
            $last = $this->postJson('/api/auth/register', ['name' => 'M', 'email' => "mm{$i}@example.com", 'password' => 'a-strong-passw0rd']);
        }
        $last->assertStatus(429);
    }

    public function test_ipv6_attackers_cannot_dodge_limits_by_rotating_addresses_in_their_network(): void
    {
        $a = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '2001:db8:abcd:12:1111:2222:3333:4444']);
        $b = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '2001:db8:abcd:12:ffff:eeee:dddd:cccc']);
        $c = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '2001:db8:abcd:13::1']);
        $this->assertSame(AppServiceProvider::ipKey($a), AppServiceProvider::ipKey($b));
        $this->assertNotSame(AppServiceProvider::ipKey($a), AppServiceProvider::ipKey($c));
        $this->assertSame('203.0.113.9', AppServiceProvider::ipKey(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9'])));
    }

    private function login(?string $token): \Illuminate\Testing\TestResponse
    {
        config(['security.turnstile.secret_key' => 'secret']);

        return $this->post('/login', array_filter(['email' => 'a@example.com', 'password' => 'x', 'cf-turnstile-response' => $token]));
    }

    public function test_turnstile_refuses_a_request_with_no_token(): void
    {
        Http::fake();
        $this->login(null)->assertSessionHasErrors('captcha');
        Http::assertNothingSent(); // rejected before even calling Cloudflare
    }

    public function test_turnstile_refuses_a_token_cloudflare_rejects(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $this->login('bad')->assertSessionHasErrors('captcha');
    }

    public function test_turnstile_fails_closed_when_cloudflare_is_unreachable(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => fn () => throw new \RuntimeException('down')]);
        $this->login('tok')->assertSessionHasErrors('captcha');
    }

    public function test_turnstile_lets_a_verified_human_through_to_normal_login_handling(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        $this->login('good')->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors('captcha');
    }

    public function test_turnstile_is_off_when_not_configured(): void
    {
        config(['security.turnstile.secret_key' => '']);
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'x'])->assertSessionHasErrors('email')->assertSessionDoesntHaveErrors('captcha');
    }

    public function test_rate_limit_counters_do_not_use_the_database(): void
    {
        $this->assertNotSame('database', config('cache.limiter'));
        $this->assertTrue(RateLimiter::tooManyAttempts('nothing', 1) === false);
    }

    public function test_a_page_flood_is_dropped_before_any_session_is_created(): void
    {
        config(['security.limits.early_page_per_min' => 10]);
        Cache::flush();
        for ($i = 0; $i < 10; $i++) {
            $this->get('/login')->assertOk();
        }
        $res = $this->get('/login');
        $res->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame([], $res->headers->getCookies()); // no session / CSRF cookie was even issued
    }

    public function test_the_early_guard_does_not_touch_normal_pages_or_other_clients(): void
    {
        config(['security.limits.early_page_per_min' => 3]);
        Cache::flush();
        for ($i = 0; $i < 5; $i++) {
            $this->get('/terms')->assertOk(); // not a guarded path
        }
        for ($i = 0; $i < 3; $i++) {
            $this->get('/login')->assertOk();
        }
        $this->get('/login')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])->get('/login')->assertOk(); // a different visitor is unaffected
    }
}
