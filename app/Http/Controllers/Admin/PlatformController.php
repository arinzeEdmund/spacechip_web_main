<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\LoginActivity;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\ProviderStatusService;
use App\Services\Admin\SettingsService;
use App\Services\RentalProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Providers, settings, system health, login activity and the audit trail. */
class PlatformController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function providers(ProviderStatusService $providers): View
    {
        return view('admin.providers', ['status' => $providers->all()]);
    }

    public function refreshProviders(ProviderStatusService $providers): RedirectResponse
    {
        $providers->all(refresh: true);

        return back()->with('status', 'Provider balances refreshed.');
    }

    public function settings(SettingsService $settings): View
    {
        $values = collect(SettingsService::definitions())->map(fn ($def, $key) => $settings->get($key));
        $groups = collect(SettingsService::definitions())->groupBy('group', preserveKeys: true);

        return view('admin.settings', compact('values', 'groups'));
    }

    public function updateSettings(Request $request, SettingsService $settings, RentalProviders $rentalProviders): RedirectResponse
    {
        $input = [];
        foreach (SettingsService::definitions() as $key => $def) {
            if ($def['type'] === 'bool') {
                $input[$key] = $request->boolean($key);
            } elseif ($request->has($key)) {
                $input[$key] = $request->input($key);
            }
        }
        $rules = collect(SettingsService::definitions())
            ->filter(fn ($d) => $d['type'] === 'number')
            ->map(fn ($d) => ['nullable', 'numeric', 'min:'.($d['min'] ?? 0), 'max:'.($d['max'] ?? PHP_INT_MAX)])->all();
        foreach (SettingsService::definitions() as $key => $def) {
            if ($def['type'] === 'choice') {
                $rules[$key] = ['nullable', Rule::in(array_keys($def['options']))];
            }
        }
        validator($input, $rules)->validate();

        // A supplier can only be switched on if its API key is configured.
        $chosen = $input['social_rental_provider'] ?? null;
        if ($chosen && $chosen !== $settings->get('social_rental_provider') && ! $rentalProviders->isConfigured($chosen)) {
            return back()->withErrors(['social_rental_provider' => 'That supplier has no API key configured (set it in .env first), so it cannot be switched on.'])->withInput();
        }

        $changes = $settings->update($input);
        if ($changes) {
            $this->audit->log('settings.updated', 'Updated '.implode(', ', array_keys($changes)), null, $changes);
        }

        return back()->with('status', $changes ? count($changes).' setting(s) saved.' : 'No changes.');
    }

    public function logins(Request $request): View
    {
        $result = (string) $request->query('result', '');
        $channel = (string) $request->query('channel', '');
        $q = trim((string) $request->query('q', ''));

        return view('admin.logins', [
            'activities' => LoginActivity::query()->with('user')
                ->when($result === 'success', fn ($q) => $q->where('successful', true))
                ->when($result === 'failed', fn ($q) => $q->where('successful', false))
                ->when($channel !== '', fn ($q) => $q->where('channel', $channel))
                ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('email', 'like', "%{$q}%")->orWhere('ip_address', 'like', "%{$q}%")))
                ->latest('created_at')->paginate(40)->withQueryString(),
            'stats' => [
                'success_24h' => LoginActivity::where('successful', true)->where('created_at', '>=', now()->subDay())->count(),
                'failed_24h' => LoginActivity::where('successful', false)->where('created_at', '>=', now()->subDay())->count(),
                'unique_users_7d' => LoginActivity::where('successful', true)->where('created_at', '>=', now()->subDays(7))->distinct('user_id')->count('user_id'),
                'active_now' => User::where('last_login_at', '>=', now()->subHour())->count(),
            ],
            'suspiciousIps' => LoginActivity::query()->selectRaw('ip_address, COUNT(*) c')
                ->where('successful', false)->where('created_at', '>=', now()->subDay())
                ->groupBy('ip_address')->having('c', '>=', 5)->orderByDesc('c')->limit(5)->pluck('c', 'ip_address'),
            'filters' => compact('result', 'channel', 'q'),
        ]);
    }

    public function audit(Request $request): View
    {
        $action = (string) $request->query('action', '');

        return view('admin.audit', [
            'entries' => AdminAuditLog::query()->with('admin')
                ->when($action !== '', fn ($q) => $q->where('action', $action))
                ->latest('created_at')->paginate(40)->withQueryString(),
            'actions' => AdminAuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => compact('action'),
        ]);
    }

    public function system(Request $request): View
    {
        $level = strtoupper((string) $request->query('level', ''));

        return view('admin.system', [
            'environment' => [
                'App environment' => app()->environment(),
                'Debug mode' => config('app.debug') ? 'On' : 'Off',
                'App URL' => config('app.url'),
                'Laravel' => app()->version(),
                'PHP' => PHP_VERSION,
                'Database' => config('database.default').' · '.config('database.connections.'.config('database.default').'.database'),
                'Cache store' => config('cache.default'),
                'Queue' => config('queue.default'),
                'Session driver' => config('session.driver'),
                'Mailer' => config('mail.default'),
                'Timezone' => config('app.timezone'),
                'Scheduler' => ($t = (int) \Illuminate\Support\Facades\Cache::get('scheduler:last_run')) ? 'Last ran '.\Illuminate\Support\Carbon::createFromTimestamp($t)->diffForHumans() : 'Not running (add the cron entry)',
            ],
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'failedJobs' => DB::table('failed_jobs')->latest('failed_at')->limit(15)->get(),
            'tables' => collect(['users', 'payments', 'wallet_transactions', 'virtual_number_subscriptions', 'virtual_number_messages', 'social_number_orders', 'social_number_rentals', 'personal_access_tokens', 'login_activities', 'admin_audit_logs'])
                ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]),
            'logLines' => $this->tailLog($level),
            'level' => $level,
        ]);
    }

    public function retryFailedJobs(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        $this->audit->log('system.jobs_retried', 'Retried all failed queue jobs');

        return back()->with('status', 'All failed jobs were pushed back onto the queue.');
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        $this->audit->log('system.cache_cleared', 'Cleared application and view caches');

        return back()->with('status', 'Caches cleared.');
    }

    /** Last ~200 entries of laravel.log, newest first, optionally filtered by level. */
    private function tailLog(string $level): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path)) {
            return [];
        }
        $size = filesize($path);
        $handle = fopen($path, 'r');
        fseek($handle, max(0, $size - 400_000));
        $chunk = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+-]+)\] \w+\.(\w+): (.*)$/m', $chunk, $m, PREG_SET_ORDER);

        return collect($m)
            ->map(fn ($x) => ['at' => $x[1], 'level' => strtoupper($x[2]), 'message' => mb_substr($x[3], 0, 400)])
            ->when($level !== '', fn ($c) => $c->where('level', $level))
            ->reverse()->take(200)->values()->all();
    }
}
