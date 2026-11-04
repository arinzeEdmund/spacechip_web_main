<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes `admin` the default guard for the whole admin area, so $request->user(), Auth::id() and auth()->user()
 * all mean "the signed-in admin" there and never the customer-side session.
 */
class UseAdminGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('admin');

        try {
            return $next($request);
        } finally {
            // Long-lived workers (and the test suite) reuse the app between requests; never leak the choice.
            Auth::shouldUse($previous);
        }
    }
}
