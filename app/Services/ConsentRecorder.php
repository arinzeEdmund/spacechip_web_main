<?php

namespace App\Services;

use App\Models\ConsentRecord;
use Illuminate\Http\Request;
use Throwable;

/** Writes consent proof. Never throws: a logging hiccup must not break sign-up or checkout. */
class ConsentRecorder
{
    public function record(?int $userId, string $type, Request $request, string $source, bool $granted = true, array $meta = [], ?string $version = null): void
    {
        try {
            ConsentRecord::create([
                'user_id' => $userId,
                'type' => $type,
                'version' => $version ?? config("legal.versions.{$type}"),
                'granted' => $granted,
                'source' => $source,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'meta' => $meta ?: null,
            ]);
        } catch (Throwable) {
        }
    }

    /** Terms + Privacy accepted at account creation. */
    public function recordSignup(?int $userId, Request $request, string $source): void
    {
        $this->record($userId, 'terms', $request, $source);
        $this->record($userId, 'privacy', $request, $source);
    }
}
