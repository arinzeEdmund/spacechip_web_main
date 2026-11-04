<x-app-layout>
    <style>
        .vnp-flag{font-size:26px;line-height:1}
        .vnp-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:12px;margin-bottom:16px}
        .vnp-toolbar .ui-seg{flex:0 0 auto}
        .vnp-count{margin-left:auto;font-size:13px;color:var(--muted);white-space:nowrap}
        .vnp-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fill,minmax(280px,1fr))}
        .vnp-card{position:relative;display:flex;flex-direction:column;gap:12px;padding:16px;border-radius:18px;border:1px solid var(--border);background:var(--surface);color:var(--text);text-decoration:none;box-shadow:var(--shadow-1);transition:border-color .18s ease,transform .18s ease,box-shadow .18s ease}
        .vnp-card:hover{border-color:rgb(var(--accent-rgb) / .45);box-shadow:var(--shadow-3);transform:translateY(-2px)}
        .vnp-card:focus-visible{outline:2px solid var(--accent-strong);outline-offset:2px}
        .vnp-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
        .vnp-num{font-size:16.5px;font-weight:650;letter-spacing:.01em;font-variant-numeric:tabular-nums;color:var(--text)}
        .vnp-loc{display:flex;align-items:center;gap:6px;margin-top:4px;min-width:0;font-size:13px;color:var(--muted)}
        .vnp-loc svg{flex:0 0 auto;width:14px;height:14px;color:var(--faint)}
        .vnp-loc span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .vnp-price{flex:0 0 auto;text-align:right;font-size:16px;font-weight:700;color:var(--text);font-variant-numeric:tabular-nums}
        .vnp-price small{display:block;font-size:11.5px;font-weight:500;color:var(--muted)}
        .vnp-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:12px;border-top:1px dashed var(--border)}
        .vnp-chips{display:flex;flex-wrap:wrap;gap:6px}
        .vnp-go{display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:600;color:var(--accent-text)}
        .vnp-go svg{width:15px;height:15px;transition:transform .18s ease}
        .vnp-card:hover .vnp-go svg{transform:translateX(3px)}
        .vnp-skel{height:132px;border-radius:18px}
        .vnp-more{display:flex;justify-content:center;margin-top:20px}
        .vnp-status{margin:0 0 12px;font-size:13px;color:var(--muted)}
        .vnp-status:empty{display:none}
        @media(max-width:640px){.vnp-toolbar .ui-search{flex-basis:100%}.vnp-grid{grid-template-columns:1fr}}
    </style>

    @php
        $code = strtoupper((string) ($countryIso ?? ''));
        $flag = '☎️';
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            $flag = mb_chr(ord($code[0]) + 127397).mb_chr(ord($code[1]) + 127397);
        }
        $price = (string) ($startingFrom ?? (strtoupper((string) ($product->currency ?? 'USD')).' '.number_format(((int) ($product->monthly_amount_minor ?? 0)) / 100, 2)));
        $chev = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
    @endphp

    <div class="pg">
        <nav class="pg-crumbs" aria-label="Breadcrumb">
            <a href="{{ $indexUrl ?? route('dashboard.virtual.index') }}">Virtual numbers</a>
            {!! $chev !!}
            <span>{{ $countryName }}</span>
        </nav>

        <div class="pg-head">
            <div>
                <h1 class="pg-title"><span class="ui-icon-tile ui-icon-tile--lg ui-icon-tile--neutral vnp-flag" aria-hidden="true">{{ $flag }}</span>{{ $countryName }}</h1>
                <p class="pg-sub">Local numbers from <strong style="color:var(--text);font-weight:600">{{ $price }}/month</strong>. Pick one below — it's active the moment you subscribe.</p>
            </div>
            <div class="pg-actions">
                <a class="ui-btn ui-btn--secondary" href="{{ $myUrl ?? route('dashboard.virtual.my') }}">My numbers</a>
            </div>
        </div>

        <div class="ui-card vnp-toolbar">
            <label class="ui-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <span class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Search numbers</span>
                <input id="q" class="ui-input" type="search" placeholder="Search number, city or region" autocomplete="off">
            </label>
            <div class="ui-seg" id="typeSeg" role="group" aria-label="Number type">
                <button type="button" class="is-active" data-type="">All</button>
            </div>
            <span class="vnp-count" id="count" aria-live="polite"></span>
            <button class="ui-icon-btn" id="refreshBtn" type="button" aria-label="Refresh numbers" title="Refresh">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36L21 8"/><path d="M21 3v5h-5"/></svg>
            </button>
        </div>

        <p id="status" class="vnp-status"></p>
        <div id="results" class="vnp-grid"></div>
        <div class="vnp-more">
            <button id="loadMoreBtn" class="ui-btn ui-btn--secondary" type="button" hidden>Load more numbers</button>
        </div>
    </div>

    <script type="application/json" id="virtualNumbersCountryConfig">{!! json_encode([
        'productId' => (int) $product->id,
        'checkoutBase' => (string) ($checkoutBaseUrl ?? route('dashboard.virtual.checkout')),
    ]) !!}</script>
    <script src="{{ asset('js/virtual-numbers-country.js') }}?v={{ @filemtime(public_path('js/virtual-numbers-country.js')) }}"></script>
</x-app-layout>
