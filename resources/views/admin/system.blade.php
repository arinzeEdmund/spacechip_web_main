<x-admin.layout title="System & logs" subtitle="Runtime configuration, background jobs, data volume and the application log.">
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.system.clear-cache') }}" data-confirm="Clear the application and view caches? Provider balances and catalogues will be re-fetched.">@csrf<x-admin.button type="submit" icon="eraser">Clear caches</x-admin.button></form>
    </x-slot:actions>

    @if ($environment['Debug mode'] === 'On' && app()->isProduction())
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-danger-soft px-4 py-3.5 text-[13.5px] text-danger ring-1 ring-danger/25"><i data-lucide="octagon-alert" class="mt-0.5 h-4 w-4"></i>Debug mode is on in production. Set <code class="font-mono">APP_DEBUG=false</code> to stop stack traces leaking to visitors.</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <x-admin.card title="Environment">
            <dl>@foreach ($environment as $label => $value)<x-admin.kv :label="$label">{{ $value }}</x-admin.kv>@endforeach</dl>
        </x-admin.card>

        <x-admin.card title="Queue" subtitle="Background jobs (emails, renewals)">
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-surface-2 p-4 ring-1 ring-ink/[.04]"><div class="text-[12.5px] text-muted">Pending</div><div class="mt-1 text-[24px] font-semibold tabular">{{ $queue['pending'] }}</div></div>
                <div @class(['rounded-xl p-4 ring-1', 'bg-danger-soft ring-danger/25' => $queue['failed'], 'bg-surface-2 ring-ink/[.04]' => ! $queue['failed']])><div class="text-[12.5px] text-muted">Failed</div><div class="mt-1 text-[24px] font-semibold tabular">{{ $queue['failed'] }}</div></div>
            </div>
            @if ($queue['failed'])
                <form method="POST" action="{{ route('admin.system.retry-jobs') }}" class="mt-4" data-confirm="Push all failed jobs back onto the queue?">@csrf<x-admin.button type="submit" icon="rotate-cw" class="w-full">Retry failed jobs</x-admin.button></form>
                <div class="mt-4 space-y-2">
                    @foreach ($failedJobs as $job)
                        <details class="rounded-lg bg-surface-2 px-3 py-2 text-[12.5px] ring-1 ring-ink/[.04]">
                            <summary class="cursor-pointer truncate font-medium">{{ data_get(json_decode($job->payload, true), 'displayName', 'Job') }} · {{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}</summary>
                            <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap font-mono text-[11.5px] text-danger">{{ \Illuminate\Support\Str::limit($job->exception, 1200) }}</pre>
                        </details>
                    @endforeach
                </div>
            @endif
        </x-admin.card>

        <x-admin.card title="Data volume" subtitle="Rows per table">
            <dl>@foreach ($tables as $table => $count)<x-admin.kv :label="$table"><span class="tabular">{{ number_format($count) }}</span></x-admin.kv>@endforeach</dl>
        </x-admin.card>
    </div>

    <x-admin.card class="mt-6" title="Application log" subtitle="Newest first · storage/logs/laravel.log" :padding="false">
        <x-slot:action>
            <nav class="ad-seg" aria-label="Log level">
                @foreach (['' => 'All', 'ERROR' => 'Errors', 'WARNING' => 'Warnings', 'INFO' => 'Info'] as $key => $label)
                    <a href="{{ route('admin.system', array_filter(['level' => $key])) }}" @class(['is-active' => $level === $key])>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:action>
        <div class="scrollbar-thin mt-4 max-h-[560px] overflow-auto border-t border-ink/[.05]">
            @forelse ($logLines as $line)
                <div class="flex gap-3 border-b border-ink/[.04] px-5 py-2 font-mono text-[12px] leading-relaxed">
                    <span class="shrink-0 text-faint">{{ \Illuminate\Support\Str::of($line['at'])->replace('T', ' ')->limit(19, '') }}</span>
                    <span @class(['w-16 shrink-0 font-medium', 'text-danger' => in_array($line['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']), 'text-warning' => $line['level'] === 'WARNING', 'text-info' => $line['level'] === 'INFO', 'text-muted' => ! in_array($line['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'WARNING', 'INFO'])])>{{ $line['level'] }}</span>
                    <span class="min-w-0 break-all text-muted">{{ $line['message'] }}</span>
                </div>
            @empty
                <x-admin.empty icon="file-text" title="No log entries" class="!py-10" />
            @endforelse
        </div>
    </x-admin.card>
</x-admin.layout>
