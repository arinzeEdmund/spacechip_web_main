<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\LoginActivity;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\AdminMetrics;
use App\Services\Admin\ProviderStatusService;
use App\Services\Admin\SettingsService;
use Illuminate\View\View;

/**
 * Admin home: revenue and order metrics, provider health and items needing attention.
 */
class DashboardController extends Controller
{
    public function __invoke(AdminMetrics $metrics, ProviderStatusService $providers, SettingsService $settings): View
    {
        $kpis = $metrics->kpis();
        $providerStatus = $providers->all();

        $alerts = collect();
        if ($kpis['payments_stuck'] > 0) {
            $alerts->push(['tone' => 'danger', 'title' => "{$kpis['payments_stuck']} paid order(s) not fulfilled", 'body' => 'Customers were charged but have not received their product.', 'href' => route('admin.payments.index', ['status' => 'attention'])]);
        }
        foreach ($providerStatus['providers'] as $p) {
            if ($p['configured'] && ! $p['ok']) {
                $alerts->push(['tone' => 'danger', 'title' => "{$p['name']} is unreachable", 'body' => (string) $p['detail'], 'href' => route('admin.providers')]);
            } elseif ($p['low_balance']) {
                $alerts->push(['tone' => 'warning', 'title' => "{$p['name']} balance is low", 'body' => number_format((float) $p['balance'], 2)." {$p['currency']} remaining.", 'href' => route('admin.providers')]);
            }
        }
        if ($kpis['virtual_past_due'] > 0) {
            $alerts->push(['tone' => 'warning', 'title' => "{$kpis['virtual_past_due']} virtual number(s) past due", 'body' => 'Renewal payments failed.', 'href' => route('admin.numbers.virtual', ['status' => 'past_due'])]);
        }
        if (config('app.debug') && app()->isProduction()) {
            $alerts->push(['tone' => 'danger', 'title' => 'Debug mode is on in production', 'body' => 'Stack traces and secrets can leak to visitors. Set APP_DEBUG=false.', 'href' => route('admin.system')]);
        }
        if ($settings->enabled('maintenance_mode')) {
            $alerts->push(['tone' => 'info', 'title' => 'Maintenance mode is on', 'body' => 'Customers cannot use the app.', 'href' => route('admin.settings')]);
        }

        return view('admin.dashboard', [
            'kpis' => $kpis,
            'series' => $metrics->dailySeries(30),
            'mix' => $metrics->revenueMix(),
            'statusBreakdown' => $metrics->paymentStatusBreakdown(),
            'providers' => $providerStatus,
            'alerts' => $alerts,
            'recentPayments' => Payment::with('user')->latest()->limit(8)->get(),
            'recentUsers' => User::latest()->limit(6)->get(),
            'recentAudit' => AdminAuditLog::with('admin')->latest('created_at')->limit(6)->get(),
            'failedLogins24h' => LoginActivity::where('successful', false)->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }
}
