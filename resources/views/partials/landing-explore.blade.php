{{--
    Landing page "explore" sections shown after Popular Countries: why spacechip, an interactive trip-data planner,
    a roaming-savings calculator, how-it-works tabs, a device checker, social numbers, FAQ and a final CTA.
    Self-contained (scoped .lx-* styles + one small script). No invented reviews or user counts: only claims the
    product actually backs up. Usage figures in the planner are rough, clearly labelled estimates.
--}}
@php
    $plansUrl = auth()->check() ? route('dashboard', ['tab' => 'countries']) : '/allassets?tab=countries';
    $socialUrl = auth()->check() ? route('dashboard', ['tab' => 'social']) : route('register');
    $faqs = [
        ['What is an eSIM?', 'An eSIM is a digital SIM built into your phone. You install it by scanning a QR code or tapping a link, so there is no plastic card to swap and your home SIM stays in the phone.'],
        ['Will I keep my WhatsApp number and my normal SIM?', 'Yes. Your eSIM adds mobile data on top of your normal line. Calls and texts to your usual number keep working on your home SIM when you leave data roaming off.'],
        ['When does my plan start?', 'Install it any time before you travel. The validity period starts when the eSIM first connects to a network in your destination, so you can set it up at home.'],
        ['Does my phone support eSIM?', 'Most phones from about 2018 onward do, and they must be network-unlocked. Use the device checker above, then confirm in your phone settings.'],
        ['How do virtual and social numbers work?', 'Virtual numbers give you a second number for calls and texts. Social numbers receive the verification code for apps like WhatsApp or Telegram, either once or as a monthly rental that keeps receiving messages. Use them only where the app and local law allow it.'],
        ['What if no code arrives or my eSIM fails to install?', 'One-time verification numbers are refunded to your wallet automatically if no SMS arrives. If an eSIM cannot be delivered, the order is refunded. See the refund policy for the rules on activated plans.'],
        ['How can I pay?', 'From your wallet balance, by card, or with crypto, depending on what is enabled for your region at checkout.'],
    ];
@endphp

<style>
    .lx{--lx-card:rgb(var(--surface-rgb) / .78);--lx-line:rgb(var(--ink-rgb) / .12);--lx-mute:rgb(var(--ink-rgb) / .64)}
    .lx section{padding:56px 0 0}
    .lx h2{margin:0;font-size:28px;line-height:1.12;font-weight:800;letter-spacing:-.025em;color:var(--text)}
    .lx .lx-eyebrow{display:inline-block;margin-bottom:10px;padding:5px 11px;border-radius:9999px;background:var(--accent-soft);color:var(--accent-text);font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
    .lx .lx-sub{margin:10px 0 0;max-width:60ch;font-size:15px;line-height:1.6;color:var(--lx-mute)}
    .lx .lx-head{margin-bottom:22px}
    .lx .lx-head.center{text-align:center}.lx .lx-head.center .lx-sub{margin-inline:auto}
    .lx .lx-card{background:var(--lx-card);border:1px solid var(--lx-line);border-radius:22px;box-shadow:0 10px 30px rgba(0,0,0,.05)}
    .lx .lx-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:9999px;background:var(--accent-fill);color:#fff;font-size:14px;font-weight:700;border:0;cursor:pointer;box-shadow:0 14px 35px rgba(0,0,0,.14);transition:transform .15s}
    .lx .lx-btn:hover{transform:translateY(-1px)}
    .lx .lx-btn.ghost{background:var(--surface);color:var(--accent-text);border:1px solid var(--lx-line);box-shadow:none}
    .lx .lx-reveal{opacity:0;transform:translateY(14px);transition:opacity .6s ease,transform .6s ease}
    .lx .lx-reveal.in{opacity:1;transform:none}
    @media(prefers-reduced-motion:reduce){.lx .lx-reveal{opacity:1;transform:none;transition:none}}

    /* trust strip */
    .lx-trust{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:18px}
    .lx-trust div{display:flex;align-items:center;gap:12px;font-size:14px;font-weight:650;color:var(--text)}
    .lx-trust svg{flex:0 0 auto;color:var(--accent-text)}
    .lx-trust small{display:block;font-weight:500;color:var(--lx-mute);font-size:12px}

    /* feature grid */
    .lx-feats{display:grid;grid-template-columns:1fr;gap:14px}
    .lx-feat{padding:22px}
    .lx-ico{height:44px;width:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:var(--accent-soft);color:var(--accent-text);margin-bottom:14px}
    .lx-feat h3{margin:0 0 6px;font-size:16px;font-weight:800;color:var(--text)}
    .lx-feat p{margin:0;font-size:14px;line-height:1.55;color:var(--lx-mute)}

    /* planner */
    .lx-plan{display:grid;grid-template-columns:1fr;gap:0;overflow:hidden}
    .lx-plan-in{padding:26px}
    .lx-plan-out{padding:26px;background:linear-gradient(160deg,rgb(var(--accent-rgb) / .16),rgb(var(--accent-rgb) / .04));border-top:1px solid var(--lx-line)}
    .lx-field{margin-top:18px}.lx-field:first-child{margin-top:0}
    .lx-field label{display:flex;justify-content:space-between;font-size:13px;font-weight:700;color:var(--text);margin-bottom:8px}
    .lx-field label b{color:var(--accent-text)}
    .lx input[type=range]{width:100%;accent-color:var(--accent-strong,#d86c54)}
    .lx-acts{display:grid;grid-template-columns:1fr;gap:10px}
    .lx-act{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:14px;border:1px solid var(--lx-line);background:var(--surface)}
    .lx-act span.n{flex:1;font-size:13px;font-weight:650;color:var(--text)}
    .lx-act span.n small{display:block;font-weight:500;color:var(--lx-mute);font-size:11px}
    .lx-step{display:inline-flex;align-items:center;border:1px solid var(--lx-line);border-radius:9999px;overflow:hidden}
    .lx-step button{height:30px;width:30px;border:0;background:transparent;color:var(--accent-text);font-size:16px;font-weight:800;cursor:pointer}
    .lx-step button:hover{background:var(--accent-soft)}
    .lx-step output{min-width:44px;text-align:center;font-size:12px;font-weight:800;color:var(--text)}
    .lx-big{font-size:46px;line-height:1;font-weight:850;letter-spacing:-.04em;color:var(--text)}
    .lx-big small{font-size:18px;font-weight:700;color:var(--lx-mute);margin-left:4px}
    .lx-bar{margin:16px 0 8px;display:flex;height:12px;border-radius:9999px;overflow:hidden;background:rgb(var(--ink-rgb) / .08)}
    .lx-bar i{display:block;height:100%;transition:width .35s ease}
    .lx-leg{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px;color:var(--lx-mute)}
    .lx-leg i{display:inline-block;height:8px;width:8px;border-radius:9999px;margin-right:6px}
    .lx-note{margin-top:14px;font-size:11px;line-height:1.5;color:var(--lx-mute)}
    .lx-sizes{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}
    .lx-size{padding:7px 13px;border-radius:9999px;border:1px solid var(--lx-line);font-size:13px;font-weight:750;color:var(--lx-mute);background:var(--surface);transition:all .2s}
    .lx-size.on{background:var(--accent-fill);border-color:transparent;color:#fff;transform:scale(1.06)}

    /* savings */
    .lx-save{display:grid;grid-template-columns:1fr;gap:16px}
    .lx-save .lx-card{padding:24px}
    .lx-cmp{display:grid;gap:12px;margin-top:6px}
    .lx-cmp-row{display:grid;grid-template-columns:96px 1fr 76px;gap:10px;align-items:center;font-size:13px;font-weight:700;color:var(--text)}
    .lx-cmp-track{height:14px;border-radius:9999px;background:rgb(var(--ink-rgb) / .08);overflow:hidden}
    .lx-cmp-track i{display:block;height:100%;border-radius:9999px;transition:width .4s ease}
    .lx-num{width:100%;padding:11px 14px;border-radius:14px;border:1px solid var(--lx-line);background:var(--surface);color:var(--text);font-size:15px;font-weight:700}
    .lx-sv{font-size:34px;font-weight:850;letter-spacing:-.03em;color:var(--accent-text)}

    /* how it works */
    .lx-tabs{display:inline-flex;flex-wrap:wrap;gap:6px;padding:5px;border-radius:9999px;background:rgb(var(--ink-rgb) / .06);margin-bottom:20px}
    .lx-tab{padding:9px 16px;border-radius:9999px;border:0;background:transparent;font-size:13px;font-weight:750;color:var(--lx-mute);cursor:pointer}
    .lx-tab.on{background:var(--surface);color:var(--accent-text);box-shadow:0 4px 14px rgba(0,0,0,.08)}
    .lx-steps{display:grid;grid-template-columns:1fr;gap:14px;counter-reset:s}
    .lx-stepcard{padding:22px;position:relative;counter-increment:s}
    .lx-stepcard::before{content:counter(s);display:flex;align-items:center;justify-content:center;height:34px;width:34px;border-radius:9999px;background:var(--accent-fill);color:#fff;font-weight:800;margin-bottom:12px}
    .lx-stepcard h3{margin:0 0 6px;font-size:16px;font-weight:800;color:var(--text)}
    .lx-stepcard p{margin:0;font-size:14px;line-height:1.55;color:var(--lx-mute)}
    .lx-panel[hidden]{display:none}

    /* device checker */
    .lx-dev{display:grid;grid-template-columns:1fr;gap:18px;padding:26px;align-items:center}
    .lx-dev-res{min-height:76px;padding:14px 16px;border-radius:16px;border:1px dashed var(--lx-line);font-size:14px;line-height:1.5;color:var(--lx-mute)}
    .lx-dev-res.ok{border-style:solid;border-color:rgb(48 209 88 / .5);background:rgb(48 209 88 / .09);color:var(--text)}
    .lx-dev-res.no{border-style:solid;border-color:rgb(255 69 58 / .45);background:rgb(255 69 58 / .08);color:var(--text)}
    .lx-dev-res.maybe{border-style:solid;border-color:rgb(255 159 10 / .5);background:rgb(255 159 10 / .09);color:var(--text)}

    /* numbers */
    .lx-nums{display:grid;grid-template-columns:1fr;gap:16px}
    .lx-promo{padding:28px;background:linear-gradient(150deg,rgb(var(--accent-rgb) / .20),rgb(var(--accent-rgb) / .05))}
    .lx-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
    .lx-chip{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:9999px;background:var(--surface);border:1px solid var(--lx-line);font-size:13px;font-weight:700;color:var(--text)}
    .lx-kinds{display:grid;gap:12px}
    .lx-kind{padding:20px}
    .lx-kind h3{margin:0 0 4px;font-size:15px;font-weight:800;color:var(--text)}
    .lx-kind p{margin:0;font-size:13px;line-height:1.5;color:var(--lx-mute)}

    /* faq */
    .lx-faq{max-width:780px;margin:0 auto;display:grid;gap:10px}
    .lx-faq details{padding:0;overflow:hidden}
    .lx-faq summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:18px 20px;font-size:15px;font-weight:750;color:var(--text)}
    .lx-faq summary::-webkit-details-marker{display:none}
    .lx-faq summary::after{content:"+";font-size:22px;font-weight:600;color:var(--accent-text);transition:transform .2s}
    .lx-faq details[open] summary::after{transform:rotate(45deg)}
    .lx-faq details p{margin:0;padding:0 20px 18px;font-size:14px;line-height:1.65;color:var(--lx-mute)}

    /* final cta */
    .lx-cta{margin-top:56px;padding:34px 24px;text-align:center;background:linear-gradient(150deg,rgb(var(--accent-rgb) / .24),rgb(var(--accent-rgb) / .06))}
    .lx-cta h2{font-size:26px}
    .lx-cta .row{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
    .lx-end{height:48px}

    @media(min-width:640px){
        .lx-trust{grid-template-columns:repeat(4,1fr)}
        .lx-feats{grid-template-columns:repeat(2,1fr)}
        .lx-acts{grid-template-columns:repeat(2,1fr)}
    }
    @media(min-width:900px){
        .lx h2{font-size:34px}
        .lx-feats{grid-template-columns:repeat(3,1fr)}
        .lx-plan{grid-template-columns:1.15fr .85fr}
        .lx-plan-out{border-top:0;border-left:1px solid var(--lx-line)}
        .lx-save{grid-template-columns:1fr 1fr}
        .lx-steps{grid-template-columns:repeat(3,1fr)}
        .lx-dev{grid-template-columns:1fr 1fr;padding:32px}
        .lx-nums{grid-template-columns:1.2fr .8fr}
    }
    @media(max-width:859px){.lx .lx-card,.lx .lx-size,.lx .lx-chip{-webkit-backdrop-filter:none;backdrop-filter:none}.lx{--lx-card:var(--surface)}}
</style>

<div class="lx" id="explore">
    <div class="container">

        {{-- Trust strip: only things the product really does --}}
        <section style="padding-top:40px">
            <div class="lx-card lx-trust lx-reveal">
                <div><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg><span>Instant delivery<small>QR code in minutes</small></span></div>
                <div><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2 4 6v6c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V6l-8-4Z"/><path d="m9 12 2 2 4-4"/></svg><span>Auto-refund<small>If delivery or SMS fails</small></span></div>
                <div><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg><span>Global coverage<small>Countries and regions</small></span></div>
                <div><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20"/></svg><span>Flexible payment<small>Wallet, card or crypto</small></span></div>
            </div>
        </section>

        {{-- Why --}}
        <section>
            <div class="lx-head lx-reveal">
                <span class="lx-eyebrow">Why spacechip</span>
                <h2>Stay connected without the roaming bill</h2>
                <p class="lx-sub">Everything you need to be reachable and online abroad, from one wallet.</p>
            </div>
            <div class="lx-feats">
                @foreach ([
                    ['M12 20h.01M2 8.8a15 15 0 0 1 20 0M5 12.9a10 10 0 0 1 14 0M8.5 16.4a5 5 0 0 1 7 0', 'Data in 190+ places', 'Country, regional and global plans, so you pick coverage that matches your route.'],
                    ['M13 2 4 14h7l-1 8 9-12h-7l1-8Z', 'Install in minutes', 'Scan a QR code or tap a link. Set it up at home, and the plan starts when you connect abroad.'],
                    ['M3 7h18M3 12h18M3 17h18', 'Keep your own number', 'The eSIM adds data alongside your normal SIM, so your home number stays reachable.'],
                    ['M4 4h16v12H4zM8 20h8M12 16v4', 'One wallet for everything', 'Top up once and pay for eSIMs, virtual numbers and verification numbers.'],
                    ['M12 2 4 6v6c0 5 3.4 8.4 8 10 4.6-1.6 8-5 8-10V6l-8-4Z', 'Privacy first', 'A second number keeps your main one private. Admin tools are protected by two-step login.'],
                    ['M20 12a8 8 0 1 1-3-6.2M20 4v5h-5', 'Fair refunds', 'Automatic refunds when delivery fails or a one-time verification SMS never arrives.'],
                ] as [$path, $title, $copy])
                    <div class="lx-card lx-feat lx-reveal">
                        <div class="lx-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg></div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $copy }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Interactive trip data planner --}}
        <section id="planner">
            <div class="lx-head lx-reveal">
                <span class="lx-eyebrow">Trip data planner</span>
                <h2>How much data will your trip need?</h2>
                <p class="lx-sub">Tell us how long you are away and how you use your phone. We estimate the plan size so you do not overpay or run out.</p>
            </div>
            <div class="lx-card lx-plan lx-reveal">
                <div class="lx-plan-in">
                    <div class="lx-field">
                        <label for="lxDays">Trip length <b id="lxDaysOut">7 days</b></label>
                        <input type="range" id="lxDays" min="1" max="60" value="7">
                    </div>
                    <div class="lx-field">
                        <label>Hours a day on mobile data, per activity</label>
                        <div class="lx-acts" id="lxActs"></div>
                    </div>
                    <div class="lx-field">
                        <label for="lxPeople">Travellers sharing the hotspot <b id="lxPeopleOut">1</b></label>
                        <input type="range" id="lxPeople" min="1" max="6" value="1">
                    </div>
                </div>
                <div class="lx-plan-out" aria-live="polite">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--accent-text)">Estimated total</div>
                    <div class="lx-big" style="margin-top:8px"><span id="lxGb">0</span><small>GB</small></div>
                    <div class="lx-bar" id="lxBar"></div>
                    <div class="lx-leg" id="lxLeg"></div>
                    <div class="lx-sizes" id="lxSizes"></div>
                    <div style="font-size:14px;color:var(--text);font-weight:650" id="lxRec"></div>
                    <a class="lx-btn" id="lxGo" href="{{ $plansUrl }}" style="margin-top:16px">Find a plan</a>
                    <p class="lx-note">Rough estimates from typical usage (maps ~30&nbsp;MB/h, messaging ~5&nbsp;MB/h, social ~150&nbsp;MB/h, music ~70&nbsp;MB/h, video ~0.7–3&nbsp;GB/h, video calls ~0.5&nbsp;GB/h). Real usage varies, and a 20% buffer is added.</p>
                </div>
            </div>
        </section>

        {{-- Savings calculator --}}
        <section>
            <div class="lx-head lx-reveal">
                <span class="lx-eyebrow">Roaming vs eSIM</span>
                <h2>See what you could save</h2>
                <p class="lx-sub">Enter what your carrier charges for roaming. Figures below are your own inputs, not a quote.</p>
            </div>
            <div class="lx-save">
                <div class="lx-card lx-reveal">
                    <div class="lx-field"><label for="lxRoamDay">Carrier roaming pass per day (USD)</label><input class="lx-num" id="lxRoamDay" type="number" min="0" step="0.5" value="10"></div>
                    <div class="lx-field"><label for="lxRoamDays">Days abroad</label><input class="lx-num" id="lxRoamDays" type="number" min="1" max="365" value="7"></div>
                    <div class="lx-field"><label for="lxEsim">eSIM plan price you expect to pay (USD)</label><input class="lx-num" id="lxEsim" type="number" min="0" step="0.5" value="8"></div>
                    <p class="lx-note">Tip: plan prices on this page start from the amounts shown under Popular Countries.</p>
                </div>
                <div class="lx-card lx-reveal">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--accent-text)">You could save</div>
                    <div class="lx-sv" id="lxSave">$0</div>
                    <div class="lx-cmp">
                        <div class="lx-cmp-row"><span>Roaming</span><div class="lx-cmp-track"><i id="lxBarRoam" style="background:#ff453a;width:100%"></i></div><span id="lxTotRoam">$0</span></div>
                        <div class="lx-cmp-row"><span>eSIM</span><div class="lx-cmp-track"><i id="lxBarEsim" style="background:var(--accent-fill);width:30%"></i></div><span id="lxTotEsim">$0</span></div>
                    </div>
                    <a class="lx-btn ghost" href="{{ $plansUrl }}" style="margin-top:18px">Browse eSIM plans</a>
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section>
            <div class="lx-head lx-reveal">
                <span class="lx-eyebrow">How it works</span>
                <h2>Three steps, whichever product you pick</h2>
            </div>
            <div class="lx-tabs lx-reveal" role="tablist" aria-label="Product">
                <button class="lx-tab on" role="tab" aria-selected="true" data-tab="esim">Travel eSIM</button>
                <button class="lx-tab" role="tab" aria-selected="false" data-tab="virtual">Virtual number</button>
                <button class="lx-tab" role="tab" aria-selected="false" data-tab="social">Verification number</button>
            </div>
            @foreach ([
                'esim' => [['Choose a plan', 'Pick your destination and a data size. Prices are shown before you pay.'], ['Install the eSIM', 'Scan the QR code or tap the install link in your account. Takes about two minutes.'], ['Connect abroad', 'Turn on the eSIM line and data roaming for it when you land. Your plan period starts then.']],
                'virtual' => [['Pick a number', 'Choose a country and number for calls and texts.'], ['Subscribe from your wallet', 'Monthly billing, with reminders before it renews.'], ['Call and text in the app', 'Messages and voicemail show up in your account.']],
                'social' => [['Choose the app and country', 'Select WhatsApp, Telegram or another supported app, then a country.'], ['Get your number', 'One-time numbers wait for a single code. Monthly rentals keep receiving messages.'], ['Read the code', 'It appears in your account. One-time numbers are refunded automatically if no SMS arrives.']],
            ] as $key => $steps)
                <div class="lx-steps lx-panel" data-panel="{{ $key }}" @if ($key !== 'esim') hidden @endif>
                    @foreach ($steps as [$t, $c])
                        <div class="lx-card lx-stepcard"><h3>{{ $t }}</h3><p>{{ $c }}</p></div>
                    @endforeach
                </div>
            @endforeach
        </section>

        {{-- Device checker --}}
        <section>
            <div class="lx-card lx-dev lx-reveal">
                <div>
                    <span class="lx-eyebrow">Compatibility</span>
                    <h2 style="font-size:26px">Is your phone eSIM-ready?</h2>
                    <p class="lx-sub">Type your phone model. This is a quick guide only: confirm in Settings, and your phone must be network-unlocked.</p>
                </div>
                <div>
                    <input class="lx-num" id="lxDev" aria-label="Your phone model" type="text" placeholder="e.g. iPhone 13, Pixel 8, Galaxy S23" autocomplete="off" list="lxDevList">
                    <datalist id="lxDevList"><option value="iPhone 15"><option value="iPhone 14"><option value="iPhone 13"><option value="iPhone 12"><option value="iPhone 11"><option value="iPhone XR"><option value="iPhone SE (2022)"><option value="Pixel 8"><option value="Pixel 7"><option value="Galaxy S24"><option value="Galaxy S23"><option value="Galaxy S21"><option value="Galaxy Z Flip"></datalist>
                    <div class="lx-dev-res" id="lxDevRes" style="margin-top:12px">Start typing a model name.</div>
                </div>
            </div>
        </section>

        {{-- Numbers --}}
        <section>
            <div class="lx-head lx-reveal">
                <span class="lx-eyebrow">Phone numbers</span>
                <h2>A second number, on your terms</h2>
                <p class="lx-sub">Keep your main number private, or receive verification codes for the apps you use. Use them lawfully and within each app's rules.</p>
            </div>
            <div class="lx-nums">
                <div class="lx-card lx-promo lx-reveal">
                    <h3 style="margin:0;font-size:20px;font-weight:850;color:var(--text)">Verification numbers</h3>
                    <p class="lx-sub" style="margin-top:8px">One-time codes in seconds, or a monthly rental that keeps receiving messages.</p>
                    <div class="lx-chips">
                        @foreach (['WhatsApp', 'Telegram', 'Instagram', 'Facebook', 'Google', 'X (Twitter)', 'TikTok', 'Discord'] as $app)<span class="lx-chip">{{ $app }}</span>@endforeach
                    </div>
                    <a class="lx-btn" href="{{ $socialUrl }}" style="margin-top:20px">Get a number</a>
                </div>
                <div class="lx-kinds">
                    <div class="lx-card lx-kind lx-reveal"><h3>One-time</h3><p>Pay per code. Automatic refund to your wallet if no SMS arrives.</p></div>
                    <div class="lx-card lx-kind lx-reveal"><h3>Monthly rental</h3><p>Keep the same number and its inbox for repeat logins. Renews from your wallet, and you can stop auto-renew any time.</p></div>
                    <div class="lx-card lx-kind lx-reveal"><h3>Virtual calling number</h3><p>A second line for calls and texts in the app.</p></div>
                </div>
            </div>
        </section>

        {{-- FAQ --}}
        <section>
            <div class="lx-head center lx-reveal"><span class="lx-eyebrow">FAQ</span><h2>Questions, answered</h2></div>
            <div class="lx-faq">
                @foreach ($faqs as [$q, $a])
                    <details class="lx-card lx-reveal"><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
                @endforeach
            </div>
            <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        </section>

        {{-- Final CTA --}}
        <div class="lx-card lx-cta lx-reveal">
            <h2>Ready when you are</h2>
            <p class="lx-sub" style="margin-inline:auto">Find your destination, install in minutes and travel connected.</p>
            <div class="row">
                <a class="lx-btn" href="{{ $plansUrl }}">Browse plans</a>
                <a class="lx-btn ghost" href="#planner">Plan my data</a>
            </div>
        </div>
        <div class="lx-end"></div>
    </div>
</div>

<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };

    // Reveal on scroll
    var els = document.querySelectorAll('.lx .lx-reveal');
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
        }, { threshold: .12 });
        els.forEach(function (el) { io.observe(el); });
    } else { els.forEach(function (el) { el.classList.add('in'); }); }

    // ---- Trip data planner ----
    var acts = [
        { id: 'maps', n: 'Maps & navigation', mb: 30, c: '#34c759', h: 1 },
        { id: 'chat', n: 'Messaging', mb: 5, c: '#0a84ff', h: 2 },
        { id: 'social', n: 'Social media', mb: 150, c: '#ff9f0a', h: 1 },
        { id: 'music', n: 'Music', mb: 70, c: '#bf5af2', h: 0 },
        { id: 'video', n: 'Video (SD/HD)', mb: 1000, c: '#ff453a', h: 0, s: '~1 GB/h' },
        { id: 'call', n: 'Video calls', mb: 500, c: '#64d2ff', h: 0 }
    ];
    var sizes = [1, 3, 5, 10, 20, 50, 100];
    var actsEl = $('lxActs');
    if (actsEl) {
        acts.forEach(function (a) {
            var d = document.createElement('div'); d.className = 'lx-act';
            d.innerHTML = '<span class="n">' + a.n + '<small>' + (a.s || '~' + a.mb + ' MB/h') + '</small></span><span class="lx-step"><button type="button" aria-label="Less ' + a.n + '">−</button><output id="lxA_' + a.id + '">' + a.h + 'h</output><button type="button" aria-label="More ' + a.n + '">+</button></span>';
            var out = d.querySelector('output'), b = d.querySelectorAll('button');
            b[0].onclick = function () { a.h = Math.max(0, +(a.h - .5).toFixed(1)); out.textContent = a.h + 'h'; calc(); };
            b[1].onclick = function () { a.h = Math.min(12, +(a.h + .5).toFixed(1)); out.textContent = a.h + 'h'; calc(); };
            actsEl.appendChild(d);
        });
        function fmt(n) { return n >= 10 ? Math.round(n) : (Math.round(n * 10) / 10); }
        function calc() {
            var days = +$('lxDays').value, people = +$('lxPeople').value;
            $('lxDaysOut').textContent = days + (days === 1 ? ' day' : ' days');
            $('lxPeopleOut').textContent = people;
            var perDay = acts.map(function (a) { return a.mb * a.h * people; });
            var totalMb = perDay.reduce(function (s, v) { return s + v; }, 0) * days * 1.2;
            var gb = totalMb / 1000;
            $('lxGb').textContent = fmt(gb);
            var bar = $('lxBar'), leg = $('lxLeg'); bar.innerHTML = ''; leg.innerHTML = '';
            var sum = perDay.reduce(function (s, v) { return s + v; }, 0) || 1;
            acts.forEach(function (a, i) {
                if (!perDay[i]) return;
                var i1 = document.createElement('i'); i1.style.width = (perDay[i] / sum * 100) + '%'; i1.style.background = a.c; i1.title = a.n; bar.appendChild(i1);
                var s = document.createElement('span'); s.innerHTML = '<i style="background:' + a.c + '"></i>' + a.n; leg.appendChild(s);
            });
            var rec = sizes.find(function (s) { return s >= gb; });
            var sz = $('lxSizes'); sz.innerHTML = '';
            sizes.forEach(function (s) { var p = document.createElement('span'); p.className = 'lx-size' + (s === rec ? ' on' : ''); p.textContent = s + ' GB'; sz.appendChild(p); });
            $('lxRec').textContent = gb <= 0 ? 'Add some activities to get a recommendation.' : rec ? 'Look for a ' + rec + ' GB plan' + (gb > 20 ? ', or a larger or unlimited option.' : '.') : 'That is a lot of data. Look at unlimited or the largest plans.';
        }
        $('lxDays').oninput = calc; $('lxPeople').oninput = calc; calc();
    }

    // ---- Savings calculator ----
    function money(n) { return '$' + (Math.round(n * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); }
    function save() {
        var day = Math.max(0, +$('lxRoamDay').value || 0), days = Math.max(1, +$('lxRoamDays').value || 1), esim = Math.max(0, +$('lxEsim').value || 0);
        var roam = day * days, diff = roam - esim;
        $('lxTotRoam').textContent = money(roam); $('lxTotEsim').textContent = money(esim);
        $('lxSave').textContent = diff > 0 ? money(diff) : '$0';
        var max = Math.max(roam, esim, 1);
        $('lxBarRoam').style.width = (roam / max * 100) + '%'; $('lxBarEsim').style.width = (esim / max * 100) + '%';
    }
    ['lxRoamDay', 'lxRoamDays', 'lxEsim'].forEach(function (id) { if ($(id)) $(id).addEventListener('input', save); });
    if ($('lxSave')) save();

    // ---- How it works tabs ----
    document.querySelectorAll('.lx-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.lx-tab').forEach(function (x) { x.classList.toggle('on', x === t); x.setAttribute('aria-selected', x === t); });
            document.querySelectorAll('.lx-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== t.getAttribute('data-tab'); });
        });
    });

    // ---- Device checker (guide only) ----
    var dev = $('lxDev');
    if (dev) dev.addEventListener('input', function () {
        var v = dev.value.toLowerCase().replace(/\s+/g, ' ').trim(), r = $('lxDevRes'), cls = '', msg = 'Start typing a model name.';
        var m;
        if (v.length >= 3) {
            if (/iphone/.test(v)) {
                if ((m = v.match(/iphone (\d+)/))) { cls = +m[1] >= 11 ? 'ok' : 'no'; msg = cls === 'ok' ? 'iPhone ' + m[1] + ' supports eSIM. 🎉 Make sure it is network-unlocked.' : 'iPhone ' + m[1] + ' does not support eSIM.'; }
                else if (/\bx[rs]\b|xs max/.test(v)) { cls = 'ok'; msg = 'iPhone XR and XS support eSIM (not sold with eSIM in mainland China). Make sure it is unlocked.'; }
                else if (/se/.test(v)) { cls = /2020|2022|\b2\b|\b3\b|2nd|3rd/.test(v) ? 'ok' : 'maybe'; msg = cls === 'ok' ? 'iPhone SE (2020 and later) supports eSIM.' : 'iPhone SE: the 2020 and 2022 models support eSIM; the 2016 model does not.'; }
                else { cls = 'maybe'; msg = 'Add the model number, for example "iPhone 13".'; }
            } else if (/pixel/.test(v)) {
                if ((m = v.match(/pixel (\d+)/))) { cls = +m[1] >= 3 ? 'ok' : 'no'; msg = cls === 'ok' ? 'Pixel ' + m[1] + ' supports eSIM (a few regional Pixel 3 variants do not). Check Settings.' : 'Pixel ' + m[1] + ' does not support eSIM.'; }
                else { cls = 'maybe'; msg = 'Add the model number, for example "Pixel 8".'; }
            } else if (/galaxy|samsung|\bs\d{2}\b|flip|fold/.test(v)) {
                if ((m = v.match(/\bs(\d{2})\b/))) { cls = +m[1] >= 20 ? 'ok' : 'no'; msg = cls === 'ok' ? 'Galaxy S' + m[1] + ' supports eSIM in most regions (some regional models do not).' : 'Galaxy S' + m[1] + ' does not support eSIM.'; }
                else if (/flip|fold/.test(v)) { cls = 'ok'; msg = 'Recent Galaxy Z Flip and Fold models support eSIM. Check your exact model and region.'; }
                else { cls = 'maybe'; msg = 'Add the model, for example "Galaxy S23".'; }
            } else { cls = 'maybe'; msg = 'We could not match that. On most phones, look for “Add eSIM” or an EID number in Settings → About phone / Cellular.'; }
        }
        r.className = 'lx-dev-res' + (cls ? ' ' + cls : ''); r.textContent = msg;
    });
})();
</script>
