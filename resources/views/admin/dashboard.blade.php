@php use App\Support\Money; @endphp
<x-admin.layout title="Dashboard" subtitle="Everything happening across spacechip, live.">
    <x-slot:actions>
        <span class="text-[12.5px] text-muted tabular">Updated {{ now()->format('M j, H:i') }}</span>
        <x-admin.button href="{{ route('admin.dashboard') }}" icon="refresh-cw">Refresh</x-admin.button>
    </x-slot:actions>

    {{-- Attention --}}
    @if ($alerts->isNotEmpty())
        <div class="mb-6 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($alerts as $alert)
                @php $t = ['danger' => ['bg-danger-soft ring-danger/25 text-danger', 'text-danger', 'octagon-alert'], 'warning' => ['bg-warning-soft ring-warning/25 text-warning', 'text-warning', 'triangle-alert'], 'info' => ['bg-info-soft ring-info/25 text-info', 'text-info', 'info']][$alert['tone']]; @endphp
                <a href="{{ $alert['href'] }}" class="group flex items-start gap-3 rounded-2xl px-4 py-3.5 ring-1 transition hover:-translate-y-0.5 {{ $t[0] }}">
                    <i data-lucide="{{ $t[2] }}" class="mt-0.5 h-[18px] w-[18px] shrink-0 {{ $t[1] }}"></i>
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-medium">{{ $alert['title'] }}</div>
                        <div class="mt-0.5 text-[12.5px] opacity-75">{{ $alert['body'] }}</div>
                    </div>
                    <i data-lucide="chevron-right" class="mt-0.5 h-4 w-4 opacity-40 transition group-hover:translate-x-0.5"></i>
                </a>
            @endforeach
        </div>
    @else
        <div class="mb-6 flex items-center gap-3 rounded-2xl bg-success-soft px-4 py-3 text-[13.5px] text-success ring-1 ring-success/25">
            <i data-lucide="shield-check" class="h-[18px] w-[18px] text-success"></i> All systems normal. No orders or providers need attention.
        </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Revenue · 30 days" :value="Money::usd($kpis['revenue_30d'])" icon="trending-up" tone="coral"
                      :hint="'Today '.Money::usd($kpis['revenue_today']).' · 7d '.Money::usd($kpis['revenue_7d'])"
                      :spark="$series->pluck('revenue')->all()" href="{{ route('admin.payments.index') }}" />
        <x-admin.stat label="Customers" :value="number_format($kpis['users_total'])" icon="users" tone="teal"
                      :hint="'+'.$kpis['users_new_7d'].' this week · '.$kpis['users_active_24h'].' active today'"
                      :spark="$series->pluck('signups')->all()" href="{{ route('admin.users.index') }}" />
        <x-admin.stat label="Wallet float" :value="Money::usd($kpis['wallet_float'])" icon="wallet" tone="emerald"
                      :hint="$kpis['wallets_funded'].' funded wallets · '.Money::usd($kpis['wallet_spend_30d']).' spent (30d)'" href="{{ route('admin.wallet') }}" />
        <x-admin.stat label="Orders needing action" :value="number_format($kpis['payments_stuck'])" icon="siren" :tone="$kpis['payments_stuck'] ? 'red' : 'slate'"
                      :hint="$kpis['payments_failed'].' failed · '.$kpis['payments_abandoned'].' abandoned checkouts'" href="{{ route('admin.payments.index', ['status' => 'attention']) }}" />
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="eSIMs sold" :value="number_format($kpis['esims_sold'])" icon="cpu" tone="blue" href="{{ route('admin.esims') }}" />
        <x-admin.stat label="Virtual numbers" :value="number_format($kpis['virtual_active'])" icon="phone" tone="teal" :hint="$kpis['virtual_past_due'].' past due'" href="{{ route('admin.numbers.virtual') }}" />
        <x-admin.stat label="Social number orders" :value="number_format($kpis['social_orders_total'])" icon="message-square-text" tone="coral"
                      :hint="$kpis['social_orders_received'].' delivered · '.$kpis['social_orders_open'].' open'" href="{{ route('admin.numbers.social') }}" />
        <x-admin.stat label="Failed logins · 24h" :value="$failedLogins24h" icon="shield" :tone="$failedLogins24h > 10 ? 'amber' : 'slate'"
                      :hint="$kpis['users_suspended'].' suspended accounts'" href="{{ route('admin.logins', ['result' => 'failed']) }}" />
    </div>

    {{-- Charts --}}
    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        <x-admin.card class="xl:col-span-2" title="Revenue & sign-ups" subtitle="Last 30 days · settled USD payments, excluding wallet top-ups">
            <x-slot:action>
                <div class="flex items-center gap-4 text-[12px] text-muted">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:var(--accent-fill)"></span>Revenue</span>
                    <span class="flex items-center gap-1.5"><span class="h-0.5 w-3 rounded bg-teal-600"></span>Sign-ups</span>
                </div>
            </x-slot:action>
            @php
                $maxRev = max(1, $series->max('revenue')); $maxSign = max(1, $series->max('signups'));
                $W = 720; $H = 220; $pad = 40; $n = $series->count(); $bw = ($W - $pad) / $n;
                $line = $series->values()->map(fn ($d, $i) => round($pad + $i * $bw + $bw / 2, 1).','.round($H - 22 - ($d['signups'] / $maxSign) * ($H - 50), 1))->implode(' ');
            @endphp
            <div class="relative">
            @if ($series->sum('revenue') === 0)
                <div class="pointer-events-none absolute inset-x-0 top-[38%] text-center text-[13px] text-faint">No settled revenue in this period</div>
            @endif
            <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-[240px] w-full" role="img" aria-label="Daily revenue and sign-ups for the last 30 days">
                <defs><linearGradient id="bar" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#d86c54"/><stop offset="1" stop-color="#b85944"/></linearGradient></defs>
                @foreach ([0, .5, 1] as $g)
                    @php $y = round($H - 22 - $g * ($H - 50), 1); @endphp
                    <line x1="{{ $pad }}" x2="{{ $W }}" y1="{{ $y }}" y2="{{ $y }}" stroke="rgba(128,128,128,0.18)" stroke-dasharray="3 4"/>
                    @if ($series->sum('revenue') > 0 || ! $g)<text x="0" y="{{ $y + 4 }}" font-size="10" fill="#94a3b8">{{ $g ? '$'.number_format($maxRev * $g / 100, $maxRev * $g < 10000 ? 2 : 0) : '0' }}</text>@endif
                @endforeach
                @foreach ($series->values() as $i => $d)
                    @php $bh = $d['revenue'] > 0 ? max(3, ($d['revenue'] / $maxRev) * ($H - 50)) : 0; $x = $pad + $i * $bw + $bw * .18; @endphp
                    <rect x="{{ round($x, 1) }}" y="{{ round($H - 22 - $bh, 1) }}" width="{{ round($bw * .64, 1) }}" height="{{ round($bh, 1) }}" rx="3" fill="url(#bar)" opacity=".9">
                        <title>{{ \Carbon\Carbon::parse($d['date'])->format('M j') }}: {{ Money::usd($d['revenue']) }} · {{ $d['signups'] }} sign-ups</title>
                    </rect>
                    @if ($i % 5 === 0)<text x="{{ round($x + $bw * .32, 1) }}" y="{{ $H - 4 }}" font-size="10" fill="#94a3b8" text-anchor="middle">{{ \Carbon\Carbon::parse($d['date'])->format('M j') }}</text>@endif
                @endforeach
                <polyline points="{{ $line }}" fill="none" stroke="#d86c54" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
            </div>
        </x-admin.card>

        <x-admin.card title="Revenue mix" subtitle="Last 30 days by product">
            @php $mixTotal = max(1, $mix->sum('amount')); $palette = ['var(--accent-fill)', '#b85944', 'var(--text)', '#8a8a8a', '#f5c4b8', 'var(--border-strong)']; @endphp
            @forelse ($mix as $i => $row)
                <div class="mb-3.5 last:mb-0">
                    <div class="mb-1.5 flex items-baseline justify-between text-[13px]">
                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full" style="background:{{ $palette[$i % 6] }}"></span>{{ $row['label'] }}</span>
                        <span class="font-medium tabular">{{ Money::usd($row['amount']) }} <span class="font-normal text-faint">· {{ $row['count'] }}</span></span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" style="width:{{ round($row['amount'] / $mixTotal * 100, 1) }}%;background:{{ $palette[$i % 6] }}"></div></div>
                </div>
            @empty
                <x-admin.empty icon="chart-pie" title="No sales in 30 days" class="!py-6" />
            @endforelse

            <div class="mt-6 border-t border-ink/[.05] pt-4">
                <div class="mb-2.5 text-[11.5px] font-medium uppercase tracking-[.08em] text-faint">Payment outcomes · all time</div>
                <div class="flex flex-wrap gap-x-3 gap-y-2">
                    @foreach ($statusBreakdown as $status => $count)
                        <a href="{{ route('admin.payments.index', ['status' => $status]) }}" class="inline-flex items-center gap-1.5"><x-admin.status :value="$status" /><span class="text-[12px] text-muted tabular">{{ $count }}</span></a>
                    @endforeach
                </div>
            </div>
        </x-admin.card>
    </div>

    {{-- Providers --}}
    <x-admin.card class="mt-6" title="Providers" subtitle="Live balances and connectivity · cached for 5 minutes">
        <x-slot:action><x-admin.button href="{{ route('admin.providers') }}" variant="ghost" icon="arrow-right">Details</x-admin.button></x-slot:action>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($providers['providers'] as $p)
                <div class="flex items-center gap-3 rounded-xl bg-surface-2 px-3.5 py-3 ring-1 ring-ink/[.04]">
                    <span @class(['h-2 w-2 shrink-0 rounded-full', 'bg-emerald-500' => $p['ok'] && ! $p['low_balance'], 'bg-amber-500' => $p['low_balance'], 'bg-red-500' => $p['configured'] && ! $p['ok'], 'bg-border-strong' => ! $p['configured']])></span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[13.5px] font-medium">{{ $p['name'] }}</div>
                        <div class="truncate text-[12px] text-muted tabular">{{ $p['balance'] !== null ? number_format($p['balance'], 2).' '.$p['currency'] : $p['detail'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    {{-- Activity --}}
    <div class="mt-6 grid gap-4 xl:grid-cols-3">
        <x-admin.card class="xl:col-span-2" title="Latest payments">
            <x-slot:action><x-admin.button href="{{ route('admin.payments.index') }}" variant="ghost" icon="arrow-right">All payments</x-admin.button></x-slot:action>
            <x-admin.table class="!min-w-[600px]">
                <thead><tr><th>Customer</th><th>Product</th><th>Status</th><th class="text-right">Amount</th><th class="text-right">When</th></tr></thead>
                <tbody>
                    @forelse ($recentPayments as $p)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('admin.payments.show', $p) }}'">
                            <td><x-admin.user-cell :user="$p->user" /></td>
                            <td><div class="font-medium">{{ $p->displayTitle() }}</div><div class="text-[12px] text-muted">via {{ ucfirst($p->provider) }}</div></td>
                            <td><x-admin.status :value="$p->status" /></td>
                            <td class="text-right font-medium tabular">{{ Money::format($p->amount_minor, $p->currency) }}</td>
                            <td class="whitespace-nowrap text-right text-muted">{{ $p->created_at?->diffForHumans(short: true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-admin.empty icon="receipt" title="No payments yet" /></td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
        </x-admin.card>

        <div class="space-y-4">
            <x-admin.card title="New customers">
                <x-slot:action><x-admin.button href="{{ route('admin.users.index') }}" variant="ghost" icon="arrow-right">All</x-admin.button></x-slot:action>
                <ul class="space-y-3">
                    @foreach ($recentUsers as $u)
                        <li class="flex items-center justify-between gap-3">
                            <x-admin.user-cell :user="$u" />
                            @if ($u->hasVerifiedEmail())<x-admin.badge tone="emerald">Verified</x-admin.badge>@else<x-admin.badge tone="amber">Unverified</x-admin.badge>@endif
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>
            <x-admin.card title="Admin activity">
                <x-slot:action><x-admin.button href="{{ route('admin.audit') }}" variant="ghost" icon="arrow-right">Audit</x-admin.button></x-slot:action>
                @forelse ($recentAudit as $a)
                    <div class="flex gap-3 border-t border-ink/[.05] py-2.5 first:border-t-0 first:pt-0">
                        <x-admin.avatar :user="$a->admin" size="sm" />
                        <div class="min-w-0 text-[13px]"><div class="text-text">{{ $a->description }}</div><div class="text-[12px] text-faint">{{ $a->admin?->name ?? 'System' }} · {{ $a->created_at?->diffForHumans() }}</div></div>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">No admin actions recorded yet.</p>
                @endforelse
            </x-admin.card>
        </div>
    </div>
</x-admin.layout>
