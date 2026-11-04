<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales export for the accountant: one row per settled payment with the evidence VAT/OSS and US sales-tax
 * filings ask for (date, amount, currency, product, buyer's IP country). It does not calculate tax: choosing
 * a tax model (Merchant of Record vs own registrations) is a business decision, see TAX_POSITION.md.
 */
class ReportController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index()
    {
        return view('admin.reports');
    }

    public function sales(Request $request): StreamedResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $from = \Illuminate\Support\Carbon::parse($data['from'])->startOfDay();
        $to = \Illuminate\Support\Carbon::parse($data['to'])->endOfDay();
        $this->audit->log('report.sales_exported', "Exported sales {$from->toDateString()} to {$to->toDateString()}");

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'reference', 'product', 'provider', 'currency', 'amount', 'status', 'buyer_ip_country', 'user_id']);
            Payment::query()->whereBetween('created_at', [$from, $to])->whereIn('status', ['paid', 'fulfilled', 'refunded', 'paid_failed_provision_refunded'])
                ->orderBy('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $p) {
                        fputcsv($out, array_map([$this, 'safe'], [
                            $p->created_at?->toDateTimeString(), $p->provider_reference, $p->productTypeLabel().' '.$p->displayTitle(), $p->provider,
                            $p->currency, number_format($p->amount_minor / 100, 2, '.', ''), $p->status, $p->ip_country ?? '', $p->user_id,
                        ]));
                    }
                });
            fclose($out);
        }, 'sales-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Stops spreadsheet formula injection from user-controlled text. */
    private function safe(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
    }
}
