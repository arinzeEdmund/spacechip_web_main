@php
    $svg = fn ($p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $needs = [
        ['An unlocked, eSIM-compatible device', '<rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/>'],
        ['Stable Wi‑Fi during installation', '<path d="M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01"/>'],
        ['QR code or LPA string from your dashboard', '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h3M20 17v4"/>'],
    ];
    $platforms = [
        'ios' => ['iPhone', 'iOS', '<rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18h2"/>', [
            'Open <b>Settings → Cellular</b> (or Mobile Data)', 'Tap <b>Add eSIM</b>', 'Scan the QR code from your dashboard',
            'If scanning fails, choose <b>Enter Details Manually</b> and paste the LPA string',
        ]],
        'android' => ['Android', 'Android', '<rect x="5" y="9" width="14" height="10" rx="3"/><path d="M8 5l1.500 2.500M16 5l-1.500 2.500M9 13h.01M15 13h.01"/>', [
            'Open <b>Settings → Network &amp; Internet → SIMs</b>', 'Tap <b>Add SIM → Download a SIM instead</b>', 'Scan the QR code from your dashboard',
            'If available, use manual code entry and paste the LPA string',
        ]],
    ];
@endphp

<x-public-layout bare>
    <style>
        .doc{max-width:880px;margin:0 auto;padding:48px 20px 72px}
        .doc-head{margin-bottom:28px}
        .doc-head h1{margin:0;font-size:clamp(26px,4vw,36px);font-weight:700;letter-spacing:-.03em;color:var(--text)}
        .doc-head p{margin:10px 0 0;font-size:16px;line-height:1.55;color:var(--muted)}
        .gd-needs{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:28px}
        .gd-need{display:flex;align-items:center;gap:12px;padding:14px 16px;font-size:14px;font-weight:500;line-height:1.4;color:var(--text)}
        .gd-tabs{display:none;margin-bottom:14px}
        .gd-cols{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .gd-col .ui-card__head{justify-content:flex-start;gap:12px}
        .gd-col h2{margin:0;font-size:16px;font-weight:650;color:var(--text)}
        .gd-steps{margin:0;padding:6px 20px 8px;list-style:none;counter-reset:s}
        .gd-steps li{position:relative;padding:14px 0 14px 40px;border-top:1px solid var(--border);font-size:14.5px;line-height:1.5;color:var(--muted);counter-increment:s}
        .gd-steps li:first-child{border-top:0}
        .gd-steps li::before{content:counter(s);position:absolute;left:0;top:13px;display:grid;place-items:center;width:26px;height:26px;border-radius:50%;font-size:12.5px;font-weight:700;color:var(--accent-text);background:var(--accent-soft)}
        .gd-steps b{font-weight:600;color:var(--text)}
        .gd-help{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin-top:14px;padding:22px 24px}
        .gd-help h2{margin:0;font-size:16px;font-weight:650;color:var(--text)}
        .gd-help p{margin:6px 0 0;max-width:520px;font-size:14px;line-height:1.55;color:var(--muted)}
        @media(max-width:760px){
            .gd-needs{grid-template-columns:1fr}
            .gd-tabs{display:inline-flex}
            .gd-cols{grid-template-columns:1fr}
            .gd-col[hidden]{display:none}
            .doc{padding-top:32px}
        }
    </style>

    <div class="doc">
        <header class="doc-head">
            <p class="pg-eyebrow" style="margin:0 0 10px">Guide</p>
            <h1>Install your eSIM</h1>
            <p>Use the QR code or the LPA details from your dashboard. It takes about two minutes.</p>
        </header>

        <div class="gd-needs">
            @foreach ($needs as [$text, $icon])
                <div class="ui-card gd-need"><span class="ui-icon-tile">{!! $svg($icon) !!}</span>{{ $text }}</div>
            @endforeach
        </div>

        <div class="ui-seg gd-tabs" id="gdTabs" role="group" aria-label="Choose your device">
            <button type="button" class="is-active" data-p="ios">iPhone</button>
            <button type="button" data-p="android">Android</button>
        </div>

        <div class="gd-cols">
            @foreach ($platforms as $key => [$name, $os, $icon, $steps])
                <section class="ui-card gd-col" data-p="{{ $key }}" @if ($key !== 'ios') data-m-hidden @endif>
                    <div class="ui-card__head">
                        <span class="ui-icon-tile">{!! $svg($icon) !!}</span>
                        <h2>{{ $name }}@if ($os !== $name) <span style="font-weight:500;color:var(--muted)">({{ $os }})</span>@endif</h2>
                    </div>
                    <ol class="gd-steps">
                        @foreach ($steps as $step)<li>{!! $step !!}</li>@endforeach
                    </ol>
                </section>
            @endforeach
        </div>

        <section class="ui-card ui-card--premium gd-help">
            <div>
                <h2>Installation still failing?</h2>
                <p>Make sure the device is unlocked, the OS is up to date and you're on Wi‑Fi. If it still won't install, contact support with your eSIM ICCID and order reference.</p>
            </div>
            <a href="{{ route('contact') }}" class="ui-btn ui-btn--primary">Contact support</a>
        </section>
    </div>

    <script>
        (() => {
            const tabs = document.getElementById('gdTabs');
            const cols = document.querySelectorAll('.gd-col');
            const mq = matchMedia('(max-width:760px)');
            let current = 'ios';
            const apply = () => cols.forEach((c) => { c.hidden = mq.matches && c.dataset.p !== current; });
            tabs.addEventListener('click', (e) => {
                const b = e.target.closest('button'); if (!b) return;
                current = b.dataset.p;
                tabs.querySelectorAll('button').forEach((x) => x.classList.toggle('is-active', x === b));
                apply();
            });
            mq.addEventListener('change', apply); apply();
        })();
    </script>
</x-public-layout>
