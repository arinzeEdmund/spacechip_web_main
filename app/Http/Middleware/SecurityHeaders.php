<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser protections on every response: no framing (clickjacking), no MIME sniffing, strict referrer,
 * locked-down device permissions, HSTS over HTTPS, and a Content-Security-Policy. The full script policy is sent
 * as Report-Only first (inline scripts and a few CDNs are still in use); framing is enforced now.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $h->remove('X-Powered-By');

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Enforced: nobody may frame us or hijack <base>/forms/plugins.
        $h->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");

        // The full policy: reported only until CSP_ENFORCE=true (see config/security.php and launch:check).
        if (! $h->has('Content-Security-Policy-Report-Only')) {
            $policy = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://unpkg.com https://js.paystack.co https://challenges.cloudflare.com",
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
                "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com data:",
                "img-src 'self' data: blob: https:",
                "connect-src 'self' https://api.paystack.co https://checkout.paystack.com",
                "frame-src https://checkout.paystack.com https://challenges.cloudflare.com",
                "frame-ancestors 'none'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self' https://checkout.paystack.com",
                'report-uri /csp-report',
            ]);
            $h->set(config('security.csp_enforce') ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only', $policy);
        }

        return $response;
    }
}
