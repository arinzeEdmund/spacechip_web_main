<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `php artisan launch:check` : one command that verifies everything that must be true before real customers
 * use the site, and prints exact instructions for the parts only the owner can do (dashboards, lawyers).
 */
class LaunchCheck extends Command
{
    protected $signature = 'launch:check {--strict : Treat warnings as failures}';

    protected $description = 'Verify production readiness (migrations, encryption, secrets, webhooks, scheduler, legal details)';

    private int $fails = 0;

    private int $warns = 0;

    public function handle(): int
    {
        $prod = app()->isProduction();

        $this->line('<options=bold>Environment</>');
        $this->check(config('app.key') !== '' && config('app.key') !== null, 'APP_KEY is set', 'Run php artisan key:generate');
        $this->check(! config('app.debug'), 'APP_DEBUG is off', 'Set APP_DEBUG=false in production', $prod);
        $this->check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses https', 'Set APP_URL=https://your-domain', $prod);
        $this->check((bool) config('session.secure') || ! $prod, 'Session cookie is Secure-only', 'Set SESSION_SECURE_COOKIE=true', $prod);
        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'A real mail driver is configured (admin emailed codes, OTPs)', 'Set MAIL_MAILER and credentials', $prod);

        $this->line('<options=bold>Database</>');
        $pending = $this->pendingMigrations();
        $this->check($pending === 0, 'All migrations applied', "{$pending} pending: run php artisan migrate --force");
        $unencrypted = $this->unencryptedRows();
        $this->check($unencrypted === 0, 'Stored messages and eSIM data are encrypted', "{$unencrypted} legacy rows: run php artisan data:encrypt-messages");
        $this->check(User::query()->where('is_admin', true)->exists() || config('admin.emails'), 'At least one admin exists', 'Set ADMIN_EMAILS or flag a user as admin');

        $this->line('<options=bold>Webhooks and secrets</>');
        $this->check(filled(config('services.paystack.secret_key')), 'Paystack secret key set', 'Set PAYSTACK_SECRET_KEY');
        $this->warn2(false, 'Paystack webhook registered', 'Not checkable from here. In the Paystack dashboard (Settings → API Keys & Webhooks) set the Webhook URL to '.url('/api/paystack/webhook'));
        $secret = (string) config('services.airalo.webhook_secret');
        $this->check($secret !== '', 'AIRALO_WEBHOOK_SECRET is set', 'Generate one (e.g. '.bin2hex(random_bytes(16)).') and set AIRALO_WEBHOOK_SECRET', $prod);
        if ($secret !== '') {
            $this->line('   Give Airalo this callback URL: '.url('/api/airalo/webhook').'?token=<your secret>');
        }
        $this->check(config('legal.country_block_enabled') && ! empty(config('legal.blocked_countries')), 'Sanctioned-country block is enabled ('.implode(',', (array) config('legal.blocked_countries')).')', 'Set COUNTRY_BLOCK_ENABLED=true and BLOCKED_COUNTRIES');
        $this->warn2(false, 'Country header available', 'The block needs a CDN/host country header (Cloudflare CF-IPCountry, Vercel X-Vercel-IP-Country, CloudFront). Put the site behind one.');

        $this->line('<options=bold>Abuse protection (floods on sign-up / sign-in)</>');
        $this->check(filled(config('security.turnstile.secret_key')), 'Bot challenge (Cloudflare Turnstile) is on for web forms', 'Set TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY (free at dash.cloudflare.com → Turnstile)', $prod);
        $this->check(filled(env('TRUSTED_PROXIES')) || ! $prod, 'TRUSTED_PROXIES set so rate limits see real client IPs', 'Behind Cloudflare/a load balancer set TRUSTED_PROXIES to its IP ranges (or * if the origin is only reachable through it)', $prod);
        $this->check(config('cache.limiter') !== 'database', 'Rate-limit counters are not stored in the database', 'Set CACHE_LIMITER_STORE=redis (or file on a single server)', $prod);
        $this->warn2(config('session.driver') !== 'database', 'Sessions are not in the database', 'A flood of visitors creates one session row each; use SESSION_DRIVER=redis (or file on one server)');
        $this->warn2(false, 'Edge DDoS protection', 'Rate limits protect the app, not the network. Put the site behind Cloudflare (or similar) with its DDoS shield and a rate-limiting rule on /login and /register.');

        $this->line('<options=bold>Scheduler</>');
        $beat = (int) Cache::get('scheduler:last_run');
        $this->check($beat > 0 && now()->timestamp - $beat < 300, 'Scheduler ran in the last 5 minutes', 'Add the cron entry: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1', $prod);

        $this->line('<options=bold>Content-Security-Policy</>');
        $this->warn2((bool) config('security.csp_enforce'), 'Strict CSP enforced', 'Currently report-only. Watch storage/logs (CSP violation) for a couple of quiet weeks, then set CSP_ENFORCE=true.');

        $this->line('<options=bold>Legal details</>');
        foreach (['company_name' => 'COMPANY_NAME', 'company_number' => 'COMPANY_NUMBER', 'company_address' => 'COMPANY_ADDRESS', 'company_country' => 'COMPANY_COUNTRY', 'governing_law' => 'GOVERNING_LAW'] as $key => $env) {
            $this->check(filled(config("legal.{$key}")), "{$env} is filled", "Set {$env} in .env (shown on Terms and Privacy)", false);
        }
        $this->warn2((bool) config('legal.reviewed'), 'Policies reviewed by a lawyer', 'Have counsel review the pages, then set LEGAL_REVIEWED=true to remove the draft banner. See SECURITY_AND_LAW/legal-drafts/LAUNCH_CHECKLIST.md.');
        $this->warn2(filled(config('compliance.kyc_threshold_usd')), 'KYC threshold set', 'Decide a spend level for identity checks and set KYC_THRESHOLD_USD (see TAX_POSITION.md / counsel).');

        $this->newLine();
        $this->line("Failures: {$this->fails}   Warnings: {$this->warns}");

        return ($this->fails > 0 || ($this->option('strict') && $this->warns > 0)) ? self::FAILURE : self::SUCCESS;
    }

    /** A hard requirement: fails only when $enforce (so local dev just warns). */
    private function check(bool $ok, string $label, string $fix, bool $enforce = true): void
    {
        if ($ok) {
            $this->line("  <info>✔</info> {$label}");

            return;
        }
        if ($enforce) {
            $this->fails++;
            $this->line("  <error> ✘ </error> {$label}\n      → {$fix}");
        } else {
            $this->warns++;
            $this->line("  <comment>! {$label}</comment>\n      → {$fix}");
        }
    }

    /** Needs a human or a dashboard: always a warning, never a failure. */
    private function warn2(bool $ok, string $label, string $todo): void
    {
        if ($ok) {
            $this->line("  <info>✔</info> {$label}");

            return;
        }
        $this->warns++;
        $this->line("  <comment>! {$label}</comment>\n      → {$todo}");
    }

    private function pendingMigrations(): int
    {
        $migrator = app('migrator');
        $files = array_keys($migrator->getMigrationFiles($migrator->paths() + [database_path('migrations')]));
        $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];

        return count(array_diff($files, $ran));
    }

    private function unencryptedRows(): int
    {
        $n = 0;
        $legacy = fn ($q, $col) => $q->whereNotNull($col)->where($col, '!=', '')->where($col, 'not like', 'eyJ%')->where($col, 'not like', '"eyJ%');
        foreach ([['social_number_orders', 'sms_code'], ['social_number_orders', 'sms_text'], ['social_number_rentals', 'sms_messages'], ['virtual_number_messages', 'body'], ['payments', 'fulfillment_payload']] as [$table, $col]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                $n += $legacy(DB::table($table), $col)->count();
            }
        }

        return $n;
    }
}
