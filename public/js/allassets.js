// All destinations page: countries / regions / virtual-number tabs with search
// and infinite scroll over GET /api/allassets?tab=…&page=….
(() => {
    const assetToggles = Array.from(document.querySelectorAll('[data-asset-toggle]'));
    const assetSections = Array.from(document.querySelectorAll('[data-asset-section]'));
    const searchInput = document.getElementById('assetSearch');
    const noResultsSearch = document.getElementById('noResultsSearch');
    const loadMoreWrap = document.getElementById('loadMoreWrap');
    const loadMoreBtn = document.getElementById('loadMoreBtn');

    const grids = {
        countries: document.getElementById('countriesGrid').querySelector('.grid'),
        regions: document.getElementById('regionsGrid').querySelector('.grid'),
        virtualNumbers: document.getElementById('virtualNumbersGrid').querySelector('.grid')
    };

    const state = {
        countries: { page: 0, hasMore: true, loading: false, q: '' },
        regions: { page: 0, hasMore: true, loading: false, q: '' },
        virtual: { page: 0, hasMore: true, loading: false, q: '' },
    };
    let activeTab = 'countries';

    const esc = (value) => {
        const s = String(value ?? '');
        return s
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    };

    const safeHref = (value) => {
        const s = String(value ?? '').trim();
        if (!s) return '';
        try {
            const url = new URL(s, window.location.origin);
            if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
            return url.href;
        } catch (e) {
            return '';
        }
    };

    const safeImgSrc = (value) => {
        const s = String(value ?? '').trim();
        if (!s) return '';
        if (s.startsWith('data:image/')) return s;
        return safeHref(s);
    };

    const createCard = (item, type) => {
        const card = document.createElement('div');
        card.className = type === 'virtual' ? 'vnum-card' : 'card';
        card.setAttribute('data-search-name', String(item.name || '').toLowerCase());

        const rawName = String(item.name || '');
        const truncatedName = rawName.length > 12 ? rawName.substring(0, 12) + '...' : rawName;

        if (type === 'virtual') {
            const [vCode, ...vRest] = rawName.includes(' · ') ? rawName.split(' · ') : ['', rawName];
            const vLabel = vRest.join(' · ');
            const imgSrc = safeImgSrc(item.flag_url || '');
            const href = safeHref(item.url || '');
            card.innerHTML = `
                <div class="vnum-top">
                    <div class="flag">
                        ${imgSrc ? `<img src="${imgSrc}" alt="${esc(rawName)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                    </div>
                    <div class="vnum-title">
                        <div class="vnum-name" title="${esc(vLabel)}">${esc(vLabel)}</div>
                        ${vCode ? `<span class="vnum-code">${esc(vCode)}</span>` : ''}
                    </div>
                </div>
                <div class="vnum-price"><b>${esc(item.price_formatted)}</b><span>/mo</span></div>
                <div class="vnum-chips"><span>SMS</span><span>Calls</span><span>Monthly</span></div>
                ${href ? `<a class="vnum-btn" href="${href}">Get Number <span aria-hidden="true">→</span></a>` : `<button class="vnum-btn" type="button" disabled>Get Number</button>`}
            `;
        } else {
            const kind = type === 'regions' ? 'region' : 'country';
            const url = `/assets/${kind}/${encodeURIComponent(String(item.id || ''))}`;
            const imgSrc = safeImgSrc(item.flag_url || '');
            card.innerHTML = `
                <div class="card-left">
                    <div class="flag">
                        ${imgSrc ? `<img src="${imgSrc}" alt="${esc(rawName)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                    </div>
                    <div class="meta">
                        <div class="name">${esc(truncatedName)}</div>
                    </div>
                </div>
                <div class="card-right">
                    <a href="${url}" class="mini-btn js-view-plans">View Plans</a>
                </div>
            `;
        }
        return card;
    };

    const keyToTab = (key) => (key === 'virtualNumbers' ? 'virtual' : key);
    const tabToKey = (tab) => (tab === 'virtual' ? 'virtualNumbers' : tab);

    const updateLoadMoreUi = () => {
        const tabState = state[activeTab];
        const show = !!(tabState && tabState.hasMore && !tabState.loading && tabState.q === (searchInput.value || '').trim());
        loadMoreWrap.classList.toggle('show', show);
    };

    const setSkeleton = (key) => {
        const grid = grids[key];
        if (!grid) return;
        grid.innerHTML = '';
        const count = 9;
        for (let i = 0; i < count; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton-card skeleton-placeholder';
            sk.innerHTML = `
                <div class="card-left">
                    <div class="skeleton-flag skeleton"></div>
                    <div class="meta">
                        <div class="skeleton-text-lg skeleton"></div>
                    </div>
                </div>
                <div class="card-right">
                    <div class="skeleton-btn skeleton"></div>
                </div>
            `;
            grid.appendChild(sk);
        }
    };

    const fetchNextPage = async (tab, { reset = false } = {}) => {
        const tabState = state[tab];
        if (!tabState || tabState.loading) return;

        const key = tabToKey(tab);
        const grid = grids[key];
        if (!grid) return;

        const q = (searchInput.value || '').trim();
        if (reset) {
            tabState.page = 0;
            tabState.hasMore = true;
            tabState.q = q;
            setSkeleton(key);
            noResultsSearch.classList.add('hidden');
        }

        if (!tabState.hasMore) {
            updateLoadMoreUi();
            return;
        }

        tabState.loading = true;
        updateLoadMoreUi();

        // Keep skeletons for initial load
        if (reset) {
            setSkeleton(key);
        }

        try {
            const nextPage = tabState.page + 1;
            const url = `/api/allassets?tab=${encodeURIComponent(tab)}&page=${nextPage}&per_page=30${q ? `&q=${encodeURIComponent(q)}` : ''}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                grid.innerHTML = '<div class="no-results"><p>Failed to load data.</p></div>';
                tabState.hasMore = false;
                return;
            }

            const items = Array.isArray(json.items) ? json.items : [];
            if (nextPage === 1) {
                grid.innerHTML = '';
            }
            items.forEach((item) => {
                grid.appendChild(createCard(item, tab === 'regions' ? 'regions' : (tab === 'virtual' ? 'virtual' : 'countries')));
            });

            tabState.page = nextPage;
            tabState.hasMore = !!json.has_more;

            const any = grid.querySelectorAll('.card, .vnum-card').length > 0;
            noResultsSearch.classList.toggle('hidden', any || (q === '' && items.length > 0));
        } catch (e) {
            grid.innerHTML = '<div class="no-results"><p>Failed to load data.</p></div>';
            tabState.hasMore = false;
        } finally {
            tabState.loading = false;
            updateLoadMoreUi();
        }
    };

    // Main Asset Toggle Logic
    const setAssetSection = (mode) => {
        assetToggles.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-asset-toggle') === mode));
        assetSections.forEach(sec => sec.classList.toggle('hidden', sec.getAttribute('data-asset-section') !== mode));
        activeTab = mode === 'virtual' ? 'virtual' : mode;
        const currentKey = tabToKey(activeTab);

        // Show appropriate section
        assetSections.forEach(sec => sec.classList.toggle('hidden', sec.getAttribute('data-asset-section') !== mode));

        if (state[activeTab].page === 0) {
            fetchNextPage(activeTab, { reset: true });
        } else {
            const grid = grids[currentKey];
            const hasCards = grid && grid.querySelectorAll('.card, .vnum-card').length > 0;
            noResultsSearch.classList.toggle('hidden', hasCards);
        }
        updateLoadMoreUi();
    };

    assetToggles.forEach(btn => {
        btn.addEventListener('click', () => setAssetSection(btn.getAttribute('data-asset-toggle')));
    });

    let searchTimer = null;
    searchInput.addEventListener('input', () => {
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            fetchNextPage(activeTab, { reset: true });
        }, 250);
    });

    loadMoreBtn.addEventListener('click', () => {
        fetchNextPage(activeTab, { reset: false });
    });

    let activeLoadingLink = null;
    const resetViewPlansLoading = (el) => {
        if (!el) return;
        if (!el.classList.contains('is-loading')) return;
        el.classList.remove('is-loading');
        el.removeAttribute('aria-busy');
        el.removeAttribute('aria-disabled');
        if (el.dataset.originalHtml) el.innerHTML = el.dataset.originalHtml;
    };
    const resetAllViewPlansLoading = () => {
        document.querySelectorAll('a.js-view-plans.is-loading').forEach(resetViewPlansLoading);
        activeLoadingLink = null;
    };

    window.addEventListener('pageshow', () => {
        resetAllViewPlansLoading();
    });

    document.addEventListener('click', (e) => {
        const a = e.target.closest('a.js-view-plans');
        if (!a) return;
        if (e.defaultPrevented) return;
        if (e.button !== 0) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const href = a.getAttribute('href');
        if (!href) return;
        e.preventDefault();
        if (activeLoadingLink && activeLoadingLink !== a) resetViewPlansLoading(activeLoadingLink);
        activeLoadingLink = a;
        if (!a.dataset.originalHtml) a.dataset.originalHtml = a.innerHTML;
        a.classList.add('is-loading');
        a.setAttribute('aria-busy', 'true');
        a.setAttribute('aria-disabled', 'true');
        a.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span><span>Loading…</span>';
        window.location.assign(href);
    });

    // Initialize
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    if (tabParam) {
        setAssetSection(tabParam);
    } else {
        setAssetSection('countries');
    }

    fetchNextPage(activeTab, { reset: true });
})();
