<?php

namespace App\Http\Middleware;

use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * First line of defence for the pages bots hammer. It runs as GLOBAL middleware, before the session, cookies,
 * CSRF check, database or the route's own limits, so an abusive client costs one cache lookup and a tiny 429, and
 * never creates a session row. Limits are generous (well above any real person); the precise per-purpose
 * limits (sign-up 5/min, sign-in 15/min, ...) still apply behind it.
 */
class EarlyFloodGuard
{
    /** Paths and how many requests per minute one client (IPv6 by /64) may send to each group. */
    private const POST_PATHS = ['login', 'register', 'forgot-password', 'reset-password', 'admin/login', 'api/auth/login', 'api/auth/register', 'api/auth/forgot-password', 'api/auth/verify-otp', 'api/auth/resend-otp'];

    private const PAGE_PATHS = ['login', 'register', 'forgot-password', 'admin/login'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is(...self::POST_PATHS)) {
            $key = 'early-post:'.AppServiceProvider::ipKey($request);
            $limit = (int) config('security.limits.early_post_per_min', 40);
        } elseif ($request->isMethod('GET') && $request->is(...self::PAGE_PATHS)) {
            $key = 'early-page:'.AppServiceProvider::ipKey($request);
            $limit = (int) config('security.limits.early_page_per_min', 120);
        } else {
            return $next($request);
        }

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response('Too many requests. Please slow down.', 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
