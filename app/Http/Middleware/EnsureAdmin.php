<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to admins signed in through the separate admin login (guard `admin`, users.is_admin);
 * everyone else gets 403. A customer-side session never counts.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('admin')->user()?->isAdmin(), 403);

        return $next($request);
    }
}
