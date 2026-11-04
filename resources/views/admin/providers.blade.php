<x-admin.layout title="Providers" subtitle="Upstream services that power payments, eSIMs and numbers.">
    <x-slot:actions>
        <span class="text-[12.5px] text-muted">Checked {{ \Carbon\Carbon::parse($status['checked_at'])->diffForHumans() }}</span>
        <form method="POST" action="{{ route('admin.providers.refresh') }}">@csrf<x-admin.button type="submit" icon="refresh-cw">Check now</x-admin.button></form>
    </x-slot:actions>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($status['providers'] as $p)
            @php
                [$tone, $label] = match (true) {
                    ! $p['configured'] => ['slate', 'Not configured'],
                    ! $p['ok'] => ['red', 'Unreachable'],
                    $p['low_balance'] => ['amber', 'Low balance'],
                    default => ['emerald', 'Operational'],
                };
            @endphp
            <x-admin.card>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-[15px] font-semibold">{{ $p['name'] }}</div>
                        <div class="text-[12.5px] text-muted">{{ $p['role'] }}</div>
                    </div>
                    <x-admin.badge :tone="$tone" dot :pulse="$tone === 'emerald'">{{ $label }}</x-admin.badge>
                </div>
                <div class="mt-6">
                    @if ($p['balance'] !== null)
                        <div class="text-[11.5px] font-medium uppercase tracking-[.08em] text-faint">Balance</div>
                        <div @class(['mt-1 text-[24px] font-semibold tracking-tight tabular', 'text-warning' => $p['low_balance']])>{{ number_format($p['balance'], 2) }} <span class="text-[14px] font-medium text-faint">{{ $p['currency'] }}</span></div>
                    @else
                        <div class="text-[11.5px] font-medium uppercase tracking-[.08em] text-faint">Status</div>
                        <div class="mt-1 text-[14px] font-medium">{{ $p['detail'] }}</div>
                    @endif
                </div>
                @if ($p['balance'] !== null && $p['detail'])<div class="mt-2 text-[12px] text-faint">{{ $p['detail'] }}</div>@endif
            </x-admin.card>
        @endforeach
    </div>
    <p class="mt-5 text-[12.5px] text-muted">Balances are cached for five minutes. The low-balance threshold is set in <a class="text-coral-600 hover:underline" href="{{ route('admin.settings') }}">Settings</a>.</p>
</x-admin.layout>
