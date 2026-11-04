<x-admin.layout title="Login activity" subtitle="Sign-ins across web, Google/Facebook and the mobile app.">
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Successful · 24h" :value="$stats['success_24h']" icon="log-in" tone="emerald" />
        <x-admin.stat label="Failed · 24h" :value="$stats['failed_24h']" icon="shield-alert" :tone="$stats['failed_24h'] > 10 ? 'red' : 'slate'" />
        <x-admin.stat label="Unique users · 7 days" :value="$stats['unique_users_7d']" icon="users" tone="teal" />
        <x-admin.stat label="Signed in this hour" :value="$stats['active_now']" icon="radio" tone="coral" />
    </div>

    @if ($suspiciousIps->isNotEmpty())
        <div class="mb-6 rounded-2xl bg-warning-soft px-4 py-3.5 ring-1 ring-warning/25">
            <div class="flex items-center gap-2 text-[13.5px] font-medium text-warning"><i data-lucide="triangle-alert" class="h-4 w-4 text-warning"></i>Repeated failed sign-ins in the last 24 hours</div>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($suspiciousIps as $ip => $count)
                    <a href="{{ route('admin.logins', ['q' => $ip, 'result' => 'failed']) }}" class="rounded-lg bg-surface px-2.5 py-1 font-mono text-[12.5px] text-warning ring-1 ring-warning/25 hover:bg-warning-soft">{{ $ip }} · {{ $count }}×</a>
                @endforeach
            </div>
        </div>
    @endif

    <x-admin.card :padding="false"><div class="p-5">
        <x-admin.filters>
            <x-admin.input name="q" icon="search" value="{{ $filters['q'] }}" placeholder="Email or IP address" class="w-64" />
            <x-admin.select name="result" :selected="$filters['result']" :options="['' => 'All results', 'success' => 'Successful', 'failed' => 'Failed']" />
            <x-admin.select name="channel" :selected="$filters['channel']" :options="['' => 'All channels', 'web' => 'Website', 'social' => 'Google / Facebook', 'api' => 'Mobile app']" />
        </x-admin.filters>
        <x-admin.table>
            <thead><tr><th>Result</th><th>Account</th><th>Channel</th><th>Device</th><th>IP address</th><th class="text-right">When</th></tr></thead>
            <tbody>
                @forelse ($activities as $a)
                    <tr>
                        <td>@if ($a->successful)<x-admin.badge tone="emerald" dot>Success</x-admin.badge>@else<x-admin.badge tone="red" dot>Failed</x-admin.badge>@endif</td>
                        <td>@if ($a->user)<x-admin.user-cell :user="$a->user" />@else<span class="text-muted">{{ $a->email ?? 'Unknown' }}</span>@endif</td>
                        <td class="text-muted">{{ ['web' => 'Website', 'social' => 'Google / Facebook', 'api' => 'Mobile app'][$a->channel] ?? $a->channel }}</td>
                        <td class="text-muted">{{ $a->device }}</td>
                        <td class="font-mono text-[12.5px] text-muted">{{ $a->ip_address }}</td>
                        <td class="whitespace-nowrap text-right text-muted">{{ $a->created_at?->format('M j, H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty icon="key-round" title="No sign-ins recorded yet" body="Every sign-in from now on appears here, including failed attempts." /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        {{ $activities->links('admin.partials.pagination') }}
    </div></x-admin.card>
</x-admin.layout>
