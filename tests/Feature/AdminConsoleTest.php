<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\LoginActivity;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Admin\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // provider balance probes must never hit the network in tests
        Cache::flush();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_non_admins_are_forbidden(): void
    {
        // Not signed in to the admin console -> sent to the separate admin login, never the customer login.
        $this->get('/admin')->assertRedirect(route('admin.login'));
        // A customer-side session never counts as an admin session, even for an admin account.
        $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('admin.login'));
        // Signed in to the admin guard but not an admin -> forbidden.
        $this->asAdmin(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_every_admin_page_renders(): void
    {
        $customer = User::factory()->create();
        foreach (['/admin', '/admin/users', "/admin/users/{$customer->id}", '/admin/payments', '/admin/esims', '/admin/wallet',
            '/admin/numbers/virtual', '/admin/numbers/social', '/admin/providers', '/admin/settings', '/admin/logins', '/admin/audit', '/admin/system'] as $uri) {
            $this->asAdmin($this->admin)->get($uri)->assertOk();
        }
    }

    public function test_suspending_a_user_blocks_api_access_and_is_audited(): void
    {
        $customer = User::factory()->create(['password' => 'secret-pass']);
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->asAdmin($this->admin)
            ->post("/admin/users/{$customer->id}/suspend", ['reason' => 'Chargeback fraud'])
            ->assertRedirect();

        $customer->refresh();
        $this->assertNotNull($customer->suspended_at);
        $this->assertSame(0, $customer->tokens()->count(), 'Suspension revokes app tokens');
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.suspended', 'subject_id' => $customer->id, 'admin_id' => $this->admin->id]);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $customer->email, 'password' => 'secret-pass'])
            ->assertStatus(403)->assertJson(['code' => 'account_suspended']);
    }

    public function test_admins_cannot_suspend_themselves(): void
    {
        $this->asAdmin($this->admin)->post("/admin/users/{$this->admin->id}/suspend", ['reason' => 'oops']);
        $this->assertNull($this->admin->fresh()->suspended_at);
    }

    public function test_wallet_adjustment_updates_balance_ledger_and_audit(): void
    {
        $customer = User::factory()->create();

        $this->asAdmin($this->admin)
            ->post("/admin/users/{$customer->id}/wallet", ['direction' => 'credit', 'amount' => '12.50', 'reason' => 'Goodwill'])
            ->assertRedirect();

        $this->assertSame(1250, (int) $customer->fresh()->wallet_balance_minor);
        $this->assertTrue(WalletTransaction::where('user_id', $customer->id)->where('action', 'admin_credit')->where('amount_minor', 1250)->exists());
        $this->assertTrue(AdminAuditLog::where('action', 'wallet.credit')->exists());

        $this->asAdmin($this->admin)
            ->post("/admin/users/{$customer->id}/wallet", ['direction' => 'debit', 'amount' => '50', 'reason' => 'Too much']);
        $this->assertSame(1250, (int) $customer->fresh()->wallet_balance_minor, 'Debits cannot overdraw the wallet');
    }

    public function test_feature_toggle_and_maintenance_mode_are_enforced(): void
    {
        $customer = User::factory()->create();

        $this->asAdmin($this->admin)->put('/admin/settings', ['feature_social_numbers' => '0', 'registrations_enabled' => '1', 'feature_esim_purchases' => '1', 'feature_virtual_numbers' => '1', 'feature_wallet_deposits' => '1'])->assertRedirect();
        $this->assertFalse(app(SettingsService::class)->enabled('feature_social_numbers'));

        $this->app['auth']->shouldUse('web');
        $this->actingAs($customer)->postJson('/api/social-numbers/buy', ['product' => 'tiktok', 'country' => 'US'])
            ->assertStatus(503)->assertJson(['code' => 'feature_disabled']);

        $this->asAdmin($this->admin)->put('/admin/settings', ['maintenance_mode' => '1', 'registrations_enabled' => '1', 'feature_esim_purchases' => '1', 'feature_virtual_numbers' => '1', 'feature_social_numbers' => '1', 'feature_wallet_deposits' => '1']);
        $this->app['auth']->shouldUse('web');
        $this->actingAs($customer)->getJson('/api/wallet/balance')->assertStatus(503)->assertJson(['code' => 'maintenance']);
        $this->asAdmin($this->admin)->get('/admin')->assertOk();
    }

    public function test_api_logins_are_recorded(): void
    {
        $customer = User::factory()->create(['password' => 'secret-pass']);

        $this->postJson('/api/auth/login', ['email' => $customer->email, 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => $customer->email, 'password' => 'secret-pass', 'device_name' => 'iPhone'])->assertOk();

        $this->assertSame(1, LoginActivity::where('user_id', $customer->id)->where('successful', false)->count());
        $this->assertSame(1, LoginActivity::where('user_id', $customer->id)->where('successful', true)->where('device', 'iPhone')->count());
        $this->assertNotNull($customer->fresh()->last_login_at);
    }
}
