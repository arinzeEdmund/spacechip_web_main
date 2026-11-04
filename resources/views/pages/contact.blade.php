@php
    $mail = (string) config('mail.from.address', 'support@spacechip.com');
    $svg = fn ($p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $checklist = [
        ['Payment reference', 'Your Paystack reference or Cryptomus order ID'],
        ['eSIM details', 'The eSIM ID and ICCID shown on your dashboard'],
        ['Your device', 'Model and OS version'],
        ['A screenshot', 'Of the error, if there is one'],
    ];
@endphp

<x-public-layout bare>
    <style>
        .doc{max-width:880px;margin:0 auto;padding:48px 20px 72px}
        .doc-head{margin-bottom:28px}
        .doc-head h1{margin:0;font-size:clamp(26px,4vw,36px);font-weight:700;letter-spacing:-.03em;color:var(--text)}
        .doc-head p{margin:10px 0 0;font-size:16px;line-height:1.55;color:var(--muted)}
        .doc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .doc-link{display:flex;flex-direction:column;gap:14px;padding:20px;text-decoration:none;color:inherit}
        .doc-link h2{margin:0;font-size:12px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--faint)}
        .doc-link strong{display:block;margin-top:2px;font-size:16px;font-weight:650;color:var(--text);word-break:break-word}
        .doc-link p{margin:6px 0 0;font-size:14px;line-height:1.55;color:var(--muted)}
        .doc-link .go{margin-top:auto;display:inline-flex;align-items:center;gap:4px;font-size:13.5px;font-weight:600;color:var(--accent-text)}
        .doc-link .go svg{width:15px;height:15px;transition:transform .18s}
        .doc-link:hover .go svg{transform:translateX(3px)}
        .doc-card{margin-top:14px}
        .doc-list{margin:0;padding:0;list-style:none}
        .doc-list li{display:flex;align-items:flex-start;gap:14px;padding:14px 20px;border-top:1px solid var(--border)}
        .doc-list li:first-child{border-top:0}
        .doc-list .ui-icon-tile{width:32px;height:32px;border-radius:10px}
        .doc-list .ui-icon-tile svg{width:16px;height:16px}
        .doc-list b{display:block;font-size:14.5px;font-weight:600;color:var(--text)}
        .doc-list span{font-size:13.5px;color:var(--muted)}
        @media(max-width:640px){.doc-grid{grid-template-columns:1fr}.doc{padding-top:32px}}
    </style>

    <div class="doc">
        <header class="doc-head">
            <p class="pg-eyebrow" style="margin:0 0 10px">Support</p>
            <h1>Contact us</h1>
            <p>Get help with purchases, activation, refunds or account issues. We usually reply within a day.</p>
        </header>

        <div class="doc-grid">
            <a class="ui-card ui-card--interactive doc-link" href="mailto:{{ $mail }}">
                <span class="ui-icon-tile">{!! $svg('<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>') !!}</span>
                <div>
                    <h2>Email</h2>
                    <strong>{{ $mail }}</strong>
                    <p>Include your order reference and the ICCID (if available) for faster support.</p>
                </div>
                <span class="go">Send an email {!! $svg('<path d="M5 12h14M13 6l6 6-6 6"/>') !!}</span>
            </a>

            <a class="ui-card ui-card--interactive doc-link" href="{{ route('help') }}">
                <span class="ui-icon-tile">{!! $svg('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7M12 17h.01"/>') !!}</span>
                <div>
                    <h2>Help center</h2>
                    <strong>Browse common questions</strong>
                    <p>Start with activation steps, QR install and troubleshooting tips.</p>
                </div>
                <span class="go">Open help center {!! $svg('<path d="M5 12h14M13 6l6 6-6 6"/>') !!}</span>
            </a>
        </div>

        <section class="ui-card doc-card">
            <div class="ui-card__head"><h2 class="pg-section-title">What to send us</h2><span class="ui-pill ui-pill--accent">Resolves issues faster</span></div>
            <ul class="doc-list">
                @foreach ($checklist as [$title, $desc])
                    <li>
                        <span class="ui-icon-tile">{!! $svg('<path d="m5 12 5 5L20 7"/>') !!}</span>
                        <div><b>{{ $title }}</b><span>{{ $desc }}</span></div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-public-layout>
