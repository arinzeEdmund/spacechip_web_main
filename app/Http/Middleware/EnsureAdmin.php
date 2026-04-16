<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $isAdminFlag = (bool) ($user->is_admin ?? false);
        $email = trim(mb_strtolower((string) ($user->email ?? '')));

        $raw = (string) (env('ADMIN_EMAILS', '') ?: '');
        $allowed = array_values(array_filter(array_map(fn ($v) => trim(mb_strtolower($v)), explode(',', $raw))));
        $isAllowedEmail = $email !== '' && in_array($email, $allowed, true);

        if (! $isAdminFlag && ! $isAllowedEmail) {
            abort(403);
        }

        return $next($request);
    }
}
