@php
    $topics = [
        'esim' => [
            'title' => 'eSIMs & installation',
            'blurb' => 'Install, activate and top up your data plans.',
            'icon' => '<path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><rect x="8.5" y="11" width="7" height="6" rx="1.2"/>',
            'faqs' => [
                ['I paid successfully but my eSIM is not showing', 'Open <strong>Dashboard → My eSIMs</strong> and give it a few seconds — new eSIMs appear once the provider confirms them. If it is still missing after a few minutes, contact support with your Paystack reference or Cryptomus order ID.'],
                ['How do I install my eSIM?', 'On the eSIM card, choose <strong>Install on iPhone</strong> or <strong>Install on Android</strong> where available, or scan the QR code from another screen. The step-by-step walkthrough is in the <a href=":guide">eSIM guide</a>.'],
                ['The card says “No QR” — what now?', 'Some providers deliver the QR or LPA details a little later. Refresh the dashboard after a few minutes. If you see an ICCID but still no activation details, contact support.'],
                ['How do I install manually?', 'Open <strong>Installation details</strong> on the eSIM card and copy the SM-DP+ address and activation code (or the full LPA string) into your phone’s “Enter details manually” screen.'],
                ['Can I top up an existing eSIM?', 'Yes, when the provider allows renewals. Eligible eSIMs show a <strong>Top up</strong> action on their card.'],
            ],
        ],
        'payments' => [
            'title' => 'Wallet & payments',
            'blurb' => 'Deposits, card and crypto payments, receipts.',
            'icon' => '<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M16 15h2"/>',
            'faqs' => [
                ['How does the wallet work?', 'Your wallet is held in USD. Deposit by card from the dashboard, then pay for eSIMs and numbers instantly from your balance.'],
                ['Which payment methods are supported?', 'Cards through Paystack, and selected cryptocurrencies through Cryptomus at checkout.'],
                ['My deposit hasn’t appeared yet', 'Card deposits usually land within seconds. Use <strong>Refresh</strong> on the wallet card. If the payment succeeded but the balance didn’t change, send us the payment reference.'],
            ],
        ],
        'numbers' => [
            'title' => 'Virtual & social numbers',
            'blurb' => 'Monthly numbers, inboxes and verification codes.',
            'icon' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
            'faqs' => [
                ['Where do I read messages sent to my number?', 'Go to <strong>My numbers</strong> and choose <strong>Messages</strong> on the number’s card. Texts and voicemails appear there.'],
                ['How do I stop a monthly number from renewing?', 'On <strong>My numbers</strong>, choose <strong>Cancel renewal</strong>. The number keeps working until the end of the period you already paid for.'],
                ['A verification code never arrived', 'One-time verification numbers wait a limited time for the code. If no code arrives, the order closes and the charge is returned to your wallet.'],
            ],
        ],
        'account' => [
            'title' => 'Account & security',
            'blurb' => 'Sign-in, passwords and your data.',
            'icon' => '<path d="M12 3l7 3.5v5.5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6.5L12 3Z"/><path d="m9 12 2 2 4-4"/>',
            'faqs' => [
                ['I forgot my password', 'Use <a href=":forgot">Forgot your password?</a> on the sign-in screen and we’ll email you a reset link.'],
                ['How do I change my name, email or password?', 'Open <strong>Account</strong> from the menu. Changing your email asks you to verify the new address.'],
                ['How do I delete my account?', 'Open <strong>Account → Delete account</strong>. Use any wallet balance and install active eSIMs first — deleted accounts can’t be recovered.'],
            ],
        ],
    ];
    $links = [':guide' => route('esim.guide'), ':forgot' => route('password.request')];
    $svg = fn ($p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
@endphp

<x-public-layout bare>
    <style>
        .hc-hero{position:relative;overflow:hidden;padding:64px 20px 56px;text-align:center;border-bottom:1px solid var(--border);background:radial-gradient(700px 300px at 50% -20%,rgb(var(--accent-rgb) / .16),transparent 70%)}
        .hc-hero h1{margin:0;font-size:clamp(28px,4.5vw,42px);font-weight:700;letter-spacing:-.035em;color:var(--text)}
        .hc-hero p{margin:12px auto 0;max-width:520px;font-size:16px;line-height:1.55;color:var(--muted)}
        .hc-hero .pg-eyebrow{margin:0 0 10px;color:var(--accent-text)}
        .hc-search{max-width:560px;margin:28px auto 0;text-align:left}
        .hc-search .ui-input{height:54px;border-radius:16px;font-size:15.5px;box-shadow:var(--shadow-2)}
        .hc-search svg{left:18px;width:20px;height:20px}
        .hc-search .ui-input{padding-left:50px}
        .hc-body{max-width:1040px;margin:0 auto;padding:40px 20px 72px}
        .hc-topics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:40px}
        .hc-topic{display:flex;flex-direction:column;gap:12px;padding:18px;text-decoration:none;color:inherit}
        .hc-topic h2{margin:0;font-size:15px;font-weight:650;color:var(--text)}
        .hc-topic p{margin:4px 0 0;font-size:13px;line-height:1.5;color:var(--muted)}
        .hc-topic small{margin-top:auto;font-size:12.5px;font-weight:600;color:var(--accent-text)}
        .hc-group{display:grid;grid-template-columns:240px minmax(0,1fr);gap:32px;padding:28px 0;border-top:1px solid var(--border);scroll-margin-top:84px}
        .hc-group h3{margin:0;font-size:17px;font-weight:650;letter-spacing:-.01em;color:var(--text)}
        .hc-group > div:first-child p{margin:6px 0 0;font-size:14px;color:var(--muted)}
        .hc-faqs .ui-disclosure:first-child{border-top:0}
        .hc-faqs .ui-disclosure > summary{font-size:15px;padding:16px 0}
        .hc-faqs a{color:var(--accent-text);font-weight:600;text-decoration:none}
        .hc-faqs strong{color:var(--text);font-weight:600}
        .hc-none{display:none}
        .hc-contact{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;margin-top:40px;padding:28px}
        .hc-contact h2{margin:0;font-size:18px;font-weight:650;color:var(--text)}
        .hc-contact p{margin:6px 0 0;font-size:14px;color:var(--muted)}
        @media(max-width:900px){.hc-topics{grid-template-columns:1fr 1fr}.hc-group{grid-template-columns:1fr;gap:8px}}
        @media(max-width:480px){.hc-topics{grid-template-columns:1fr}.hc-hero{padding:44px 16px 40px}}
    </style>

    <section class="hc-hero">
        <p class="pg-eyebrow">Help center</p>
        <h1>How can we help?</h1>
        <p>Answers for buying, installing and managing your eSIMs, wallet and numbers.</p>
        <label class="ui-search hc-search">
            {!! $svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>') !!}
            <span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Search help articles</span>
            <input type="search" id="hcSearch" class="ui-input" placeholder="Search e.g. “install”, “wallet”, “code”" autocomplete="off">
        </label>
    </section>

    <div class="hc-body">
        <div class="hc-topics" id="hcTopics">
            @foreach ($topics as $key => $topic)
                <a href="#{{ $key }}" class="ui-card ui-card--interactive hc-topic">
                    <span class="ui-icon-tile">{!! $svg($topic['icon']) !!}</span>
                    <div>
                        <h2>{{ $topic['title'] }}</h2>
                        <p>{{ $topic['blurb'] }}</p>
                    </div>
                    <small>{{ count($topic['faqs']) }} articles</small>
                </a>
            @endforeach
        </div>

        <div id="hcGroups">
            @foreach ($topics as $key => $topic)
                <section class="hc-group" id="{{ $key }}">
                    <div>
                        <h3>{{ $topic['title'] }}</h3>
                        <p>{{ $topic['blurb'] }}</p>
                    </div>
                    <div class="hc-faqs">
                        @foreach ($topic['faqs'] as [$q, $a])
                            <details class="ui-disclosure">
                                <summary>{{ $q }}</summary>
                                <div class="ui-disclosure__body">{!! strtr($a, $links) !!}</div>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="ui-empty hc-none" id="hcNone">
            <div class="ui-empty__icon">{!! $svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>') !!}</div>
            <p class="ui-empty__title">No articles match your search</p>
            <p class="ui-empty__desc">Try another word, or ask our team directly — we usually reply within a day.</p>
        </div>

        <div class="ui-card ui-card--premium hc-contact">
            <div>
                <h2>Still need help?</h2>
                <p>Our support team can look into payments, eSIM delivery and account issues.</p>
            </div>
            <div class="pg-actions">
                <a class="ui-btn ui-btn--secondary" href="{{ route('esim.guide') }}">Read the eSIM guide</a>
                <a class="ui-btn ui-btn--primary" href="{{ route('contact') }}">Contact support</a>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const input = document.getElementById('hcSearch');
            const topics = document.getElementById('hcTopics');
            const none = document.getElementById('hcNone');
            const groups = Array.from(document.querySelectorAll('.hc-group'));
            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                let total = 0;
                groups.forEach((g) => {
                    let shown = 0;
                    g.querySelectorAll('details').forEach((d) => {
                        const hit = !q || d.textContent.toLowerCase().includes(q);
                        d.hidden = !hit;
                        d.open = !!q && hit;
                        if (hit) shown++;
                    });
                    g.hidden = shown === 0;
                    total += shown;
                });
                topics.hidden = !!q;
                none.style.display = total ? 'none' : 'flex';
            });
        })();
    </script>
</x-public-layout>
