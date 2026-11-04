<x-app-layout>
    <style>
        .vn{--coral:var(--accent);--teal:var(--accent-text);--ink:var(--text);--muted:rgb(var(--ink-rgb) / .58);--line:rgb(var(--ink-rgb) / .08);
            max-width:1180px;margin:0 auto;padding:36px 20px 80px;color:var(--ink);font-family:"Instrument Sans",ui-sans-serif,system-ui,-apple-system,sans-serif}
        .vn .tab{font-variant-numeric:tabular-nums}

        /* Hero */
        .vn-hero{position:relative;overflow:hidden;border-radius:28px;padding:36px;background:rgb(var(--surface-rgb) / .72);
            border:1px solid rgb(var(--surface-rgb) / .8);box-shadow:0 1px 2px rgba(0,0,0,.04),0 24px 60px -28px rgba(0,0,0,.25);backdrop-filter:blur(18px)}
        .vn-hero::before{content:"";position:absolute;inset:-40% -10% auto auto;width:520px;height:520px;border-radius:50%;
            background:radial-gradient(closest-side,rgb(var(--accent-rgb) / .22),transparent);pointer-events:none}
        .vn-hero::after{content:"";position:absolute;left:-120px;bottom:-200px;width:460px;height:460px;border-radius:50%;
            background:radial-gradient(closest-side,rgb(var(--ink-rgb) / .16),transparent);pointer-events:none}
        .vn-hero-grid{position:relative;display:grid;gap:28px;align-items:center}
        @media(min-width:900px){.vn-hero-grid{grid-template-columns:1.35fr 1fr}}
        .vn-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:999px;font-size:12.5px;font-weight:500;
            color:var(--teal);background:rgb(var(--ink-rgb) / .07);border:1px solid rgb(var(--ink-rgb) / .10)}
        .vn-eyebrow i{width:6px;height:6px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.18)}
        .vn-title{margin-top:16px;font-size:clamp(28px,3.4vw,40px);line-height:1.08;letter-spacing:-.025em;font-weight:600}
        .vn-title span{background:linear-gradient(100deg,var(--coral),var(--teal));-webkit-background-clip:text;background-clip:text;color:transparent}
        .vn-sub{margin-top:12px;max-width:520px;font-size:16px;line-height:1.55;color:var(--muted)}
        .vn-actions{margin-top:22px;display:flex;flex-wrap:wrap;gap:10px}
        .vn-btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 18px;border-radius:14px;font-size:14.5px;font-weight:500;text-decoration:none;transition:.18s}
        .vn-btn-primary{color:#fff;background:linear-gradient(135deg,var(--coral),var(--teal));box-shadow:0 10px 24px -10px rgb(var(--accent-rgb) / .75)}
        .vn-btn-primary:hover{filter:brightness(1.06);transform:translateY(-1px)}
        .vn-btn-ghost{color:var(--ink);background:rgb(var(--surface-rgb) / .85);border:1px solid var(--line)}
        .vn-btn-ghost:hover{background:var(--surface)}
        .vn-features{display:grid;gap:10px}
        .vn-feature{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;border-radius:18px;background:rgb(var(--surface-rgb) / .7);border:1px solid var(--line)}
        .vn-feature-icon{flex:none;display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:rgb(var(--accent-rgb) / .10);color:var(--coral)}
        .vn-feature:nth-child(2) .vn-feature-icon{background:rgb(var(--ink-rgb) / .09);color:var(--teal)}
        .vn-feature:nth-child(3) .vn-feature-icon{background:rgba(59,130,246,.09);color:#2563eb}
        .vn-feature b{display:block;font-size:14.5px;font-weight:600}
        .vn-feature span{display:block;margin-top:2px;font-size:13px;color:var(--muted)}

        /* Steps */
        .vn-steps{margin-top:18px;display:grid;gap:10px}
        @media(min-width:760px){.vn-steps{grid-template-columns:repeat(3,1fr)}}
        .vn-step{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:16px;background:rgb(var(--surface-rgb) / .55);border:1px solid var(--line);font-size:13.5px;color:var(--muted)}
        .vn-step em{font-style:normal;flex:none;display:grid;place-items:center;width:26px;height:26px;border-radius:50%;font-size:12.5px;font-weight:600;color:var(--on-accent);background:var(--accent-fill);box-shadow:0 0 14px -4px var(--accent)}
        .vn-step strong{color:var(--ink);font-weight:600}

        /* Toolbar */
        .vn-toolbar{position:sticky;top:12px;z-index:5;margin-top:34px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;
            padding:10px;border-radius:20px;background:rgb(var(--surface-rgb) / .82);border:1px solid var(--line);backdrop-filter:blur(16px);box-shadow:0 12px 30px -20px rgba(0,0,0,.3)}
        .vn-search{position:relative;flex:1 1 320px;max-width:460px}
        .vn-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:rgb(var(--ink-rgb) / .4)}
        .vn-search input{width:100%;height:44px;padding:0 40px 0 42px;border-radius:14px;border:1px solid var(--line);background:var(--surface);font-size:14.5px;color:var(--ink);outline:none;transition:.15s}
        .vn-search input:focus{border-color:rgb(var(--accent-rgb) / .45);box-shadow:0 0 0 4px rgb(var(--accent-rgb) / .12)}
        .vn-clear{position:absolute;right:8px;top:50%;transform:translateY(-50%);display:none;width:28px;height:28px;border-radius:9px;border:0;background:rgb(var(--ink-rgb) / .06);color:var(--muted);cursor:pointer}
        .vn-seg{display:inline-flex;padding:4px;border-radius:13px;background:rgb(var(--ink-rgb) / .05)}
        .vn-seg button{height:36px;padding:0 14px;border:0;border-radius:10px;background:transparent;font-size:13.5px;font-weight:500;color:var(--muted);cursor:pointer;transition:.15s}
        .vn-seg button[aria-pressed=true]{background:var(--surface);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.12)}
        .vn-chips{margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
        .vn-chips small{font-size:12.5px;color:var(--muted);margin-right:4px}
        .vn-chip{display:inline-flex;align-items:center;gap:7px;height:34px;padding:0 12px;border-radius:999px;border:1px solid var(--line);background:rgb(var(--surface-rgb) / .75);
            font-size:13px;color:var(--ink);cursor:pointer;transition:.15s}
        .vn-chip:hover{border-color:rgb(var(--accent-rgb) / .35);background:var(--surface)}
        .vn-chip img{width:18px;height:13px;border-radius:3px;object-fit:cover}
        .vn-meta{margin-top:22px;display:flex;justify-content:space-between;align-items:baseline;gap:12px}
        .vn-meta h2{font-size:18px;font-weight:600;letter-spacing:-.01em}
        .vn-meta span{font-size:13px;color:var(--muted)}

        /* Grid & cards */
        .vn-grid{margin-top:14px;display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(250px,1fr))}
        .vn-card{position:relative;display:flex;flex-direction:column;gap:18px;padding:18px;border-radius:20px;text-decoration:none;color:inherit;
            background:rgb(var(--surface-rgb) / .82);border:1px solid var(--line);box-shadow:0 1px 2px rgba(0,0,0,.03);transition:transform .18s,box-shadow .18s,border-color .18s}
        .vn-card:hover{transform:translateY(-2px);border-color:rgb(var(--accent-rgb) / .35);box-shadow:0 18px 36px -22px rgba(0,0,0,.35)}
        .vn-card:focus-visible{outline:none;box-shadow:0 0 0 4px rgb(var(--accent-rgb) / .25)}
        .vn-card-top{display:flex;align-items:center;gap:12px}
        .vn-flag{flex:none;width:44px;height:44px;border-radius:14px;display:grid;place-items:center;overflow:hidden;background:var(--surface-2);border:1px solid var(--line);font-size:22px}
        .vn-flag img{width:100%;height:100%;object-fit:cover}
        .vn-name{min-width:0}
        .vn-name b{display:block;font-size:15px;font-weight:600;letter-spacing:-.005em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .vn-name span{display:block;margin-top:1px;font-size:12.5px;color:var(--muted)}
        .vn-card-bottom{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;padding-top:14px;border-top:1px dashed var(--line)}
        .vn-price small{display:block;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
        .vn-price b{font-size:20px;font-weight:600;letter-spacing:-.02em}
        .vn-price i{font-style:normal;font-size:13px;color:var(--muted);margin-left:2px}
        .vn-go{display:grid;place-items:center;width:36px;height:36px;border-radius:12px;background:rgb(var(--ink-rgb) / .05);color:var(--ink);transition:.18s}
        .vn-card:hover .vn-go{background:linear-gradient(135deg,var(--coral),var(--teal));color:#fff;transform:translateX(2px)}
        .vn-caps{display:flex;gap:6px}
        .vn-caps span{font-size:11.5px;font-weight:500;padding:3px 8px;border-radius:999px;color:var(--teal);background:rgb(var(--ink-rgb) / .07)}

        .vn-empty{grid-column:1/-1;padding:56px 20px;text-align:center;border-radius:22px;border:1px dashed rgb(var(--ink-rgb) / .14);color:var(--muted)}
        .vn-empty b{display:block;color:var(--ink);font-size:16px;font-weight:600;margin-bottom:6px}
        .vn-more{margin-top:22px;display:flex;justify-content:center}
        .vn-more button{height:44px;padding:0 22px;border-radius:14px;border:1px solid var(--line);background:var(--surface);font-size:14px;font-weight:500;color:var(--ink);cursor:pointer}
        .vn-more button:disabled{opacity:.5;cursor:default}

        .vn-skel{height:146px;border-radius:20px;background:linear-gradient(90deg,var(--skeleton-base) 0%,var(--skeleton-shine) 40%,var(--skeleton-base) 80%);background-size:200% 100%;animation:vnskel 1.1s ease-in-out infinite}
        @keyframes vnskel{0%{background-position:150% 0}100%{background-position:-50% 0}}
        @media (prefers-reduced-motion:reduce){.vn-skel{animation:none}.vn-card,.vn-go{transition:none}}

    </style>

    <div class="vn">
        <section class="vn-hero" aria-labelledby="vnTitle">
            <div class="vn-hero-grid">
                <div>
                    <span class="vn-eyebrow"><i></i>Virtual numbers · SMS &amp; voice</span>
                    <h1 id="vnTitle" class="vn-title">A real local number,<br><span>in minutes.</span></h1>
                    <p class="vn-sub">Pick a country, choose the exact number you like and keep it month to month. Receive texts and calls anywhere, forwarded to your inbox or phone.</p>
                    <div class="vn-actions">
                        <a class="vn-btn vn-btn-primary" href="#countries">Browse countries
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                        </a>
                        <a class="vn-btn vn-btn-ghost" href="{{ $myUrl ?? route('dashboard.virtual.my') }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>
                            My numbers
                        </a>
                    </div>
                </div>
                <div class="vn-features">
                    <div class="vn-feature">
                        <div class="vn-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                        <div><b>Texts &amp; calls, anywhere</b><span>Read SMS and hear voicemail in the app or on the web.</span></div>
                    </div>
                    <div class="vn-feature">
                        <div class="vn-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 17 5-5-5-5"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg></div>
                        <div><b>Smart forwarding</b><span>Send every message to your email or another phone.</span></div>
                    </div>
                    <div class="vn-feature">
                        <div class="vn-feature-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></div>
                        <div><b>Monthly, cancel anytime</b><span>Pay by card or Reserve wallet. No contracts.</span></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="vn-steps" aria-label="How it works">
            <div class="vn-step"><em>1</em><span><strong>Choose a country</strong> from the list below</span></div>
            <div class="vn-step"><em>2</em><span><strong>Pick your number</strong> from live availability</span></div>
            <div class="vn-step"><em>3</em><span><strong>Subscribe</strong> and it’s active instantly</span></div>
        </div>

        <div id="countries" class="vn-toolbar" role="search">
            <label class="vn-search">
                <span class="sr-only">Search countries</span>
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input id="q" type="search" placeholder="Search countries…" autocomplete="off">
                <button id="clearQ" class="vn-clear" type="button" aria-label="Clear search">✕</button>
            </label>
            <div class="vn-seg" role="group" aria-label="Sort countries">
                <button type="button" data-sort="name" aria-pressed="true">A–Z</button>
                <button type="button" data-sort="price" aria-pressed="false">Lowest price</button>
            </div>
        </div>

        <div class="vn-chips" aria-label="Popular countries">
            <small>Popular</small>
            @foreach (['US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia', 'DE' => 'Germany', 'NL' => 'Netherlands'] as $code => $name)
                <button type="button" class="vn-chip" data-chip="{{ $name }}"><img src="https://flagcdn.com/w40/{{ strtolower($code) }}.png" alt="" loading="lazy">{{ $name }}</button>
            @endforeach
        </div>

        <div class="vn-meta">
            <h2>Available countries</h2>
            <span id="status" class="tab" aria-live="polite"></span>
        </div>
        <div id="grid" class="vn-grid"></div>
        <div class="vn-more"><button id="loadMoreBtn" type="button" hidden>Show more countries</button></div>
    </div>

    <script type="application/json" id="virtualNumbersIndexConfig">{!! json_encode([
        'apiBase' => (string) ($countriesApiBase ?? '/api/virtual-numbers/countries?context=dashboard'),
    ]) !!}</script>
    <script src="{{ asset('js/virtual-numbers-index.js') }}?v={{ @filemtime(public_path('js/virtual-numbers-index.js')) }}"></script>
</x-app-layout>
