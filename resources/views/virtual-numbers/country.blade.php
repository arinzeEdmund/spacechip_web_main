<x-app-layout>
    <style>
        :root{--primary:#f27457;--secondary:#145454}
        .wrap{max-width:1120px;margin:0 auto;padding:34px 16px 64px}
        .head{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:16px}
        .title{font-weight:950;letter-spacing:-.02em;color:#0b1a1a;font-size:28px}
        .sub{color:rgba(15,31,31,.62);font-weight:650;margin-top:6px}
        .glass{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);border-radius:22px}
        .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        .btn{padding:10px 14px;border-radius:14px;font-weight:850;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.65);color:rgba(15,31,31,.72);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
        .btn-primary{background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;border:none;box-shadow:0 10px 28px rgba(20,84,84,.14)}
        .panel{padding:16px}
        .controls{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-top:10px}
        input{padding:10px 12px;border-radius:14px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.75);outline:none;min-width:240px}
        .list{display:grid;gap:10px;margin-top:12px;grid-template-columns:1fr}
        @media(min-width:900px){.list{grid-template-columns:repeat(3,1fr)}}
        .item{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px;border-radius:18px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6);flex-wrap:wrap}
        .meta{display:flex;flex-direction:column;gap:4px}
        .pn{font-weight:950;color:#0b1a1a}
        .small{font-size:12px;color:rgba(15,31,31,.62);font-weight:650}
        .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);font-weight:900;font-size:11px;color:rgba(15,31,31,.72);background:rgba(255,255,255,.6)}
        .tags{display:flex;gap:6px;align-items:center;flex-wrap:nowrap;overflow:hidden}
        .tag{white-space:nowrap}
        .status{margin-top:10px;font-size:13px;color:rgba(15,31,31,.62);font-weight:700}
        .load-more-wrap{margin-top:14px;display:flex;justify-content:center}
        .load-more-btn{padding:12px 16px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.65);font-weight:900;color:rgba(15,31,31,.72);cursor:pointer}
        .load-more-btn:disabled{opacity:.6;cursor:not-allowed}
        .skeleton{background:linear-gradient(90deg, rgba(15,31,31,.06) 0%, rgba(15,31,31,.12) 35%, rgba(15,31,31,.06) 70%, rgba(15,31,31,.12) 100%);background-size:300% 100%;animation:skeleton .95s ease-in-out infinite}
        @keyframes skeleton{0%{background-position:200% 0}100%{background-position:-200% 0}}
        .dark .skeleton{background:linear-gradient(90deg, rgba(31,41,55,.55) 0%, rgba(55,65,81,.95) 35%, rgba(242,116,87,.26) 50%, rgba(55,65,81,.95) 65%, rgba(31,41,55,.55) 100%);background-size:400% 100%;animation:skeleton .85s ease-in-out infinite}
        .skel-pn{width:140px;height:16px;border-radius:8px}
        .skel-loc{width:160px;height:12px;border-radius:8px}
        .skel-tags{width:140px;height:26px;border-radius:9999px}
        .skel-buy{width:110px;height:36px;border-radius:14px}
    </style>

    @php
        $code = strtoupper((string) ($countryIso ?? ''));
        $flag = '☎️';
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            $flag = mb_chr(ord($code[0]) + 127397).mb_chr(ord($code[1]) + 127397);
        }
        $price = (string) ($startingFrom ?? (strtoupper((string) ($product->currency ?? 'USD')).' '.number_format(((int) ($product->monthly_amount_minor ?? 0)) / 100, 2)));
    @endphp

    <div class="wrap">
        <div class="head">
            <div>
                <div class="title">{{ $flag }} {{ $countryName }}</div>
                <div class="sub">Starting from {{ $price }}/mo · Pick a number below.</div>
            </div>
            <div class="row">
                <a class="btn" href="{{ $indexUrl ?? route('dashboard.virtual.index') }}">Back</a>
                <a class="btn" href="{{ $myUrl ?? route('dashboard.virtual.my') }}">My Numbers</a>
            </div>
        </div>

        <div class="glass panel">
            <div style="font-weight:950;color:#0b1a1a">Available phone numbers</div>
            <div class="controls">
                <input id="q" type="text" placeholder="Search by number, city, or region…">
                <button class="btn btn-primary" id="refreshBtn" type="button">Refresh</button>
            </div>
            <div id="status" class="status"></div>
            <div id="results" class="list"></div>
            <div class="load-more-wrap">
                <button id="loadMoreBtn" class="load-more-btn" type="button" disabled>Load more</button>
            </div>
        </div>
    </div>

    <script type="application/json" id="virtualNumbersCountryConfig">{!! json_encode([
        'productId' => (int) $product->id,
        'checkoutBase' => (string) ($checkoutBaseUrl ?? route('dashboard.virtual.checkout')),
    ]) !!}</script>
    <script>
        (() => {
            const cfgEl = document.getElementById('virtualNumbersCountryConfig');
            const cfg = cfgEl ? JSON.parse(cfgEl.textContent || '{}') : {};
            const productId = Number(cfg.productId || 0);
            const checkoutBase = String(cfg.checkoutBase || '');
            const statusEl = document.getElementById('status');
            const resultsEl = document.getElementById('results');
            const qEl = document.getElementById('q');
            const refreshBtn = document.getElementById('refreshBtn');
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';

            let page = 0;
            let hasMore = false;
            let loading = false;
            let lastQuery = '';

            const setStatus = (t) => { statusEl.textContent = t || ''; };

            const esc = (value) => {
                const s = String(value ?? '');
                return s
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#39;');
            };

            const setSkeleton = (count = 9) => {
                resultsEl.innerHTML = '';
                for (let i = 0; i < count; i++) {
                    const row = document.createElement('div');
                    row.className = 'item';
                    row.innerHTML = `
                        <div class="meta">
                            <div class="skel-pn skeleton"></div>
                            <div class="skel-loc skeleton"></div>
                            <div class="skel-tags skeleton"></div>
                        </div>
                        <div class="skel-buy skeleton"></div>
                    `;
                    resultsEl.appendChild(row);
                }
            };

            const renderAppend = (items) => {
                if (!Array.isArray(items) || items.length === 0) return;
                items.forEach((it) => {
                    const phone = String(it.phone_number || '');
                    const friendly = String(it.friendly_name || phone);
                    const region = String(it.region || '');
                    const locality = String(it.locality || '');
                    const loc = [locality, region].filter(Boolean).join(', ');
                    const locShort = loc.length > 18 ? `${loc.slice(0, 18)}…` : loc;
                    const caps = it.capabilities && typeof it.capabilities === 'object' ? it.capabilities : {};
                    const capSms = !!caps.SMS || !!caps.sms || !!caps.Sms;
                    const capVoice = !!caps.Voice || !!caps.voice;
                    const numberType = String(it.number_type || '');
                    const fee = String(it.monthly_fee_formatted || '');
                    const tags = [
                        numberType ? `<span class="tag">${esc(numberType)}</span>` : '',
                        capSms ? '<span class="tag">SMS</span>' : '',
                        capVoice ? '<span class="tag">VOICE</span>' : ''
                    ].filter(Boolean).join(' ');

                    const buyUrl = `${checkoutBase}?product_id=${encodeURIComponent(String(productId))}&phone_number=${encodeURIComponent(phone)}&number_type=${encodeURIComponent(numberType)}`;

                    const row = document.createElement('div');
                    row.className = 'item';
                    row.innerHTML = `
                        <div class="meta">
                            <div class="pn">${esc(friendly)}</div>
                            <div class="small" title="${esc(loc)}">${esc(locShort)}</div>
                            <div class="tags">${tags}</div>
                        </div>
                        <a class="btn btn-primary" href="${buyUrl}" style="width:auto">${fee ? `Buy ${esc(fee)}` : 'Buy'}</a>
                    `;
                    resultsEl.appendChild(row);
                });
            };

            const fetchPage = async ({ reset = false } = {}) => {
                if (loading) return;
                const query = String(qEl.value || '').trim().toLowerCase();
                if (reset) {
                    page = 0;
                    hasMore = false;
                    setSkeleton();
                    lastQuery = query;
                }
                if (query !== lastQuery) {
                    page = 0;
                    hasMore = false;
                    setSkeleton();
                    lastQuery = query;
                }

                loading = true;
                loadMoreBtn.disabled = true;
                setStatus(reset ? 'Loading…' : 'Loading more…');

                try {
                    const reqPage = page;
                    const res = await fetch(`/api/virtual-numbers/available?product_id=${encodeURIComponent(String(productId))}&limit=30&page=${encodeURIComponent(String(reqPage))}`, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        setStatus(json.message || `Failed (HTTP ${res.status})`);
                        return;
                    }
                    const items = Array.isArray(json.items) ? json.items : [];
                    const filtered = query
                        ? items.filter((it) => {
                            const p = String(it.phone_number || '').toLowerCase();
                            const f = String(it.friendly_name || '').toLowerCase();
                            const r = String(it.region || '').toLowerCase();
                            const l = String(it.locality || '').toLowerCase();
                            return p.includes(query) || f.includes(query) || r.includes(query) || l.includes(query);
                        })
                        : items;

                    if (reqPage === 0) {
                        resultsEl.innerHTML = '';
                    }
                    renderAppend(filtered);
                    page = reqPage + 1;
                    hasMore = !!json.has_more;
                    setStatus(filtered.length === 0 && page === 1 ? 'No numbers found.' : '');
                } catch (e) {
                    setStatus('Failed to load numbers.');
                } finally {
                    loading = false;
                    loadMoreBtn.disabled = !hasMore;
                }
            };

            refreshBtn.addEventListener('click', () => fetchPage({ reset: true }));
            loadMoreBtn.addEventListener('click', () => fetchPage({ reset: false }));
            qEl.addEventListener('input', () => fetchPage({ reset: true }));

            fetchPage({ reset: true });
        })();
    </script>
</x-app-layout>
