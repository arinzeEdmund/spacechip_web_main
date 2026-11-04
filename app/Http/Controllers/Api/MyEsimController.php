<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AiraloService;
use App\Services\GloEsimService;
use App\Services\MyEsimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user's purchased eSIMs, plus an on-demand sync that completes any whose
 * delivery details were still pending (Airalo, or legacy GloEsim orders).
 */
class MyEsimController extends Controller
{
    public function index(Request $request, MyEsimService $myEsims): JsonResponse
    {
        return response()->json($myEsims->listForUser(
            (int) Auth::id(),
            (string) $request->query('filter', 'valid'),
            max(1, (int) $request->query('page', 1)),
            (int) $request->query('per_page', 10),
        ));
    }

    public function sync(AiraloService $airalo, GloEsimService $gloEsim): JsonResponse
    {
        $payments = Payment::query()
            ->where('user_id', Auth::id())
            ->whereIn('provider', ['paystack', 'cryptomus'])
            ->whereNotNull('fulfillment_payload')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        $updated = 0;
        foreach ($payments as $payment) {
            $f = is_array($payment->fulfillment_payload) ? $payment->fulfillment_payload : [];
            $esimId = trim((string) ($f['esim_id'] ?? ''));
            if (($f['synced'] ?? false) === true || $esimId === '') {
                continue;
            }

            // Orders without a GloEsim payload were fulfilled by Airalo.
            $isAiralo = isset($f['airalo']) || ! isset($f['gloesim']);
            $details = ($isAiralo ? $airalo : $gloEsim)->getEsimDetails($esimId);
            if (($details['ok'] ?? false) !== true) {
                continue;
            }

            $f = array_merge($f, [
                'ok' => true,
                'synced' => true,
                'attempted_at' => now()->toIso8601String(),
                'esim_id' => (string) ($details['esim_id'] ?? $esimId),
                'iccid' => (string) ($details['iccid'] ?? ($f['iccid'] ?? '')),
                'number' => $details['number'] ?? null,
            ]);
            foreach (['esim_status', 'smdp_address', 'activation_code', 'lpa', 'puk_code', 'qr_code_url', 'direct_installation_link_ios', 'direct_installation_link_android'] as $key) {
                $f[$key] = (string) ($details[$key] ?? '');
            }
            $f[$isAiralo ? 'airalo' : 'gloesim'] = $isAiralo ? ($details['raw'] ?? $details) : ($details['gloesim_item'] ?? $details);

            $payment->update(['fulfillment_payload' => $f]);
            $updated++;
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }
}
