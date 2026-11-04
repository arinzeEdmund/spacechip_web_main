<?php

namespace App\Http\Middleware;

use App\Services\Admin\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies admin-controlled switches: maintenance mode, sign-up toggle and per-product kill switches.
 * Admins, authentication and provider webhooks are never blocked.
 */
class EnforcePlatformSettings
{
    /** POST endpoints that start a purchase, grouped by the setting that controls them. */
    private const FEATURE_PATHS = [
        'feature_esim_purchases' => ['api/paystack/initialize', 'api/wallet/pay/esim', 'api/cryptomus/invoice'],
        'feature_virtual_numbers' => ['api/virtual-numbers/paystack/initialize', 'api/wallet/pay/virtual-number'],
        'feature_social_numbers' => ['api/social-numbers/buy', 'api/social-numbers/orders/*/try-another', 'api/social-rentals/buy'],
        'feature_wallet_deposits' => ['api/wallet/deposit/paystack/initialize'],
        'registrations_enabled' => ['register', 'api/auth/register'],
    ];

    /** Always reachable, even during maintenance. */
    private const ALWAYS_OPEN = [
        'admin', 'admin/*', 'login', 'logout', 'up',
        'api/auth/login', 'api/auth/logout', 'api/mobile/health',
        'api/airalo/webhook', 'api/twilio/*', 'api/cryptomus/webhook', 'api/paystack/webhook', 'paystack/webhook',
    ];

    public function __construct(private SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? $request->user('sanctum');
        if (($user->is_admin ?? false) || $request->is(...self::ALWAYS_OPEN)) {
            return $next($request);
        }

        if ($this->settings->enabled('maintenance_mode')) {
            return $this->deny($request, (string) $this->settings->get('maintenance_message'), 'maintenance', 503);
        }

        if ($request->isMethod('POST')) {
            foreach (self::FEATURE_PATHS as $setting => $paths) {
                if ($request->is(...$paths) && ! $this->settings->enabled($setting)) {
                    $message = $setting === 'registrations_enabled'
                        ? 'New sign-ups are paused right now. Please try again later.'
                        : 'This service is temporarily unavailable. Please try again later.';

                    return $this->deny($request, $message, 'feature_disabled', 503);
                }
            }
        }

        return $next($request);
    }

    private function deny(Request $request, string $message, string $code, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message, 'code' => $code], $status);
        }
        if ($request->isMethod('GET')) {
            return response()->view('errors.maintenance', ['message' => $message], $status);
        }

        return back()->withErrors(['email' => $message])->withInput();
    }
}
