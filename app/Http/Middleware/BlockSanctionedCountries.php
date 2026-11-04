<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses sign-up and purchases from comprehensively sanctioned countries, using the country header your
 * CDN/host adds (Cloudflare CF-IPCountry, Vercel X-Vercel-IP-Country, CloudFront CloudFront-Viewer-Country).
 * With no such header (for example local development) nothing is blocked, so deploy behind one.
 * Browsing stays open; counsel should confirm the list in config/legal.php.
 */
class BlockSanctionedCountries
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('legal.country_block_enabled') && $request->isMethod('POST')) {
            $country = strtoupper((string) ($request->header('CF-IPCountry') ?: $request->header('X-Vercel-IP-Country') ?: $request->header('CloudFront-Viewer-Country') ?: ''));
            if ($country !== '' && in_array($country, (array) config('legal.blocked_countries', []), true)) {
                $message = 'Sorry, we cannot offer this service in your region because of international sanctions rules.';

                return $request->expectsJson() || $request->is('api/*')
                    ? response()->json(['message' => $message, 'code' => 'region_blocked'], 451)
                    : response($message, 451);
            }
        }

        return $next($request);
    }
}
