// eSIM details page: bundle-type toggle and the buy buttons. Reads its URLs from
// the #detailsPageConfig JSON block rendered by details.blade.php.
(() => {
    const toggles = Array.from(document.querySelectorAll('[data-bundle-toggle]'));
    const grids = Array.from(document.querySelectorAll('[data-bundle-grid]'));
    if (toggles.length === 0 || grids.length === 0) return;

    const setMode = (mode) => {
        toggles.forEach((btn) => btn.classList.toggle('active', btn.getAttribute('data-bundle-toggle') === mode));
        grids.forEach((grid) => grid.classList.toggle('hidden', grid.getAttribute('data-bundle-grid') !== mode));
    };

    const callsGrid = document.getElementById('callsBundleGrid');
    const cfgEl = document.getElementById('detailsPageConfig');
    const cfg = cfgEl ? JSON.parse(cfgEl.textContent || '{}') : {};
    const authBuyUrlBase = String(cfg.authBuyUrlBase || '');
    const isAuthed = Boolean(cfg.isAuthed);
    const loginUrl = String(cfg.loginUrl || '');
    const assetType = String(cfg.assetType || '');
    const assetId = String(cfg.assetId || '');
    const topupEsimId = cfg.topupEsimId ? String(cfg.topupEsimId) : '';
    let activeBuyButton = null;

    const resetBuyButton = (btn) => {
        if (!btn || !btn.classList.contains('is-loading')) return;
        btn.classList.remove('is-loading');
        const original = btn.getAttribute('data-original-label') || btn.textContent.trim() || 'Buy eSIM';
        btn.innerHTML = original;
    };

    const setBuyButtonLoading = (btn) => {
        if (!btn) return;
        if (activeBuyButton && activeBuyButton !== btn) {
            resetBuyButton(activeBuyButton);
        }
        activeBuyButton = btn;
        const label = btn.getAttribute('data-original-label') || btn.textContent.trim() || 'Buy eSIM';
        btn.setAttribute('data-original-label', label);
        btn.classList.add('is-loading');
        btn.innerHTML = `<span class="buy-btn-content"><span class="buy-spinner" aria-hidden="true"></span><span>Loading...</span></span>`;
    };

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('a.buy-btn');
        if (!btn) return;
        setBuyButtonLoading(btn);
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('a.buy-btn.is-loading').forEach(resetBuyButton);
        activeBuyButton = null;
    });

    const renderCallsBundles = (bundles) => {
        if (!callsGrid) return;
        callsGrid.innerHTML = '';
        if (!Array.isArray(bundles) || bundles.length === 0) {
            callsGrid.innerHTML = `
                <div class="no-bundles" style="grid-column: 1 / -1; padding: 40px 0">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg>
                    <p>No Data + Calls plans available for this selection.</p>
                </div>
            `;
            return;
        }

        const checkIcon = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>`;

        bundles.forEach((bundle) => {
            const features = bundle.features || {};
            const hotspot = features.Hotspot || 'Yes';
            const renewal = features.Renewal || (bundle.can_renew === true ? 'Yes' : 'No');
            const topupQuery = topupEsimId ? `&topup_esim_id=${encodeURIComponent(topupEsimId)}` : '';
            const checkoutUrl = `${authBuyUrlBase}?type=${encodeURIComponent(assetType)}&id=${encodeURIComponent(assetId)}&bundle=${encodeURIComponent(bundle.id)}&package_type=DATA-VOICE-SMS${topupQuery}`;

            const buyCta = isAuthed
                ? `<a class="buy-btn" href="${checkoutUrl}" style="display:block;text-align:center">${topupEsimId ? 'Top up' : 'Buy eSIM'}</a>`
                : `<a class="buy-btn" href="${loginUrl}" style="display:block;text-align:center">Sign in to buy</a>`;

            const card = document.createElement('div');
            card.className = 'bundle-card';
            card.innerHTML = `
                <div class="bundle-top">
                    <div>
                        <div class="meta-label" style="margin-bottom: 2px;">Valid for</div>
                        <div class="bundle-data" style="font-size: 24px; color: var(--text);">${bundle.validity || ''}</div>
                    </div>
                    <div class="bundle-price">
                        <div class="price-val">${bundle.price_formatted || ''}</div>
                        <div class="price-sub">One-time payment</div>
                    </div>
                </div>

                <div class="bundle-meta">
                    <div class="meta-item">
                        <span class="meta-label">Data</span>
                        <span class="meta-val">${bundle.data || ''}</span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Type</span>
                        <span class="meta-val">Data + Calls</span>
                    </div>
                </div>

                <div class="bundle-features">
                    <div class="feature">
                        ${checkIcon}
                        <span>Hotspot: <strong>${hotspot}</strong></span>
                    </div>
                    <div class="feature">
                        ${checkIcon}
                        <span>Network: <strong>2G,3G,4G,5G</strong></span>
                    </div>
                    <div class="feature">
                        ${checkIcon}
                        <span>Activation: <strong>Instant</strong></span>
                    </div>
                    <div class="feature">
                        ${checkIcon}
                        <span>Renewal: <strong>${renewal}</strong></span>
                    </div>
                </div>

                ${buyCta}
            `;
            callsGrid.appendChild(card);
        });
    };

    const loadCallsBundlesIfNeeded = async () => {
        if (!callsGrid) return;
        if (callsGrid.getAttribute('data-calls-loaded') === '1') return;
        callsGrid.setAttribute('data-calls-loaded', '1');

        toggles.forEach((b) => b.setAttribute('disabled', 'disabled'));

        try {
            const url = `/api/assets/${encodeURIComponent(assetType)}/${encodeURIComponent(assetId)}/bundles?package_type=DATA-VOICE-SMS`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                callsGrid.innerHTML = `
                    <div class="no-bundles" style="grid-column: 1 / -1; padding: 40px 0">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg>
                        <p>${(json && json.message) ? json.message : 'Failed to load Data + Calls plans.'}</p>
                    </div>
                `;
                return;
            }
            renderCallsBundles(json.bundles || []);
        } catch (e) {
            callsGrid.innerHTML = `
                <div class="no-bundles" style="grid-column: 1 / -1; padding: 40px 0">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg>
                    <p>Failed to load Data + Calls plans.</p>
                </div>
            `;
        } finally {
            toggles.forEach((b) => b.removeAttribute('disabled'));
        }
    };

    toggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.hasAttribute('disabled')) return;
            const mode = btn.getAttribute('data-bundle-toggle');
            setMode(mode);
            if (mode === 'calls') {
                loadCallsBundlesIfNeeded();
            }
        });
    });

    const defaultToggle = toggles.find((t) => !t.hasAttribute('disabled')) || toggles[0];
    const initialMode = defaultToggle.getAttribute('data-bundle-toggle');
    setMode(initialMode);
    if (initialMode === 'calls') {
        loadCallsBundlesIfNeeded();
    }
})();
