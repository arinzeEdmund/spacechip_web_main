<x-app-layout>
    <style>
        :root{--primary:#f27457;--secondary:#145454}
        .admin-wrap{max-width:1200px;margin:0 auto;padding:34px 16px 64px}
        .admin-head{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:18px}
        .admin-title{font-weight:950;letter-spacing:-.02em;color:#0b1a1a;font-size:28px}
        .admin-sub{color:rgba(15,31,31,.62);font-weight:650;margin-top:6px}
        .glass{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);border-radius:22px}
        .grid{display:grid;grid-template-columns:1fr;gap:14px}
        @media(min-width:860px){.grid{grid-template-columns:repeat(12,1fr)}}
        .card{padding:16px}
        .kpi{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
        .kpi .label{font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:rgba(15,31,31,.55)}
        .kpi .value{font-size:28px;font-weight:950;color:#0b1a1a;margin-top:6px}
        .kpi .meta{font-size:12px;font-weight:750;color:rgba(15,31,31,.58);margin-top:6px}
        .pill{display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.65);font-weight:850;font-size:12px;color:rgba(15,31,31,.7)}
        .dot{height:8px;width:8px;border-radius:9999px;background:linear-gradient(90deg,var(--primary),var(--secondary))}
        .section-title{font-weight:950;color:#0b1a1a;font-size:16px;margin-bottom:10px}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px 10px;border-bottom:1px solid rgba(15,31,31,.10);text-align:left;font-size:13px;vertical-align:top}
        th{font-weight:900;color:rgba(15,31,31,.62);text-transform:uppercase;letter-spacing:.08em;font-size:11px}
        .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
        .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);font-weight:900;font-size:11px;color:rgba(15,31,31,.72);background:rgba(255,255,255,.6)}
        .tag.good{border-color:rgba(20,84,84,.22);color:rgba(20,84,84,.92)}
        .tag.bad{border-color:rgba(242,116,87,.28);color:rgba(242,116,87,.92)}
        .bar{height:10px;border-radius:9999px;background:rgba(15,31,31,.08);overflow:hidden}
        .bar > span{display:block;height:100%;border-radius:9999px;background:linear-gradient(90deg,var(--primary),var(--secondary))}
        .two{display:grid;grid-template-columns:1fr;gap:12px}
        @media(min-width:860px){.two{grid-template-columns:1fr 1fr}}
    </style>

    <div class="admin-wrap">
        <div class="admin-head">
            <div>
                <div class="admin-title">Admin Dashboard</div>
                <div class="admin-sub">Live overview of users, payments, fulfillment, and system health.</div>
            </div>
            <div class="pill"><span class="dot"></span><span class="mono">{{ now()->toIso8601String() }}</span></div>
        </div>

        <div class="grid">
            <div class="glass card" style="grid-column:span 12">
                <div class="two">
                    <div class="kpi">
                        <div>
                            <div class="label">Users</div>
                            <div class="value">{{ number_format($stats['users_total']) }}</div>
                            <div class="meta">{{ number_format($stats['users_verified']) }} verified · {{ number_format($stats['users_unverified']) }} unverified</div>
                        </div>
                        <span class="tag {{ $stats['users_unverified'] > 0 ? 'bad' : 'good' }}">{{ $stats['users_unverified'] > 0 ? 'Needs review' : 'Healthy' }}</span>
                    </div>

                    <div class="kpi">
                        <div>
                            <div class="label">Payments</div>
                            <div class="value">{{ number_format($stats['payments_total']) }}</div>
                            <div class="meta">{{ number_format($stats['payments_fulfilled']) }} fulfilled · {{ number_format($stats['payments_pending']) }} pending</div>
                        </div>
                        <span class="tag {{ $stats['payments_pending'] > 0 ? 'bad' : 'good' }}">{{ $stats['payments_pending'] > 0 ? 'Pending' : 'All clear' }}</span>
                    </div>
                </div>
            </div>

            <div class="glass card" style="grid-column:span 12">
                <div class="section-title">Payments by Provider</div>
                <div class="two">
                    @foreach($stats['payments_by_provider'] as $provider => $count)
                        @php
                            $pct = $stats['payments_total'] > 0 ? round(($count / $stats['payments_total']) * 100) : 0;
                        @endphp
                        <div style="padding:12px;border-radius:18px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6)">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                                <div style="font-weight:950;color:#0b1a1a;text-transform:capitalize">{{ $provider }}</div>
                                <div class="mono" style="font-weight:900;color:rgba(15,31,31,.62)">{{ number_format($count) }} ({{ $pct }}%)</div>
                            </div>
                            <div class="bar" style="margin-top:10px"><span style="width: {{ $pct }}%"></span></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="glass card" style="grid-column:span 12">
                <div class="section-title">System Health</div>
                <div class="two">
                    <div style="padding:12px;border-radius:18px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6)">
                        <div style="display:flex;justify-content:space-between;gap:10px">
                            <div>
                                <div class="label">Environment</div>
                                <div class="meta mono" style="margin-top:6px">{{ $health['app_env'] }}</div>
                            </div>
                            <span class="tag {{ $health['app_debug'] ? 'bad' : 'good' }}">{{ $health['app_debug'] ? 'Debug ON' : 'Debug OFF' }}</span>
                        </div>
                    </div>
                    <div style="padding:12px;border-radius:18px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6)">
                        <div style="display:flex;justify-content:space-between;gap:10px">
                            <div>
                                <div class="label">Drivers</div>
                                <div class="meta mono" style="margin-top:6px">cache={{ $health['cache_driver'] }} · queue={{ $health['queue_connection'] }} · mail={{ $health['mail_mailer'] }}</div>
                            </div>
                            <span class="tag {{ $health['gloesim_configured'] ? 'good' : 'bad' }}">{{ $health['gloesim_configured'] ? 'GloEsim OK' : 'GloEsim Missing' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass card" style="grid-column:span 12">
                <div class="section-title">Recent Payments</div>
                <div style="overflow:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>User</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Bundle</th>
                                <th>Amount</th>
                                <th>ICCID</th>
                                <th>Synced</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentPayments as $p)
                                @php
                                    $fp = is_array($p->fulfillment_payload) ? $p->fulfillment_payload : [];
                                    $glo = is_array($fp['gloesim'] ?? null) ? $fp['gloesim'] : [];
                                    $iccid = (string) ($fp['iccid'] ?? (data_get($glo, 'iccid') ?? ''));
                                    $synced = (bool) ($fp['synced'] ?? false);
                                @endphp
                                <tr>
                                    <td class="mono">{{ optional($p->created_at)->toDateTimeString() }}</td>
                                    <td class="mono">{{ $p->user?->email }}</td>
                                    <td>{{ $p->provider }}</td>
                                    <td class="mono">{{ $p->status }}</td>
                                    <td class="mono">{{ $p->bundle_id }}</td>
                                    <td class="mono">{{ $p->currency }} {{ number_format(((int) $p->amount_minor) / 100, 2) }}</td>
                                    <td class="mono">{{ $iccid }}</td>
                                    <td>
                                        <span class="tag {{ $synced ? 'good' : 'bad' }}">{{ $synced ? 'Yes' : 'No' }}</span>
                                        <a href="{{ route('admin.payment', ['payment' => $p->id]) }}" class="mono" style="margin-left:10px;font-weight:900;color:rgba(20,84,84,.92)">JSON</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass card" style="grid-column:span 12">
                <div class="section-title">Recent Users</div>
                <div style="overflow:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Email</th>
                                <th>Verified</th>
                                <th>Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentUsers as $u)
                                <tr>
                                    <td class="mono">{{ optional($u->created_at)->toDateTimeString() }}</td>
                                    <td class="mono">{{ $u->email }}</td>
                                    <td><span class="tag {{ $u->email_verified_at ? 'good' : 'bad' }}">{{ $u->email_verified_at ? 'Yes' : 'No' }}</span></td>
                                    <td><span class="tag {{ $u->is_admin ? 'good' : '' }}">{{ $u->is_admin ? 'Yes' : 'No' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
