// Virtual numbers country picker: searchable, paged grid of countries.
// Config comes from #virtualNumbersIndexConfig.
(() => {
    const cfg = JSON.parse(document.getElementById('virtualNumbersIndexConfig')?.textContent || '{}');
    const apiBase = String(cfg.apiBase || '');
    const qEl = document.getElementById('q');
    const clearBtn = document.getElementById('clearQ');
    const statusEl = document.getElementById('status');
    const gridEl = document.getElementById('grid');
    const moreBtn = document.getElementById('loadMoreBtn');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let page = 0, hasMore = false, loading = false, total = 0, sort = 'name', items = [], debounce;

    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const parsePrice = (s) => { const n = parseFloat(String(s).replace(/[^0-9.]/g, '')); return Number.isFinite(n) ? n : Infinity; };
    const formatPrice = (s) => {
        const raw = String(s || '');
        const n = parsePrice(raw);
        if (!Number.isFinite(n)) return esc(raw);
        const cur = (raw.match(/[A-Z]{3}/) || ['USD'])[0];
        return cur === 'USD' ? `$${n.toFixed(2)}` : `${n.toFixed(2)} ${cur}`;
    };

    const card = (it) => {
        const code = String(it.country_code || '').toLowerCase();
        const name = String(it.country_name || code.toUpperCase());
        const flagEmoji = esc(it.flag || '☎️');
        return `
            <a class="vn-card" href="${esc(it.url)}" aria-label="${esc(name)}, from ${esc(formatPrice(it.starting_price_formatted))} per month">
                <div class="vn-card-top">
                    <div class="vn-flag">${code ? `<img src="https://flagcdn.com/w80/${esc(code)}.png" alt="" loading="lazy" onerror="this.replaceWith(document.createTextNode('${flagEmoji}'))">` : flagEmoji}</div>
                    <div class="vn-name"><b title="${esc(name)}">${esc(name)}</b><span>Local number · ${esc(code.toUpperCase())}</span></div>
                </div>
                <div class="vn-card-bottom">
                    <div class="vn-price"><small>From</small><b class="tab">${formatPrice(it.starting_price_formatted)}</b><i>/mo</i></div>
                    <span class="vn-go" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                </div>
            </a>`;
    };

    const render = () => {
        const list = [...items].sort((a, b) => sort === 'price'
            ? parsePrice(a.starting_price_formatted) - parsePrice(b.starting_price_formatted) || String(a.country_name).localeCompare(b.country_name)
            : String(a.country_name).localeCompare(b.country_name));
        gridEl.innerHTML = list.length ? list.map(card).join('')
            : `<div class="vn-empty"><b>No countries match “${esc(qEl.value)}”</b>Try another spelling, or browse the full list.</div>`;
        statusEl.textContent = list.length ? `${list.length}${total > list.length ? ` of ${total}` : ''} countries` : '';
        moreBtn.hidden = !hasMore;
    };

    const skeleton = () => { gridEl.innerHTML = Array.from({ length: 9 }, () => '<div class="vn-skel" aria-hidden="true"></div>').join(''); statusEl.textContent = 'Loading…'; };

    const fetchPage = async (reset) => {
        if (loading) return;
        if (reset) { page = 0; items = []; skeleton(); }
        loading = true; moreBtn.disabled = true;
        try {
            const u = new URL(apiBase, window.location.origin);
            u.searchParams.set('page', String(page + 1));
            u.searchParams.set('per_page', '60');
            const q = qEl.value.trim();
            if (q) u.searchParams.set('q', q);
            const res = await fetch(u, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || `Request failed (${res.status})`);
            items = items.concat(json.items || []);
            page += 1; hasMore = !!json.has_more; total = Number(json.total || items.length);
            if (!q && total) qEl.placeholder = `Search ${total} countries…`;
            render();
        } catch (e) {
            gridEl.innerHTML = `<div class="vn-empty"><b>We couldn’t load countries</b>${esc(e.message)}. <a href="" style="color:var(--coral)">Try again</a></div>`;
            statusEl.textContent = '';
        } finally {
            loading = false; moreBtn.disabled = false;
        }
    };

    qEl.addEventListener('input', () => {
        clearBtn.style.display = qEl.value ? 'block' : 'none';
        clearTimeout(debounce); debounce = setTimeout(() => fetchPage(true), 250);
    });
    clearBtn.addEventListener('click', () => { qEl.value = ''; qEl.dispatchEvent(new Event('input')); qEl.focus(); });
    document.querySelectorAll('[data-chip]').forEach((b) => b.addEventListener('click', () => { qEl.value = b.dataset.chip; qEl.dispatchEvent(new Event('input')); }));
    document.querySelectorAll('[data-sort]').forEach((b) => b.addEventListener('click', () => {
        sort = b.dataset.sort;
        document.querySelectorAll('[data-sort]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
        render();
    }));
    moreBtn.addEventListener('click', () => fetchPage(false));
    fetchPage(true);
})();
