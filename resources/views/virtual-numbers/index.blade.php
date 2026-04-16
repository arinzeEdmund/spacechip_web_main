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
        input{padding:10px 12px;border-radius:14px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.75);outline:none;min-width:280px}
        .panel{padding:16px}
        .grid{display:grid;grid-template-columns:1fr;gap:12px;margin-top:12px}
        @media(min-width:860px){.grid{grid-template-columns:repeat(3,1fr)}}
        .card{padding:14px;border-radius:20px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6);display:flex;justify-content:space-between;align-items:center;gap:12px}
        .left{display:flex;align-items:center;gap:12px;min-width:0}
        .flag{height:40px;width:40px;border-radius:14px;background:rgba(255,255,255,.7);border:1px solid rgba(15,31,31,.08);display:flex;align-items:center;justify-content:center;font-size:20px}
        .name{font-weight:950;color:#0b1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:320px}
        .price{font-size:12px;color:rgba(15,31,31,.62);font-weight:750;margin-top:4px}
        .mini{padding:10px 14px;border-radius:9999px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.75);color:rgba(20,84,84,.92);font-weight:900;text-decoration:none;white-space:nowrap}
        .status{margin-top:10px;font-size:13px;color:rgba(15,31,31,.62);font-weight:700}
        .load-more-wrap{margin-top:14px;display:flex;justify-content:center}
        .load-more-btn{padding:12px 16px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.65);font-weight:900;color:rgba(15,31,31,.72);cursor:pointer}
        .load-more-btn:disabled{opacity:.6;cursor:not-allowed}
        .skeleton{background:linear-gradient(90deg, rgba(15,31,31,.06), rgba(15,31,31,.10), rgba(15,31,31,.06));background-size:200% 100%;animation:skeleton 1.1s ease-in-out infinite}
        @keyframes skeleton{0%{background-position:200% 0}100%{background-position:-200% 0}}
        .skel-card{padding:14px;border-radius:20px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6);display:flex;justify-content:space-between;align-items:center;gap:12px}
        .skel-left{display:flex;align-items:center;gap:12px}
        .skel-flag{height:40px;width:40px;border-radius:14px}
        .skel-lines{display:flex;flex-direction:column;gap:8px}
        .skel-line-lg{width:170px;height:14px;border-radius:8px}
        .skel-line-sm{width:120px;height:12px;border-radius:8px}
        .skel-btn{width:96px;height:36px;border-radius:9999px}
    </style>

    <div class="wrap">
        <div class="head">
            <div>
                <div class="title">Virtual Numbers</div>
                <div class="sub">Browse supported countries, then choose a number and subscribe monthly.</div>
            </div>
            <div class="row">
                <a class="btn" href="{{ $myUrl ?? route('dashboard.virtual.my') }}">My Numbers</a>
            </div>
        </div>

        <div class="glass panel">
            <div style="font-weight:950;color:#0b1a1a">Choose a country</div>
            <div class="row" style="margin-top:12px;justify-content:space-between">
                <input id="q" type="text" placeholder="Search countries…">
                <a class="btn btn-primary" href="{{ $indexUrl ?? route('dashboard.virtual.index') }}">Refresh</a>
            </div>
            <div id="status" class="status"></div>
            <div id="grid" class="grid"></div>
            <div class="load-more-wrap">
                <button id="loadMoreBtn" class="load-more-btn" type="button" disabled>Load more</button>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const apiBase = @json((string) ($countriesApiBase ?? '/api/virtual-numbers/countries?context=dashboard'));
            const qEl = document.getElementById('q');
            const statusEl = document.getElementById('status');
            const gridEl = document.getElementById('grid');
            const loadMoreBtn = document.getElementById('loadMoreBtn');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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

            const renderAppend = (items) => {
                if (!Array.isArray(items) || items.length === 0) return;
                items.forEach((it) => {
                    const code = String(it.country_code || '');
                    const name = String(it.country_name || code);
                    const displayName = name.length > 28 ? `${name.slice(0, 28)}…` : name;
                    const flag = String(it.flag || '☎️');
                    const price = String(it.starting_price_formatted || '');
                    const url = String(it.url || '');

                    const card = document.createElement('div');
                    card.className = 'card';
                    card.innerHTML = `
                        <div class="left">
                            <div class="flag">${esc(flag)}</div>
                            <div style="min-width:0">
                                <div class="name" title="${esc(name)}">${esc(displayName)}</div>
                                <div class="price">From ${esc(price)}/mo</div>
                            </div>
                        </div>
                        <a class="mini" href="${esc(url)}">View Plans</a>
                    `;
                    gridEl.appendChild(card);
                });
            };

            const setSkeleton = (count = 9) => {
                gridEl.innerHTML = '';
                for (let i = 0; i < count; i++) {
                    const card = document.createElement('div');
                    card.className = 'skel-card';
                    card.innerHTML = `
                        <div class="skel-left">
                            <div class="skel-flag skeleton"></div>
                            <div class="skel-lines">
                                <div class="skel-line-lg skeleton"></div>
                                <div class="skel-line-sm skeleton"></div>
                            </div>
                        </div>
                        <div class="skel-btn skeleton"></div>
                    `;
                    gridEl.appendChild(card);
                }
            };

            const fetchPage = async ({ reset = false } = {}) => {
                if (loading) return;
                const q = String(qEl.value || '').trim();
                if (reset || q !== lastQuery) {
                    page = 0;
                    hasMore = false;
                    setSkeleton();
                    lastQuery = q;
                }
                loading = true;
                loadMoreBtn.disabled = true;
                setStatus(page === 0 ? 'Loading…' : 'Loading more…');
                try {
                    const nextPage = page + 1;
                    const u = new URL(apiBase, window.location.origin);
                    u.searchParams.set('page', String(nextPage));
                    u.searchParams.set('per_page', '60');
                    if (q) u.searchParams.set('q', q);
                    const res = await fetch(u.toString(), { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        setStatus(json.message || `Failed (HTTP ${res.status})`);
                        return;
                    }
                    if (nextPage === 1) {
                        gridEl.innerHTML = '';
                    }
                    renderAppend(json.items || []);
                    page = nextPage;
                    hasMore = !!json.has_more;
                    setStatus((json.items || []).length === 0 && page === 1 ? 'No countries found.' : '');
                } catch (e) {
                    setStatus('Failed to load countries.');
                } finally {
                    loading = false;
                    loadMoreBtn.disabled = !hasMore;
                }
            };

            qEl.addEventListener('input', () => fetchPage({ reset: true }));
            loadMoreBtn.addEventListener('click', () => fetchPage({ reset: false }));
            fetchPage({ reset: true });
        })();
    </script>
</x-app-layout>
