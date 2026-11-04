// Virtual numbers for one country: lists available numbers and sends the chosen
// one to checkout. Config comes from #virtualNumbersCountryConfig.
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
    const countEl = document.getElementById('count');
    const typeSeg = document.getElementById('typeSeg');
    let activeType = '';
    const seenTypes = new Set();
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
            const sk = document.createElement('div');
            sk.className = 'vnp-skel skeleton';
            resultsEl.appendChild(sk);
        }
    };

    const icons = {
        pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
        sms: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/></svg>',
        voice: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>',
        arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    };
    const titleCase = (t) => t.toLowerCase().replace(/(^|[\s_-])\w/g, (m) => m.toUpperCase()).replace(/[_-]/g, ' ');

    const updateCount = () => {
        const shown = resultsEl.querySelectorAll('.vnp-card:not([hidden])').length;
        countEl.textContent = shown ? `${shown} number${shown === 1 ? '' : 's'}${hasMore ? '+' : ''}` : '';
        if (!loading && !shown && resultsEl.querySelector('.vnp-card')) {
            setStatus('No numbers of this type on this page — try “All” or load more.');
        }
    };

    const applyType = () => {
        resultsEl.querySelectorAll('.vnp-card').forEach((c) => { c.hidden = !!activeType && c.dataset.type !== activeType; });
        typeSeg.querySelectorAll('button').forEach((b) => b.classList.toggle('is-active', b.dataset.type === activeType));
        setStatus('');
        updateCount();
    };

    const addTypeFilter = (type) => {
        if (!type || seenTypes.has(type)) return;
        seenTypes.add(type);
        const b = document.createElement('button');
        b.type = 'button';
        b.dataset.type = type;
        b.textContent = titleCase(type);
        typeSeg.appendChild(b);
    };

    typeSeg.addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b) return;
        activeType = b.dataset.type || '';
        applyType();
    });

    const renderAppend = (items) => {
        if (!Array.isArray(items) || items.length === 0) return;
        items.forEach((it) => {
            const phone = String(it.phone_number || '');
            const friendly = String(it.friendly_name || phone);
            const loc = [String(it.locality || ''), String(it.region || '')].filter(Boolean).join(', ');
            const caps = it.capabilities && typeof it.capabilities === 'object' ? it.capabilities : {};
            const capSms = !!caps.SMS || !!caps.sms || !!caps.Sms;
            const capVoice = !!caps.Voice || !!caps.voice;
            const numberType = String(it.number_type || '');
            const fee = String(it.monthly_fee_formatted || '');
            addTypeFilter(numberType);

            const chips = [
                numberType ? `<span class="ui-chip ui-chip--accent">${esc(titleCase(numberType))}</span>` : '',
                capSms ? `<span class="ui-chip">${icons.sms}SMS</span>` : '',
                capVoice ? `<span class="ui-chip">${icons.voice}Voice</span>` : '',
            ].filter(Boolean).join('');

            const buyUrl = `${checkoutBase}?product_id=${encodeURIComponent(String(productId))}&phone_number=${encodeURIComponent(phone)}&number_type=${encodeURIComponent(numberType)}`;

            const card = document.createElement('a');
            card.className = 'vnp-card';
            card.href = buyUrl;
            card.dataset.type = numberType;
            card.setAttribute('aria-label', `Select ${friendly}${fee ? `, ${fee} per month` : ''}`);
            card.innerHTML = `
                <div class="vnp-top">
                    <div style="min-width:0">
                        <div class="vnp-num">${esc(friendly)}</div>
                        <div class="vnp-loc" title="${esc(loc)}">${icons.pin}<span>${esc(loc || 'Location not listed')}</span></div>
                    </div>
                    ${fee ? `<div class="vnp-price">${esc(fee)}<small>per month</small></div>` : ''}
                </div>
                <div class="vnp-foot">
                    <div class="vnp-chips">${chips}</div>
                    <span class="vnp-go">Select ${icons.arrow}</span>
                </div>
            `;
            resultsEl.appendChild(card);
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
        if (reset) refreshBtn.classList.add('is-spinning');
        setStatus('');

        try {
            const reqPage = page;
            const res = await fetch(`/api/virtual-numbers/available?product_id=${encodeURIComponent(String(productId))}&limit=30&page=${encodeURIComponent(String(reqPage))}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                resultsEl.innerHTML = '';
                setStatus(json.message || `We couldn’t load numbers (HTTP ${res.status}).`);
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
            if (filtered.length === 0 && page === 1) {
                resultsEl.innerHTML = `<div class="ui-empty" style="grid-column:1/-1"><div class="ui-empty__icon">${icons.voice}</div><p class="ui-empty__title">${query ? 'No numbers match your search' : 'No numbers available right now'}</p><p class="ui-empty__desc">${query ? 'Try a different city, region or digits.' : 'Inventory changes often — refresh in a minute or pick another country.'}</p></div>`;
            }
            setStatus('');
        } catch (e) {
            setStatus('We couldn’t load numbers. Check your connection and refresh.');
        } finally {
            loading = false;
            loadMoreBtn.hidden = !hasMore;
            loadMoreBtn.disabled = false;
            refreshBtn.classList.remove('is-spinning');
            applyType();
        }
    };

    refreshBtn.addEventListener('click', () => fetchPage({ reset: true }));
    loadMoreBtn.addEventListener('click', () => fetchPage({ reset: false }));
    let searchTimer = 0;
    qEl.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => fetchPage({ reset: true }), 280); });

    fetchPage({ reset: true });
})();
