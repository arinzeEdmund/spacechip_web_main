<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Receives browser Content-Security-Policy violation reports so the policy can be tightened with evidence. */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $report = json_decode((string) $request->getContent(), true);
        $r = is_array($report) ? ($report['csp-report'] ?? $report) : [];
        $directive = mb_substr((string) ($r['effective-directive'] ?? $r['violated-directive'] ?? ''), 0, 80);
        $blocked = mb_substr((string) ($r['blocked-uri'] ?? ''), 0, 200);
        $page = mb_substr((string) parse_url((string) ($r['document-uri'] ?? ''), PHP_URL_PATH), 0, 120);

        // One log line per distinct (directive, blocked, page) per hour, so a noisy page can't flood the log.
        if ($directive !== '' && Cache::add('csp:'.sha1($directive.$blocked.$page), 1, 3600)) {
            Log::warning('CSP violation', ['directive' => $directive, 'blocked' => $blocked, 'page' => $page, 'source' => mb_substr((string) ($r['source-file'] ?? ''), 0, 200)]);
        }

        return response()->noContent();
    }
}
