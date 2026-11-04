<?php

namespace App\Http\Middleware;

use App\Models\Payment;
use App\Models\WalletTransaction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identity-check gate. When KYC_THRESHOLD_USD is set and a customer's purchase volume over the window reaches it,
 * further purchases are refused until an admin approves them (after checking ID outside the app, so we never
 * hold ID documents here). Off by default. Volume = wallet purchases + direct USD payments.
 */
class RequireKycAboveThreshold
{
    public function handle(Request $request, Closure $next): Response
    {
        $threshold = config('compliance.kyc_threshold_usd');
        $user = $request->user() ?? $request->user('sanctum');
        if (! $threshold || $threshold <= 0 || ! $user || $user->kyc_status === 'approved' || ! $request->isMethod('POST')) {
            return $next($request);
        }

        if ($user->kyc_status === 'required' || $this->volumeUsd($user->id) >= $threshold) {
            if ($user->kyc_status !== 'required') {
                $user->forceFill(['kyc_status' => 'required'])->saveQuietly();
            }
            $message = 'For your security and to meet legal requirements we need to verify your identity before you can buy more. Please contact '.config('legal.support_email').'.';

            return $request->expectsJson() || $request->is('api/*')
                ? response()->json(['message' => $message, 'code' => 'kyc_required'], 403)
                : response($message, 403);
        }

        return $next($request);
    }

    private function volumeUsd(int $userId): float
    {
        $since = now()->subDays((int) config('compliance.kyc_window_days', 30));

        $wallet = (int) WalletTransaction::query()->where('user_id', $userId)->where('direction', 'debit')->where('created_at', '>=', $since)->sum('amount_minor');
        $direct = (int) Payment::query()->where('user_id', $userId)->where('currency', 'USD')->where('provider', '!=', 'wallet')
            ->where('asset_type', '!=', 'wallet_deposit')->whereIn('status', ['paid', 'fulfilled'])->where('created_at', '>=', $since)->sum('amount_minor');

        return ($wallet + $direct) / 100;
    }
}
