<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/** Blocks suspended accounts on every authenticated web and API request. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? $request->user('sanctum');
        if (! $user || ! method_exists($user, 'isSuspended') || ! $user->isSuspended()) {
            return $next($request);
        }

        $message = 'This account has been suspended. Contact support@spacechipltd.com for help.';

        if ($request->expectsJson() || $request->is('api/*')) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => $message, 'code' => 'account_suspended'], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
