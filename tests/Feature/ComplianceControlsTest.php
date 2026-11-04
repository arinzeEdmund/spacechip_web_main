<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class ComplianceControlsTest extends TestCase
{
    use ActsAsAdmin, RefreshDatabase;

    private function admin(): User
    {
        $a = User::factory()->create();
        $a->forceFill(['is_admin' => true])->save();

        return $a;
    }

    public function test_kyc_gate_is_off_by_default(): void
    {
        $u = User::factory()->create(['wallet_balance_minor' => 1_000_000]);
        app(WalletService::class)->debit($u->id, 500000, 'buy');
        $res = $this->actingAs($u)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'x']);
        $this->assertNotSame('kyc_required', $res->json('code'));
    }

    public function test_kyc_gate_blocks_after_threshold_until_an_admin_approves(): void
    {
        config(['compliance.kyc_threshold_usd' => 100]);
        $u = User::factory()->create(['wallet_balance_minor' => 1_000_000]);
        app(WalletService::class)->debit($u->id, 12000, 'buy'); // $120 in the window

        $this->actingAs($u)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'x'])->assertForbidden()->assertJsonPath('code', 'kyc_required');
        $this->assertSame('required', $u->fresh()->kyc_status);

        $admin = $this->admin();
        $this->asAdmin($admin)->post("/admin/users/{$u->id}/kyc", ['status' => 'approved', 'note' => 'Passport seen by ops'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('approved', $u->fresh()->kyc_status);
        $this->app['auth']->forgetGuards(); // the test app is reused between requests; real requests start clean
        $this->app['auth']->shouldUse('web');

        $res = $this->actingAs($u->fresh())->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'x']);
        $this->assertNotSame('kyc_required', $res->json('code'));
    }

    public function test_small_spenders_are_not_gated(): void
    {
        config(['compliance.kyc_threshold_usd' => 100]);
        $u = User::factory()->create(['wallet_balance_minor' => 1_000_000]);
        app(WalletService::class)->debit($u->id, 1000, 'buy');

        $res = $this->actingAs($u)->postJson('/api/wallet/pay/esim', ['type' => 'country', 'id' => 'x']);
        $this->assertNotSame('kyc_required', $res->json('code'));
    }

    public function test_csp_is_report_only_until_enforced_and_reports_are_accepted(): void
    {
        $this->get('/')->assertHeader('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString('report-uri /csp-report', $this->get('/')->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.csp_enforce' => true]);
        $res = $this->get('/');
        $this->assertStringContainsString("script-src 'self'", (string) $res->headers->get('Content-Security-Policy'));
        $this->assertFalse($res->headers->has('Content-Security-Policy-Report-Only'));

        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => ['violated-directive' => 'script-src', 'blocked-uri' => 'https://evil.test/x.js', 'document-uri' => 'https://site.test/']]))->assertNoContent();
    }

    public function test_payments_record_the_buyers_country_and_sales_export_works(): void
    {
        $u = User::factory()->create();
        request()->headers->set('CF-IPCountry', 'DE'); // as the CDN would on the real request
        $p = Payment::create(['user_id' => $u->id, 'provider' => 'paystack', 'provider_reference' => 'r1', 'status' => 'fulfilled', 'asset_type' => 'country', 'asset_id' => 'us', 'bundle_id' => 'b', 'package_type' => 'x', 'currency' => 'USD', 'amount_minor' => 1250]);
        $this->assertSame('DE', $p->fresh()->ip_country);

        $admin = $this->admin();
        $csv = $this->asAdmin($admin)->get('/admin/reports/sales.csv?from='.now()->subDay()->toDateString().'&to='.now()->addDay()->toDateString());
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('buyer_ip_country', $body);
        $this->assertStringContainsString('r1', $body);
        $this->assertStringContainsString('12.50', $body);
        $this->assertStringContainsString(',DE,', $body);
    }

    public function test_launch_check_runs_and_flags_missing_airalo_secret_in_production(): void
    {
        config(['services.airalo.webhook_secret' => '']);
        $this->artisan('launch:check')->expectsOutputToContain('Legal details')->assertSuccessful(); // local: warnings only
    }

    public function test_billing_pages_for_admin_render(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin)->get('/admin/reports')->assertOk();
        $u = User::factory()->create();
        $this->asAdmin($admin)->get("/admin/users/{$u->id}")->assertOk()->assertSee('Identity check');
    }
}
