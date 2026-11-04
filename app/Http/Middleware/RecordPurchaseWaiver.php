<?php

namespace App\Http\Middleware;

use App\Services\ConsentRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On routes that start an immediate-delivery purchase, stores the customer's acknowledgement that delivery starts
 * straight away and the 14-day withdrawal right ends once delivered. The web checkout makes the tick-box
 * mandatory; the API records whatever `waiver_accepted` it receives and never blocks (older app builds).
 */
class RecordPurchaseWaiver
{
    public function __construct(private ConsentRecorder $consents) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST')) {
            $this->consents->record(
                $request->user()?->id ?? $request->user('sanctum')?->id,
                'withdrawal_waiver',
                $request,
                'purchase:'.$request->path(),
                granted: $request->boolean('waiver_accepted'),
                meta: array_filter(['product' => $request->input('type') ?? $request->input('product'), 'id' => $request->input('id')], fn ($v) => $v !== null),
            );
        }

        return $next($request);
    }
}
