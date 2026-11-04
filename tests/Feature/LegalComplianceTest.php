<?php

namespace Tests\Feature;

use App\Models\ConsentRecord;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class LegalComplianceTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    public function test_legal_pages_render_for_everyone(): void
    {
        foreach (['/terms', '/privacy', '/refund-policy', '/acceptable-use', '/cookie-policy', '/accessibility', '/privacy/request', '/delete-account'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/privacy')->assertSee('How long we keep data')->assertDontSee('```');
        $this->get('/refund-policy')->assertSee('Automatic refunds');
    }

    public function test_web_signup_requires_acceptance_and_records_versions(): void
    {
        Mail::fake();
        $base = ['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'a-strong-passw0rd', 'password_confirmation' => 'a-strong-passw0rd'];

        $this->post('/register', $base)->assertSessionHasErrors('accept_terms');
        $this->assertDatabaseCount('users', 0);

        $this->post('/register', $base + ['accept_terms' => '1'])->assertRedirect();
        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame(['privacy', 'terms'], ConsentRecord::where('user_id', $user->id)->orderBy('type')->pluck('type')->all());
        $this->assertSame(config('legal.versions.terms'), ConsentRecord::where('user_id', $user->id)->where('type', 'terms')->value('version'));
    }

    public function test_mobile_signup_records_consent(): void
    {
        Mail::fake();
        $id = $this->postJson('/api/auth/register', ['name' => 'Bo', 'email' => 'bo@example.com', 'password' => 'a-strong-passw0rd'])->assertCreated()->json('user_id');
        $this->assertSame(2, ConsentRecord::where('user_id', $id)->where('source', 'mobile_register')->count());
    }

    public function test_purchase_waiver_is_recorded_with_the_customers_answer(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100000]);

        $this->actingAs($user)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'x', 'waiver_accepted' => true]);
        $this->actingAs($user)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'x']);

        $rows = ConsentRecord::where('user_id', $user->id)->where('type', 'withdrawal_waiver')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->granted);
        $this->assertFalse($rows[1]->granted); // we record "no" too, so the log is honest
    }

    public function test_sanctioned_country_cannot_register_or_buy(): void
    {
        $user = User::factory()->create(['wallet_balance_minor' => 100000]);

        $this->withHeader('CF-IPCountry', 'IR')->postJson('/api/auth/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'a-strong-passw0rd'])
            ->assertStatus(451)->assertJsonPath('code', 'region_blocked');
        $this->actingAs($user)->withHeader('X-Vercel-IP-Country', 'KP')->postJson('/api/social-rentals/buy', ['product' => 'whatsapp', 'country' => 'US'])->assertStatus(451);
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);

        // Everyone else, and requests with no country header, are unaffected.
        $this->withHeader('CF-IPCountry', 'NG')->postJson('/api/auth/register', ['name' => 'Ok', 'email' => 'ok@example.com', 'password' => 'a-strong-passw0rd'])->assertCreated();
    }

    public function test_privacy_request_gets_a_deadline_and_hides_whether_the_email_exists(): void
    {
        $known = User::factory()->create(['email' => 'known@example.com']);

        $a = $this->post('/privacy/request', ['email' => 'known@example.com', 'type' => 'delete']);
        $b = $this->post('/privacy/request', ['email' => 'nobody@example.com', 'type' => 'access']);
        $this->assertSame(session('status'), $a->getSession()->get('status'));
        $a->assertSessionHas('status');
        $b->assertSessionHas('status');

        $r = PrivacyRequest::where('email', 'known@example.com')->firstOrFail();
        $this->assertSame($known->id, $r->user_id);
        $this->assertEqualsWithDelta(30, now()->diffInDays($r->due_at), 1);
        $this->post('/privacy/request', ['email' => 'bad', 'type' => 'nonsense'])->assertSessionHasErrors(['email', 'type']);
    }

    public function test_admin_can_close_a_privacy_request(): void
    {
        $admin = User::factory()->create(['is_admin' => false]);
        $admin->forceFill(['is_admin' => true])->save();
        $r = PrivacyRequest::create(['email' => 'a@b.co', 'type' => 'delete', 'status' => 'open', 'due_at' => now()->addDays(30)]);

        $this->asAdmin($admin)->get('/admin/privacy-requests')->assertOk()->assertSee('a@b.co');
        $this->asAdmin($admin)->post("/admin/privacy-requests/{$r->id}/complete", ['status' => 'completed', 'resolution' => 'Deleted account'])->assertRedirect();
        $this->assertNotNull($r->fresh()->completed_at);
    }

    public function test_social_login_only_links_by_email_for_google(): void
    {
        $existing = User::factory()->create(['email' => 'victim@example.com']); // verified by factory
        $this->assertNotNull($existing->email_verified_at);

        $fake = fn (string $id) => new class($id) extends \Laravel\Socialite\Two\User
        {
            public function __construct(string $id)
            {
                $this->id = $id;
                $this->name = 'Attacker';
                $this->email = 'victim@example.com';
                $this->avatar = null;
            }
        };

        foreach (['facebook', 'google'] as $provider) {
            $driver = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
            $driver->shouldReceive('user')->andReturn($fake('ext-'.$provider));
            \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
        }

        // Facebook can't claim someone else's email.
        $this->get('/auth/facebook/callback')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull($existing->fresh()->facebook_id);

        // Google vouches for the address, so it may link to the verified account.
        $this->get('/auth/google/callback')->assertRedirect();
        $this->assertAuthenticatedAs($existing->fresh());
    }
}
