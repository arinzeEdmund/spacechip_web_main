<?php

use App\Services\AiraloService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Schedule (cron: `php artisan schedule:run` every minute) ──────────────────

Schedule::command('virtual-numbers:renew')->hourly();
Schedule::command('social-rentals:renew')->hourly()->withoutOverlapping();
Schedule::command('airalo:sync-catalog')->hourly();

// ─── Commands ─────────────────────────────────────────────────────────────────

Artisan::command('airalo:sync-catalog', function (AiraloService $airalo) {
    $this->info('Syncing Airalo catalog and warming cache...');
    $airalo->refreshCatalog();
    $this->info('Airalo catalog sync completed successfully.');
})->purpose('Sync Airalo catalog and warm package caches');

// One-off migration from the original SQLite database (connection: sqlite_legacy). Truncates
// each MySQL table that also exists in SQLite and copies the rows across in chunks of 500.
Artisan::command('db:sqlite-to-mysql', function () {
    $sourcePath = database_path('database.sqlite');

    if (! file_exists($sourcePath)) {
        $this->error("SQLite database file not found at: {$sourcePath}");

        return 1;
    }

    $source = DB::connection('sqlite_legacy');
    $target = DB::connection('mysql');

    $tablesRaw = $source->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'");
    $sourceTables = array_values(array_filter(array_map(fn ($r) => (string) ($r->name ?? ''), $tablesRaw)));

    $targetTablesRaw = $target->select('SHOW TABLES');
    $targetTables = [];
    foreach ($targetTablesRaw as $row) {
        $arr = (array) $row;
        $targetTables[] = (string) (array_values($arr)[0] ?? '');
    }
    $targetTables = array_values(array_filter($targetTables));

    $target->statement('SET FOREIGN_KEY_CHECKS=0');

    foreach ($sourceTables as $table) {
        if (! in_array($table, $targetTables, true)) {
            $this->line("Skipping {$table} (not present in MySQL)");

            continue;
        }

        $this->line("Copying {$table}...");

        $target->table($table)->truncate();

        $columnsInfo = $source->select("PRAGMA table_info('{$table}')");
        $columns = array_values(array_filter(array_map(fn ($c) => (string) ($c->name ?? ''), $columnsInfo)));

        $orderColumn = in_array('id', $columns, true) ? 'id' : ($columns[0] ?? null);
        if (! $orderColumn) {
            continue;
        }

        $offset = 0;
        $chunk = 500;
        while (true) {
            $rows = $source->table($table)->orderBy($orderColumn)->offset($offset)->limit($chunk)->get();
            if ($rows->isEmpty()) {
                break;
            }

            $payload = [];
            foreach ($rows as $row) {
                $payload[] = (array) $row;
            }

            $target->table($table)->insert($payload);
            $offset += $chunk;
        }
    }

    $target->statement('SET FOREIGN_KEY_CHECKS=1');
    $this->info('Done.');

    return 0;
})->purpose('Copy data from SQLite database.sqlite into MySQL tables');

// Heartbeat so the admin System page and `php artisan launch:check` can prove the scheduler is really running.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('scheduler:last_run', now()->timestamp, 86400))->everyMinute()->name('scheduler-heartbeat');

// Security and data hygiene
Schedule::command('wallet:reconcile')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('messages:purge')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
