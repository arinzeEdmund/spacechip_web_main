<?php

namespace App\Services\Admin;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

/** Writes login history and keeps users.last_login_* current. Never breaks a login if it fails. */
class LoginRecorder
{
    public function success(User $user, Request $request, string $channel): void
    {
        try {
            LoginActivity::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'channel' => $channel,
                'successful' => true,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'device' => $this->device($request),
            ]);
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        } catch (Throwable) {
        }
    }

    public function failure(?string $email, ?User $user, Request $request, string $channel): void
    {
        try {
            LoginActivity::create([
                'user_id' => $user?->id,
                'email' => $email ? mb_strtolower(trim($email)) : null,
                'channel' => $channel,
                'successful' => false,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'device' => $this->device($request),
            ]);
        } catch (Throwable) {
        }
    }

    private function device(Request $request): string
    {
        $ua = (string) $request->userAgent();
        $name = trim((string) $request->input('device_name', ''));
        if ($name !== '') {
            return mb_substr($name, 0, 64);
        }

        return match (true) {
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Macintosh') => 'Mac',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown',
        };
    }
}
