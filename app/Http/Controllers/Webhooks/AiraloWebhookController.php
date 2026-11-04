<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Mail\NoticeMail;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Airalo eSIM lifecycle notifications. Currently acts on `sim.usage.low` by emailing the owner
 * a top-up prompt; every event is logged and acknowledged.
 */
class AiraloWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Airalo does not sign webhooks, so a shared secret (header or ?token=) stops strangers from triggering
        // customer emails. Set AIRALO_WEBHOOK_SECRET and register the callback URL with the same ?token=.
        $secret = (string) config('services.airalo.webhook_secret');
        if ($secret !== '' && ! hash_equals($secret, (string) ($request->header('X-Webhook-Token') ?: $request->query('token', '')))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $payload = $request->all();
        $event = (string) ($payload['event'] ?? '');

        // Log the event type only: payloads carry ICCIDs and usage, which are customer data.
        Log::info('Airalo Webhook Received', ['event' => $event]);

        if ($event === 'sim.usage.low') {
            $iccid = (string) data_get($payload, 'data.iccid', '');
            // At most one low-data email per eSIM per day, whatever is replayed at us.
            if ($iccid === '' || ! \Illuminate\Support\Facades\Cache::add('airalo-low-data:'.sha1($iccid), 1, now()->addDay())) {
                return response()->json(['ok' => true]);
            }
            $this->notifyLowData(
                $iccid,
                (int) data_get($payload, 'data.usage', 0),
                (int) data_get($payload, 'data.limit', 0),
            );
        }

        return response()->json(['ok' => true]);
    }

    /** Usage and limit arrive in bytes. */
    private function notifyLowData(string $iccid, int $usageBytes, int $limitBytes): void
    {
        $user = Payment::where('iccid_index', Payment::iccidIndex($iccid))
            ->where('status', 'fulfilled')
            ->orderByDesc('created_at')
            ->first()
            ?->user;

        if (! $user) {
            return;
        }

        $mb = fn (int $bytes) => round($bytes / 1024 / 1024, 2).' MB';

        try {
            Mail::to($user->email)->send(new NoticeMail(
                subjectLine: 'Low Data Alert for your eSIM',
                kicker: 'Low Data Alert',
                heading: 'You are running out of data',
                quote: "Your eSIM with ICCID {$iccid} has used {$mb($usageBytes)} of its {$mb($limitBytes)} data limit.",
                action: ['label' => 'Top Up Now', 'url' => url('/dashboard')],
                footnote: 'To avoid interruption, we recommend topping up your data balance from your dashboard.',
            ));
            Log::info('Low data notification sent', ['user_id' => $user->id, 'iccid' => $iccid]);
        } catch (Throwable $e) {
            Log::error('Failed to send low data notification', ['error' => $e->getMessage()]);
        }
    }
}
