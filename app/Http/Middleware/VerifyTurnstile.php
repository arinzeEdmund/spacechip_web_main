<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bot challenge (Cloudflare Turnstile) on browser forms. Does nothing until TURNSTILE_SECRET_KEY is set, so local
 * development works untouched. Once configured it FAILS CLOSED: a missing or invalid token, or Cloudflare being
 * unreachable, rejects the request. Scripted floods never solve the challenge, so they stop here, cheaply, before
 * any password check or email is sent. The mobile API does not use this (it relies on rate limits).
 */
class VerifyTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('security.turnstile.secret_key');
        if ($secret === '' || ! $request->isMethod('POST')) {
            return $next($request);
        }

        $token = (string) $request->input('cf-turnstile-response', '');
        $ok = false;
        if ($token !== '' && strlen($token) < 4096) {
            try {
                $res = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret, 'response' => $token, 'remoteip' => $request->ip(),
                ]);
                $ok = $res->successful() && $res->json('success') === true;
            } catch (\Throwable $e) {
                Log::warning('Turnstile verification unreachable', ['error' => $e->getMessage()]);
            }
        }

        if (! $ok) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Please complete the human check and try again.', 'code' => 'captcha_failed'], 422)
                : back()->withInput($request->except(['password', 'password_confirmation']))->withErrors(['captcha' => 'Please complete the human check and try again.']);
        }

        return $next($request);
    }
}
