<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin sessions are only valid after the second step, expire after 30 idle minutes and after 12 hours
 * in total. Sessions are bound to the admin who passed the challenge.
 */
class EnsureAdminTwoFactor
{
    public const IDLE_MINUTES = 30;

    public const MAX_HOURS = 12;

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $user = $guard->user();
        $session = $request->session();
        $passed = (array) $session->get('admin_2fa', []);

        $valid = $user
            && (int) ($passed['user_id'] ?? 0) === (int) $user->id
            && now()->timestamp - (int) ($passed['at'] ?? 0) < self::MAX_HOURS * 3600
            && now()->timestamp - (int) $session->get('admin_last_activity', 0) < self::IDLE_MINUTES * 60;

        if (! $valid) {
            $guard->logout();
            $session->forget(['admin_2fa', 'admin_last_activity']);
            $session->regenerateToken();

            return redirect()->route('admin.login')->with('status', 'Your admin session ended. Please sign in again.');
        }

        $session->put('admin_last_activity', now()->timestamp);

        return $next($request);
    }
}
