@php
    $flagFor = function (?string $iso): string {
        $iso = strtoupper((string) $iso);
        return preg_match('/^[A-Z]{2}$/', $iso) ? mb_chr(ord($iso[0]) + 127397).mb_chr(ord($iso[1]) + 127397) : '☎️';
    };
    $active = $subs->where('status', 'active');
    $monthlyMinor = (int) $active->sum('monthly_amount_minor');
    $currency = strtoupper((string) ($active->first()->currency ?? $subs->first()->currency ?? 'USD'));
    $nextRenewal = $active->pluck('current_period_end')->filter()->sort()->first();
    $counts = [
        'all' => $subs->count(),
        'active' => $active->count(),
        'past_due' => $subs->where('status', 'past_due')->count(),
        'canceled' => $subs->where('status', 'canceled')->count(),
    ];
    $statusMeta = [
        'active' => ['Active', 'success', true],
        'past_due' => ['Past due', 'warning', false],
        'canceled' => ['Canceled', '', false],
        'pending' => ['Provisioning', 'info', true],
    ];
    $icon = [
        'inbox' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/></svg>',
        'copy' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>',
    ];
@endphp

<x-app-layout>
    <style>
        .mn-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}
        .mn-stat{padding:16px 18px}
        .mn-stat dt{font-size:13px;color:var(--muted)}
        .mn-stat dd{margin:6px 0 0;font-size:22px;font-weight:700;letter-spacing:-.02em;color:var(--text);font-variant-numeric:tabular-nums}
        .mn-stat dd small{font-size:13px;font-weight:500;color:var(--muted);letter-spacing:0}
        .mn-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
        .mn-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(340px,1fr))}
        .mn-card{display:flex;flex-direction:column;padding:18px;gap:16px}
        .mn-card[data-status="canceled"]{opacity:.72}
        .mn-top{display:flex;align-items:flex-start;gap:12px}
        .mn-num{display:flex;align-items:center;gap:4px;font-size:17px;font-weight:650;letter-spacing:.01em;color:var(--text);font-variant-numeric:tabular-nums}
        .mn-label{margin-top:2px;font-size:13px;color:var(--muted)}
        .mn-top .ui-pill{margin-left:auto}
        .mn-actions{display:flex;flex-wrap:wrap;gap:8px;padding-top:14px;border-top:1px solid var(--border)}
        .mn-actions .ui-btn--danger-ghost{margin-left:auto;color:var(--danger);background:transparent}
        .mn-actions .ui-btn--danger-ghost:hover{background:var(--danger-soft)}
        /* Inbox drawer */
        .mn-drawer{position:fixed;inset:0;z-index:80;display:flex;justify-content:flex-end;background:rgb(0 0 0 / .45);backdrop-filter:blur(2px)}
        .mn-drawer[hidden]{display:none}
        .mn-panel{display:flex;flex-direction:column;width:min(440px,100%);height:100%;background:var(--surface);border-left:1px solid var(--border);box-shadow:var(--shadow-3);animation:mn-in .28s cubic-bezier(.16,1,.3,1)}
        @keyframes mn-in{from{transform:translateX(40px);opacity:0}}
        .mn-panel header{display:flex;align-items:center;gap:12px;padding:18px 20px;border-bottom:1px solid var(--border)}
        .mn-panel header h2{margin:0;font-size:16px;font-weight:650;color:var(--text)}
        .mn-panel header p{margin:2px 0 0;font-size:13px;color:var(--muted)}
        .mn-thread{flex:1;overflow-y:auto;padding:18px 20px;display:flex;flex-direction:column;gap:10px}
        .mn-msg{max-width:86%;padding:10px 12px;border-radius:14px 14px 14px 4px;background:var(--surface-2);border:1px solid var(--border);font-size:14px;line-height:1.5;color:var(--text);white-space:pre-wrap;word-break:break-word}
        .mn-msg.out{align-self:flex-end;border-radius:14px 14px 4px 14px;background:var(--accent-soft);border-color:transparent}
        .mn-msg small{display:block;margin-top:4px;font-size:11.5px;color:var(--muted)}
        .mn-msg .ui-chip{margin-bottom:6px}
        @media(max-width:720px){.mn-stats{grid-template-columns:1fr 1fr}.mn-stats .mn-stat:last-child{grid-column:1/-1}.mn-grid{grid-template-columns:1fr}}
        @media(max-width:520px){.mn-panel{width:100%;margin-top:56px;height:calc(100% - 56px);border-left:0;border-radius:22px 22px 0 0}}
    </style>

    <div class="pg">
        <div class="pg-head">
            <div>
                <p class="pg-eyebrow">Virtual numbers</p>
                <h1 class="pg-title">My numbers</h1>
                <p class="pg-sub">Your private numbers, their messages and billing in one place.</p>
            </div>
            <div class="pg-actions">
                <a class="ui-btn ui-btn--primary" href="{{ $indexUrl ?? route('dashboard.virtual.index') }}">{!! $icon['plus'] !!} Get a number</a>
            </div>
        </div>

        @if ($subs->isEmpty())
            <div class="ui-empty">
                <div class="ui-empty__icon">{!! $icon['phone'] !!}</div>
                <p class="ui-empty__title">No numbers yet</p>
                <p class="ui-empty__desc">Pick a country, choose a local number and receive SMS and calls anywhere. You can cancel anytime.</p>
                <a class="ui-btn ui-btn--primary" href="{{ $indexUrl ?? route('dashboard.virtual.index') }}" style="margin-top:6px">Browse countries</a>
            </div>
        @else
            <dl class="mn-stats">
                <div class="ui-card mn-stat"><dt>Active numbers</dt><dd>{{ $counts['active'] }} <small>of {{ $counts['all'] }}</small></dd></div>
                <div class="ui-card mn-stat"><dt>Monthly total</dt><dd>{{ $currency }} {{ number_format($monthlyMinor / 100, 2) }}</dd></div>
                <div class="ui-card mn-stat"><dt>Next renewal</dt><dd>{{ $nextRenewal ? $nextRenewal->format('M j') : '—' }} @if ($nextRenewal)<small>{{ $nextRenewal->diffForHumans() }}</small>@endif</dd></div>
            </dl>

            <div class="mn-bar">
                <div class="ui-seg" id="mnFilter" role="group" aria-label="Filter numbers">
                    @foreach (['all' => 'All', 'active' => 'Active', 'past_due' => 'Past due', 'canceled' => 'Canceled'] as $key => $label)
                        @continue($key !== 'all' && $counts[$key] === 0)
                        <button type="button" data-filter="{{ $key }}" @class(['is-active' => $key === 'all'])>{{ $label }} <span class="ui-seg__count">{{ $counts[$key] }}</span></button>
                    @endforeach
                </div>
            </div>

            <div class="mn-grid">
                @foreach ($subs as $s)
                    @php
                        $st = (string) $s->status;
                        [$stLabel, $stTone, $stPulse] = $statusMeta[$st] ?? [ucfirst(str_replace('_', ' ', $st)), '', false];
                        $iso = (string) ($s->country_iso ?? $s->product?->country_iso ?? '');
                        $number = (string) ($s->phone_number ?? '');
                    @endphp
                    <article class="ui-card mn-card" data-sub-id="{{ $s->id }}" data-status="{{ $st }}">
                        <div class="mn-top">
                            <span class="ui-icon-tile ui-icon-tile--neutral" style="font-size:20px" aria-hidden="true">{{ $flagFor($iso) }}</span>
                            <div style="min-width:0">
                                <div class="mn-num">
                                    {{ $number ?: 'Provisioning…' }}
                                    @if ($number)
                                        <button type="button" class="ui-copy" data-copy="{{ $number }}" data-copy-label="Number" aria-label="Copy number">{!! $icon['copy'] !!}</button>
                                    @endif
                                </div>
                                <div class="mn-label">{{ $s->product?->label ?: 'Virtual number' }} · {{ strtoupper($iso) }}</div>
                            </div>
                            <span @class(['ui-pill', 'ui-pill--'.$stTone => $stTone, 'ui-pill--pulse' => $stPulse])><span class="ui-pill__dot"></span>{{ $stLabel }}</span>
                        </div>

                        <dl class="ui-kv">
                            <div><dt>Price</dt><dd>{{ strtoupper($s->currency) }} {{ number_format(((int) $s->monthly_amount_minor) / 100, 2) }} / month</dd></div>
                            <div><dt>{{ $st === 'canceled' ? 'Ends' : 'Renews' }}</dt><dd>{{ $s->current_period_end ? $s->current_period_end->format('M j, Y') : '—' }}</dd></div>
                            <div><dt>Capabilities</dt><dd>
                                @if ($s->cap_sms)<span class="ui-chip">SMS</span>@endif
                                @if ($s->cap_voice)<span class="ui-chip">Voice</span>@endif
                                @if (! $s->cap_sms && ! $s->cap_voice)<span>—</span>@endif
                            </dd></div>
                        </dl>

                        <div class="mn-actions">
                            <button type="button" class="ui-btn ui-btn--secondary ui-btn--sm js-inbox" data-number="{{ $number }}" @disabled($number === '')>{!! $icon['inbox'] !!} Messages</button>
                            @if ($st !== 'canceled')
                                <button type="button" class="ui-btn ui-btn--sm ui-btn--danger-ghost js-cancel">Cancel renewal</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <div class="mn-drawer" id="mnDrawer" hidden>
        <aside class="mn-panel" role="dialog" aria-modal="true" aria-labelledby="mnDrawerTitle">
            <header>
                <span class="ui-icon-tile">{!! $icon['inbox'] !!}</span>
                <div style="min-width:0;flex:1">
                    <h2 id="mnDrawerTitle">Messages</h2>
                    <p id="mnDrawerSub"></p>
                </div>
                <button type="button" class="ui-icon-btn" data-close aria-label="Close messages">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </header>
            <div class="mn-thread" id="mnThread"></div>
        </aside>
    </div>

    <script src="{{ asset('js/virtual-numbers-my.js') }}?v={{ @filemtime(public_path('js/virtual-numbers-my.js')) }}"></script>
</x-app-layout>
