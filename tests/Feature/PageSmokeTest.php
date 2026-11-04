<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Renders every page that does not depend on a live provider API, so layout,
 * partial and routing regressions surface in CI rather than in production.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public static function publicPages(): array
    {
        return [
            'landing' => ['/'],
            'contact' => ['/contact-us'],
            'terms' => ['/terms'],
            'privacy' => ['/privacy'],
            'help' => ['/help-center'],
            'esim guide' => ['/esim-guide'],
            'delete account' => ['/delete-account'],
            'all assets' => ['/allassets'],
            'login' => ['/login'],
            'register' => ['/register'],
            'forgot password' => ['/forgot-password'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_render(string $uri): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertSee('css/spacechip-ui.css', false);
    }

    public static function memberPages(): array
    {
        return [
            'dashboard' => ['/dashboard'],
            'profile' => ['/profile'],
            'virtual numbers' => ['/dashboard/virtual-numbers'],
            'my virtual numbers' => ['/dashboard/virtual-numbers/my'],
            'crypto return' => ['/crypto/return'],
        ];
    }

    #[DataProvider('memberPages')]
    public function test_member_pages_render(string $uri): void
    {
        $this->actingAs(User::factory()->create())
            ->get($uri)
            ->assertOk();
    }

    public function test_legacy_virtual_number_urls_redirect_into_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/virtual-numbers/my')->assertRedirect(route('dashboard.virtual.my'));
        $this->actingAs($user)->get('/virtual-numbers')->assertRedirect(route('dashboard.virtual.index'));
    }

    public function test_admin_area_is_restricted_to_admins(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($member)->get('/admin')->assertRedirect(route('admin.login'));
        $this->actingAs($member)->get('/debug/airalo-catalog')->assertRedirect(route('admin.login'));
        $this->actingAs($admin)->get('/admin')->assertRedirect(route('admin.login')); // customer session != admin session
        $this->actingAs($admin, 'admin')->withSession(['admin_2fa' => ['user_id' => $admin->id, 'at' => now()->timestamp], 'admin_last_activity' => now()->timestamp])->get('/admin')->assertOk();
    }

    public function test_admin_allow_list_comes_from_config(): void
    {
        $user = User::factory()->create(['email' => 'Ops@Example.com']);

        $this->assertFalse($user->isAdmin());

        config(['admin.emails' => ['ops@example.com']]);

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $user->fill(['is_admin' => true]);

        $this->assertFalse((bool) $user->is_admin);
    }
}
