// Landing page: live search over every eSIM destination and the popular
// countries / regions grids, all fed by GET /api/landing.
(() => {
    let searchableAssets = [];
    const searchInput = document.getElementById('landingSearch');
    const searchIndicator = document.getElementById('searchIndicator');
    const popularCountriesGrid = document.getElementById('popularCountriesGrid');
    const popularRegionsGrid = document.getElementById('popularRegionsGrid');
    let searchTimeout = null;

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

    const createCountryCard = (item, type) => {
        const country = document.createElement('div');
        country.className = 'country';
        const id = String(item.id || item.code || item.slug || '');
        const url = `/assets/${type}/${encodeURIComponent(id)}`;
        const imgSrc = safeImgSrc(item.flag_url || '');

        const rawName = String(item.name || 'Worldwide');
        const truncatedName = rawName.length > 12 ? rawName.substring(0, 12) + '...' : rawName;

        country.innerHTML = `
            <div class="country-left">
                <div class="flag">
                    ${imgSrc ? `<img src="${imgSrc}" alt="${esc(rawName)} flag" loading="lazy">` : `<span>${esc(item.flag || '🌐')}</span>`}
                </div>
                <div class="c-meta">
                    <div class="c-name">${esc(truncatedName)}</div>
                    <div class="c-note">${esc(item.subtext || 'Flexible plans available')}</div>
                    ${item.tag ? `<div class="c-pill">${esc(item.tag)}</div>` : ''}
                </div>
            </div>
            <div class="country-right">
                <div class="price">${esc(item.starting_price_formatted || 'View')} <span>${item.starting_price_formatted ? 'from' : 'plans'}</span></div>
                <a href="${url}" class="mini-btn js-view-plans" style="text-decoration:none">View plans</a>
            </div>
        `;
        return country;
    };

    const fetchData = async () => {
        try {
            const response = await fetch('/api/landing');
            const data = await response.json();

            searchableAssets = data.searchableAssets || [];

            // Render Popular Countries
            if (popularCountriesGrid) {
                const countries = data.popularCountries || [];
                if (countries.length > 0) {
                    popularCountriesGrid.innerHTML = '';
                    countries.forEach(country => {
                        popularCountriesGrid.appendChild(createCountryCard(country, 'country'));
                    });
                } else {
                    popularCountriesGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: rgb(var(--ink-rgb) / .5);">No popular countries found.</div>';
                }
            }

            // Render Popular Regions
            if (popularRegionsGrid) {
                const regions = data.popularRegions || [];
                if (regions.length > 0) {
                    popularRegionsGrid.innerHTML = '';
                    regions.forEach(region => {
                        popularRegionsGrid.appendChild(createCountryCard(region, 'region'));
                    });
                } else {
                    popularRegionsGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: rgb(var(--ink-rgb) / .5);">No regional plans found.</div>';
                }
            }

        } catch (error) {
            console.error('Error fetching landing data:', error);
            if (popularCountriesGrid) popularCountriesGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: rgb(var(--ink-rgb) / .5);">Failed to load destinations.</div>';
        }
    };

    fetchData();

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

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();

            // Clear existing timeout
            if (searchTimeout) clearTimeout(searchTimeout);

            if (query.length < 2) {
                searchIndicator.classList.add('hidden');
                return;
            }

            // Show indicator
            searchIndicator.classList.remove('hidden');

            // Set new timeout (800ms debounce)
            searchTimeout = setTimeout(() => {
                const match = searchableAssets.find(asset => 
                    asset.name.toLowerCase().includes(query)
                );

                if (match) {
                    const url = `/assets/${match.type}/${match.id}`;
                    window.location.href = url;
                } else {
                    searchIndicator.innerText = "No results";
                    setTimeout(() => {
                        searchIndicator.classList.add('hidden');
                        searchIndicator.innerText = "Searching...";
                    }, 1500);
                }
            }, 800);
        });
    }

    const toggles = Array.from(document.querySelectorAll('[data-plan-toggle]'));
    const sections = Array.from(document.querySelectorAll('[data-plan-section]'));
    const heads = Array.from(document.querySelectorAll('[data-plan-head]'));

    if (toggles.length === 0) return;

    const setMode = (mode) => {
        toggles.forEach((btn) => {
            const isActive = btn.getAttribute('data-plan-toggle') === mode;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        sections.forEach((el) => {
            el.classList.toggle('hidden', el.getAttribute('data-plan-section') !== mode);
        });

        heads.forEach((el) => {
            el.classList.toggle('hidden', el.getAttribute('data-plan-head') !== mode);
        });
    };

    toggles.forEach((btn) => {
        btn.addEventListener('click', () => setMode(btn.getAttribute('data-plan-toggle')));
    });

    setMode('countries');
})();
