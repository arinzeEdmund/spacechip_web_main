// Customer dashboard: eSIM catalogue browser (countries / regions / social numbers),
// My eSIMs, wallet top-up and payments, and the virtual-number panel.
// Loaded by resources/views/dashboard.blade.php, which sets window.SPACECHIP_INITIAL_ESIMS
// (the first page of My eSIMs) so it renders without an extra request.
(() => {
    const assetToggles = Array.from(document.querySelectorAll('[data-asset-toggle]'));
    const assetSections = Array.from(document.querySelectorAll('[data-asset-section]'));
    const searchBar = document.querySelector('.search-bar');
    const searchInput = document.getElementById('assetSearch');
    const noResultsSearch = document.getElementById('noResultsSearch');
    const loadMoreWrap = document.getElementById('loadMoreWrap');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const assetsPanel = document.getElementById('assetsPanel');
    const myEsimsPanel = document.getElementById('myEsimsPanel');
    const myEsimsNavBtn = document.getElementById('myEsimsNavBtn');
    const esimFilterBtns = Array.from(document.querySelectorAll('[data-esim-filter]'));
    const myEsimsGrid = document.getElementById('myEsimsGrid');
    const noEsims = document.getElementById('noEsims');
    const esimsLoadMoreWrap = document.getElementById('esimsLoadMoreWrap');
    const esimsLoadMoreBtn = document.getElementById('esimsLoadMoreBtn');

    const grids = {
        countries: document.getElementById('countriesGrid').querySelector('.grid'),
        regions: document.getElementById('regionsGrid').querySelector('.grid'),
    };
    const socialEls = {
        section: document.getElementById('socialNumbersGrid'),
        otpModeBtn: document.getElementById('snOtpModeBtn'),
        rentModeBtn: document.getElementById('snRentModeBtn'),
        rentalsBtn: document.getElementById('snRentalsBtn'),
        ordersBtn: document.getElementById('snOrdersBtn'),
        refreshBtn: document.getElementById('snRefreshBtn'),
        status: document.getElementById('snStatus'),
        grid: document.getElementById('snGrid'),
        inlineSearch: document.getElementById('snInlineSearch'),
    };
    const socialApi = {
        profile: '/api/social-numbers/profile',
        apps: '/api/social-numbers/apps',
        services: '/api/social-numbers/services',
        countries: '/api/social-numbers/countries',
        operators: '/api/social-numbers/operators',
        prices: '/api/social-numbers/prices',
        buy: '/api/social-numbers/buy',
        checkBase: '/api/social-numbers/check/',
        orders: '/api/social-numbers/orders',
        finishBase: '/api/social-numbers/orders/',
        cancelBase: '/api/social-numbers/orders/',
        banBase: '/api/social-numbers/orders/',
        tryAnotherBase: '/api/social-numbers/orders/',
    };
    const socialRentApi = {
        apps: '/api/social-rentals/apps',
        countries: '/api/social-rentals/countries',
        quote: '/api/social-rentals/quote',
        buy: '/api/social-rentals/buy',
        list: '/api/social-rentals',
        activateBase: '/api/social-rentals/',
        smsBase: '/api/social-rentals/',
        cancelBase: '/api/social-rentals/',
    };
    const socialState = {
        view: 'apps',
        mode: 'otp',
        profile: null,
        apps: [],
        browseAll: false,
        allServices: [],
        allServicesTotal: 0,
        allServicesLoading: false,
        countries: [],
        countriesPage: 0,
        selectedApp: null,
        selectedCountry: '',
        selectedOperator: 'any',
        availableOperators: [],
        operators: [],
        operatorsOffset: 0,
        operatorsHasMore: false,
        operatorsLoading: false,
        opsSummary: '',
        order: null,
        isBuying: false,
        pollTimer: null,
        pollingOrderId: null,
        countdownTimer: null,
        historyOffset: 0,
    };
    const socialRentState = {
        view: 'apps',
        apps: [],
        countries: [],
        countriesPage: 0,
        selectedApp: null,
        selectedCountry: '',
        selectedProvider: 'all_providers',
        quote: null,
        rentalsOffset: 0,
    };

    const state = {
        countries: { page: 0, hasMore: true, loading: false, q: '' },
        regions: { page: 0, hasMore: true, loading: false, q: '' },
        virtual: { page: 0, hasMore: false, loading: false, q: '' },
        social: { page: 0, hasMore: false, loading: false, q: '' },
    };
    let activeTab = 'countries';
    let viewMode = 'assets';
    const esimsState = { page: 0, hasMore: true, loading: false, filter: 'valid' };
    const initialMyEsims = window.SPACECHIP_INITIAL_ESIMS ?? null;
    let initialMyEsimsRendered = false;

    const vnEls = {
        countriesView: document.getElementById('vnCountriesView'),
        numbersView: document.getElementById('vnNumbersView'),
        myView: document.getElementById('vnMyView'),
        inboxView: document.getElementById('vnInboxView'),
        status: document.getElementById('vnStatus'),
        countriesGrid: document.getElementById('vnCountriesGrid'),
        countriesSearch: document.getElementById('vnCountrySearch'),
        countriesLoadMoreWrap: document.getElementById('vnCountriesLoadMoreWrap'),
        countriesLoadMoreBtn: document.getElementById('vnCountriesLoadMoreBtn'),
        refreshCountriesBtn: document.getElementById('vnRefreshCountries'),
        myBtnTop: document.getElementById('vnMyBtnTop'),

        numbersTitle: document.getElementById('vnNumbersTitle'),
        numbersSub: document.getElementById('vnNumbersSub'),
        numbersStatus: document.getElementById('vnNumbersStatus'),
        numbersGrid: document.getElementById('vnNumbersGrid'),
        numbersSearch: document.getElementById('vnNumberSearch'),
        numbersLoadMoreWrap: document.getElementById('vnNumbersLoadMoreWrap'),
        numbersLoadMoreBtn: document.getElementById('vnNumbersLoadMoreBtn'),
        refreshNumbersBtn: document.getElementById('vnRefreshNumbers'),
        backToCountriesBtn: document.getElementById('vnBackToCountries'),
        myBtn: document.getElementById('vnMyBtn'),

        myStatus: document.getElementById('vnMyStatus'),
        myGrid: document.getElementById('vnMyGrid'),
        refreshMyBtn: document.getElementById('vnRefreshMy'),
        backFromMyBtn: document.getElementById('vnBackFromMy'),

        inboxTitle: document.getElementById('vnInboxTitle'),
        inboxSub: document.getElementById('vnInboxSub'),
        inboxStatus: document.getElementById('vnInboxStatus'),
        chat: document.getElementById('vnChat'),
        forwardEmail: document.getElementById('vnForwardEmail'),
        forwardPhone: document.getElementById('vnForwardPhone'),
        saveForwardBtn: document.getElementById('vnSaveForward'),
        composeTo: document.getElementById('vnComposeTo'),
        composeBody: document.getElementById('vnComposeBody'),
        composeMedia: document.getElementById('vnComposeMedia'),
        sendBtn: document.getElementById('vnSendMsg'),
        backFromInboxBtn: document.getElementById('vnBackFromInbox'),
        refreshInboxBtn: document.getElementById('vnRefreshInbox'),
    };

    const vnState = {
        view: 'countries',
        countries: { page: 0, hasMore: true, loading: false, q: '' },
        numbers: { page: 0, hasMore: true, loading: false, q: '' },
        selected: { country_code: '', country_name: '', product_id: 0, starting_from: '' },
        inbox: { subscription_id: 0, phone_number: '', last_to: '' },
    };

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

    const normalizeEnvValue = (v) => {
        if (v === null || v === undefined) return '';
        const s = String(v).trim();
        return s.replace(/^"+|"+$/g, '').replace(/^'+|'+$/g, '').trim();
    };

    const paystackMeta = document.querySelector('meta[name="paystack-public-key"]');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const paystackKey = normalizeEnvValue(paystackMeta ? (paystackMeta.getAttribute('content') || '') : '');
    const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';

    const walletEls = {
        balance: document.getElementById('walletBalance'),
        amount: document.getElementById('walletDepositAmount'),
        depositBtn: document.getElementById('walletDepositBtn'),
        refreshBtn: document.getElementById('walletRefreshBtn'),
        status: document.getElementById('walletStatus'),
    };

    const setWalletStatus = (t, tone = 'neutral') => {
        if (!walletEls.status) return;
        walletEls.status.textContent = t || '';
        walletEls.status.classList.toggle('error', tone === 'error' && !!t);
        walletEls.status.classList.toggle('success', tone === 'success' && !!t);
    };

    const setWalletBalance = (t) => {
        if (!walletEls.balance) return;
        walletEls.balance.textContent = t || '$0.00';
    };

    const refreshWallet = async () => {
        setWalletStatus('Loading wallet…', 'neutral');
        try {
            const res = await fetch('/api/wallet/balance?_t=' + Date.now(), { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                setWalletStatus(json.message || 'Wallet unavailable.', 'error');
                return;
            }
            setWalletBalance(String(json.balance_formatted || '$0.00'));
            setWalletStatus('', 'neutral');
        } catch (e) {
            setWalletStatus('Wallet unavailable.', 'error');
        }
    };

    const depositToWallet = async () => {
        const amountUsd = Number(walletEls.amount?.value || 0);
        if (!amountUsd || amountUsd <= 0) {
            setWalletStatus('Enter a valid deposit amount in USD.', 'error');
            return;
        }
        if (!paystackKey || !String(paystackKey).startsWith('pk_')) {
            setWalletStatus('Paystack public key is not configured.', 'error');
            return;
        }
        if (typeof window.PaystackPop === 'undefined') {
            setWalletStatus('Paystack did not load. Disable adblockers and try again.', 'error');
            return;
        }

        if (walletEls.depositBtn) walletEls.depositBtn.disabled = true;
        if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = true;
        setWalletStatus('Initializing deposit…', 'neutral');

        try {
            const initRes = await fetch('/api/wallet/deposit/paystack/initialize', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ amount_usd: amountUsd })
            });
            const initJson = await initRes.json().catch(() => ({}));
            if (!initRes.ok || !initJson.ok) {
                setWalletStatus(initJson.message || 'Deposit initialization failed.', 'error');
                if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
                if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
                return;
            }

            const accessCode = String(initJson.access_code || '');
            const reference = String(initJson.reference || '');
            const email = String(initJson.email || '');
            const amount = Number(initJson.amount || 0);
            const currency = String(initJson.currency || '').toUpperCase();
            if (!accessCode || !reference || !email || !amount || !currency) {
                setWalletStatus('Deposit initialization failed.', 'error');
                if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
                if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
                return;
            }

            setWalletStatus('Opening Paystack…', 'neutral');
            const handler = window.PaystackPop.setup({
                key: paystackKey,
                access_code: accessCode,
                ref: reference,
                email,
                amount,
                currency,
                callback: function (response) {
                    (async () => {
                        setWalletStatus('Verifying deposit…', 'neutral');
                        const verifyRes = await fetch('/api/wallet/deposit/paystack/verify', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({ reference: response.reference })
                        });
                        const verifyJson = await verifyRes.json().catch(() => ({}));
                        if (!verifyRes.ok || !verifyJson.ok) {
                            setWalletStatus(verifyJson.message || 'Deposit verification failed.', 'error');
                            if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
                            if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
                            return;
                        }

                        setWalletStatus('Deposit successful.', 'success');
                        // Give some time for DB to propagate if needed, though with local DB it's instant
                        await new Promise(r => setTimeout(r, 500));
                        await refreshWallet();

                        // Delay the reload to let the user see the success message
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                        if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
                        if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
                    })().catch(() => {
                        setWalletStatus('Deposit verification failed.', 'error');
                        if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
                        if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
                    });
                },
                onClose: function () {
                    if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
                    if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
                    setWalletStatus('', 'neutral');
                }
            });
            handler.openIframe();
        } catch (e) {
            setWalletStatus('Deposit failed. Try again.', 'error');
            if (walletEls.depositBtn) walletEls.depositBtn.disabled = false;
            if (walletEls.refreshBtn) walletEls.refreshBtn.disabled = false;
        }
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
                <button class="vnum-btn" type="button">Get Number <span aria-hidden="true">→</span></button>
            `;
        } else {
            const kind = type === 'regions' ? 'region' : 'country';
            const url = `/assets/${kind}/${encodeURIComponent(String(item.id || ''))}`;
            const imgSrc = safeImgSrc(item.flag_url || '');
            const mobLabel = type === 'regions' ? 'Regional Plans' : 'Available eSIMs';
            card.innerHTML = `
                <div class="card-left">
                    <div class="flag">
                        ${imgSrc ? `<img src="${imgSrc}" alt="${esc(rawName)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                    </div>
                    <div class="meta">
                        <div class="name">${esc(rawName)}</div>
                    </div>
                </div>
                <div class="card-right">
                    <a href="${url}" class="mini-btn js-view-plans">View Plans</a>
                </div>
                <div class="card-mob-footer">
                    <div class="card-avail">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/><path d="M2 12h20M12 2C9 7 9 17 12 22M12 2C15 7 15 17 12 22" stroke="currentColor" stroke-width="1.5"/></svg>
                        ${esc(mobLabel)}
                    </div>
                    <svg class="card-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
            `;
        }
        return card;
    };

    const keyToTab = (key) => (key === 'virtualNumbers' ? 'virtual' : key);
    const tabToKey = (tab) => (tab === 'virtual' ? 'virtualNumbers' : tab);
    const socialQuery = () => {
        const v = socialEls.inlineSearch ? (socialEls.inlineSearch.value || '') : '';
        return String(v).trim();
    };
    const socialIconUrl = (key) => {
        const k = String(key || '').toLowerCase();
        const map = {
            whatsapp: 'simple-icons:whatsapp',
            telegram: 'simple-icons:telegram',
            instagram: 'simple-icons:instagram',
            facebook: 'simple-icons:facebook',
            tiktok: 'simple-icons:tiktok',
            twitter: 'simple-icons:x',
            google: 'simple-icons:google',
            snapchat: 'simple-icons:snapchat',
            discord: 'simple-icons:discord',
            linkedin: 'simple-icons:linkedin',
            wechat: 'simple-icons:wechat',
            viber: 'simple-icons:viber',
            signal: 'simple-icons:signal',
            truecaller: 'mdi:phone-check',
        };
        const icon = map[k] || 'mdi:message-text-outline';
        return `https://api.iconify.design/${icon}.svg?color=%23ffffff`;
    };
    const socialBrandBg = (key) => {
        const k = String(key || '').toLowerCase();
        const map = {
            whatsapp: '#25D366', telegram: '#229ED9', instagram: '#E4405F',
            facebook: '#1877F2', tiktok: '#111827', twitter: '#111827',
            google: '#4285F4', snapchat: '#FFFC00', discord: '#5865F2',
            linkedin: '#0A66C2', wechat: '#07C160', viber: '#7360F2',
            signal: '#3A76F0', truecaller: '#0B7DDA',
        };
        return map[k] || '#111827';
    };
    const socialMonogram = (name) => {
        const cleaned = String(name || '').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
        if (!cleaned) return '#';
        const words = cleaned.split(' ');
        const mono = words.length > 1 ? words[0][0] + words[1][0] : cleaned.slice(0, 2);
        return mono.toUpperCase();
    };
    const socialAppCard = (app) => {
        const card = document.createElement('div');
        const available = app.available === true;
        const key = String(app.key || '');
        const name = String(app.name || 'App');
        const isCatalogue = /^\d+$/.test(key);
        const price = app.price !== null && app.price !== undefined ? String(app.price) : '';
        const coverage = app.coverage ? String(app.coverage) : '';
        const iconSrc = isCatalogue ? '' : safeImgSrc(socialIconUrl(key));
        const bg = socialBrandBg(key);

        let detail = '';
        if (!available) {
            detail = 'Currently unavailable';
        } else if (!isCatalogue) {
            detail = [coverage, price ? `from ${price}` : ''].filter(Boolean).join(' • ') || 'Choose a country next';
        }

        card.className = 'card sn-app' + (isCatalogue ? ' sn-catalogue' : '');
        card.setAttribute('data-search-name', name.toLowerCase());
        const tileStyle = isCatalogue ? '' : `style="background:${esc(bg)};color:${/^#(fffc00)$/i.test(bg) ? '#111' : '#fff'}"`;
        const chips = (!isCatalogue && available)
            ? [coverage ? `<span class="ui-chip">${esc(coverage)}</span>` : '', price ? `<span class="ui-chip ui-chip--accent">from ${esc(price)}</span>` : ''].filter(Boolean).join('')
            : (detail ? `<span class="ui-chip">${esc(detail)}</span>` : '');
        card.innerHTML = `
            <div class="card-left">
                <div class="flag sn-tile ${isCatalogue ? 'sn-mono' : ''}" ${tileStyle}>
                    ${iconSrc ? `<img src="${iconSrc}" alt="${esc(name)}" loading="lazy" onerror="this.remove()">` : ''}<span>${esc(socialMonogram(name))}</span>
                </div>
                <div class="meta">
                    <div class="name" title="${esc(name)}">${esc(name)}</div>
                    ${(!isCatalogue && app.description) ? `<div class="subtext sn-desc">${esc(String(app.description))}</div>` : ''}
                    ${chips ? `<div class="sn-chips">${chips}</div>` : ''}
                </div>
            </div>
            <div class="card-right">
                <button class="mini-btn" type="button" data-sn-app="${esc(key)}" ${available ? '' : 'disabled'} style="${available ? '' : 'opacity:.6;cursor:not-allowed'}">Select</button>
            </div>
        `;
        const btn = card.querySelector('[data-sn-app]');
        if (btn) {
            btn.addEventListener('click', () => socialShowCountries(key).catch(() => {}));
        }
        const img = card.querySelector('.flag img');
        if (img) {
            img.addEventListener('error', () => {
                const span = document.createElement('span');
                span.textContent = isCatalogue ? socialMonogram(name) : '💬';
                img.replaceWith(span);
            });
        }
        return card;
    };

    const renderSocialNumbersPlaceholder = () => {
        if (!socialEls.grid || !socialEls.status) return;
        const q = socialQuery();
        socialEls.grid.innerHTML = '';
        socialEls.section?.classList.toggle('sn-browsing', !!socialState.browseAll);

        if (socialState.browseAll) {
            socialEls.status.textContent = '';
            const bar = document.createElement('div');
            bar.className = 'sn-browse-bar';
            bar.innerHTML = `<button class="vn-btn" type="button" id="snBackToPopular">← Popular apps</button>
                <span class="sn-browse-count">${socialState.allServicesLoading ? 'Searching…' : `${socialState.allServicesTotal.toLocaleString()} service${socialState.allServicesTotal === 1 ? '' : 's'}${q ? ` matching “${esc(q)}”` : ''}`}</span>`;
            socialEls.grid.appendChild(bar);
            bar.querySelector('#snBackToPopular')?.addEventListener('click', () => {
                socialState.browseAll = false;
                renderSocialNumbersPlaceholder();
            });

            const list = Array.isArray(socialState.allServices) ? socialState.allServices : [];
            if (list.length === 0 && !socialState.allServicesLoading) {
                const empty = document.createElement('div');
                empty.className = 'no-results';
                empty.style.cssText = 'grid-column:1/-1;padding:32px 12px';
                empty.innerHTML = '<p>No services found.</p>';
                socialEls.grid.appendChild(empty);
                return;
            }
            list.forEach((app) => socialEls.grid.appendChild(socialAppCard(app)));
            if (list.length < socialState.allServicesTotal) {
                const more = document.createElement('div');
                more.className = 'sn-browse-more';
                more.innerHTML = '<button class="load-more-btn" type="button" id="snServicesMore">Load more</button>';
                socialEls.grid.appendChild(more);
                more.querySelector('#snServicesMore')?.addEventListener('click', () => socialLoadAllServices(q, { append: true }).catch(() => {}));
            }
            return;
        }

        socialEls.status.textContent = 'Loading social numbers…';
        if (!socialState.apps || socialState.apps.length === 0) {
            socialEls.grid.innerHTML = `
                <div class="no-results" style="grid-column: 1 / -1; padding: 44px 12px">
                    <p>Click Refresh to load available apps.</p>
                </div>
            `;
            return;
        }

        const needle = q.toLowerCase();
        const filtered = needle
            ? socialState.apps.filter((a) => String(a.name || '').toLowerCase().includes(needle))
            : socialState.apps;

        socialEls.status.textContent = '';
        filtered.forEach((app) => socialEls.grid.appendChild(socialAppCard(app)));

        const seeMore = document.createElement('div');
        seeMore.style.cssText = 'grid-column:1/-1;display:flex;justify-content:center;padding:10px 0 2px';
        seeMore.innerHTML = `<button class="vn-btn" type="button" id="snSeeMore">${filtered.length === 0 ? 'No popular matches — browse all services' : 'See more services →'}</button>`;
        socialEls.grid.appendChild(seeMore);
        seeMore.querySelector('#snSeeMore')?.addEventListener('click', () => {
            socialState.browseAll = true;
            socialLoadAllServices(socialQuery()).catch(() => {});
        });
    };

    const socialLoadAllServices = async (q, opts = {}) => {
        const append = opts.append === true;
        socialState.allServicesLoading = true;
        if (!append) {
            socialState.allServices = [];
            socialState.allServicesTotal = 0;
        }
        renderSocialNumbersPlaceholder();
        const offset = append ? socialState.allServices.length : 0;
        const url = `${socialApi.services}?q=${encodeURIComponent(String(q || ''))}&offset=${offset}&limit=60`;
        const r = await socialFetchJson(url);
        socialState.allServicesLoading = false;
        if (!r.ok) {
            socialSetStatus(String(r.json && r.json.message ? r.json.message : 'Failed to load services.'));
            renderSocialNumbersPlaceholder();
            return;
        }
        const items = Array.isArray(r.json.items) ? r.json.items : [];
        socialState.allServices = append ? socialState.allServices.concat(items) : items;
        socialState.allServicesTotal = Number(r.json.total || socialState.allServices.length);
        renderSocialNumbersPlaceholder();
    };

    const socialSetStatus = (t) => {
        if (!socialEls.status) return;
        socialEls.status.textContent = t || '';
    };

    const socialSetMode = (mode) => {
        socialState.mode = mode === 'rent' ? 'rent' : 'otp';
        socialState.browseAll = false;
        socialEls.section?.classList.remove('sn-browsing');
        socialEls.otpModeBtn?.classList.toggle('primary', socialState.mode === 'otp');
        socialEls.rentModeBtn?.classList.toggle('primary', socialState.mode === 'rent');
    };

    const socialSetHeaderDisabled = (disabled) => {
        [
            socialEls.otpModeBtn,
            socialEls.rentModeBtn,
            socialEls.rentalsBtn,
            socialEls.ordersBtn,
            socialEls.refreshBtn,
        ].forEach((btn) => {
            if (btn) btn.disabled = !!disabled;
        });
        if (socialEls.inlineSearch) {
            socialEls.inlineSearch.disabled = !!disabled;
        }
    };

    const socialFetchJson = async (url, opts = {}) => {
        const headers = Object.assign({ 'Accept': 'application/json' }, opts.headers || {});
        const res = await fetch(url, Object.assign({}, opts, { headers }));
        const json = await res.json().catch(() => ({}));
        return { ok: res.ok, status: res.status, json };
    };

    const socialEnsureProfile = async () => {
        const r = await socialFetchJson(socialApi.profile);
        if (!r.ok) return null;
        const prof = r.json && r.json.profile && typeof r.json.profile === 'object' ? r.json.profile : null;
        socialState.profile = prof;
        return prof;
    };

    const socialLoadApps = async () => {
        const url = `${socialApi.apps}?country=any&operator=any`;
        const r = await socialFetchJson(url);
        if (!r.ok) {
            socialSetStatus(String(r.json && r.json.message ? r.json.message : 'Failed to load apps.'));
            socialState.apps = [];
            return;
        }
        socialState.apps = Array.isArray(r.json.items) ? r.json.items : [];
    };

    const socialLoadCountries = async () => {
        if (socialState.countries && socialState.countries.length > 0) return;
        const r = await socialFetchJson(socialApi.countries);
        if (!r.ok) return;
        socialState.countries = Array.isArray(r.json.items) ? r.json.items : [];
    };

    const socialRentLoadApps = async () => {
        const r = await socialFetchJson(socialRentApi.apps);
        if (!r.ok) {
            socialSetStatus(String(r.json && r.json.message ? r.json.message : 'Failed to load monthly rental apps.'));
            socialRentState.apps = [];
            return;
        }
        socialRentState.apps = Array.isArray(r.json.items) ? r.json.items : [];
    };
    const socialRentLoadCountries = async () => {
        if (socialRentState.countries && socialRentState.countries.length > 0) return;
        const r = await socialFetchJson(socialRentApi.countries);
        if (!r.ok) return;
        socialRentState.countries = Array.isArray(r.json.items) ? r.json.items : [];
    };

    const socialFlagEmoji = (iso) => {
        const s = String(iso || '').toUpperCase();
        if (s.length !== 2) return '🌐';
        const a = s.charCodeAt(0);
        const b = s.charCodeAt(1);
        if (a < 65 || a > 90 || b < 65 || b > 90) return '🌐';
        return String.fromCodePoint(127397 + a) + String.fromCodePoint(127397 + b);
    };

    const socialRenderOrderBox = (order) => {
        const box = document.getElementById('snOrderBox');
        if (!box) return;
        if (!order || typeof order !== 'object') {
            box.innerHTML = '';
            return;
        }
        const sms = Array.isArray(order.sms) ? order.sms : [];
        const latest = sms.length ? sms[sms.length - 1] : null;
        const code = latest && latest.code ? String(latest.code) : '';
        const text = latest && latest.text ? String(latest.text) : '';
        const sender = latest && latest.sender ? String(latest.sender) : '';
        const phone = String(order.phone_display || order.phone || '').trim();
        const status = String(order.status || 'PENDING');
        const expiresAt = String(order.expires_at || '');
        const timedOut = status === 'TIMEOUT';
        const meta = [
            status,
            String(order.country || ''),
            String(order.operator || 'any'),
        ].filter(Boolean).join(' • ');
        box.innerHTML = `
            <div class="sn-otp-panel">
                <div class="sn-otp-head">
                    <div>
                        <div class="sn-otp-title">${code ? 'OTP received' : (timedOut ? 'No SMS received' : 'Waiting for SMS code')}</div>
                        <div class="sn-otp-sub">Order #${esc(String(order.id || ''))}${meta ? ` • ${esc(meta)}` : ''}</div>
                    </div>
                    <div class="sn-countdown">
                        <div class="k">Time left</div>
                        <div class="v" data-sn-countdown="${esc(expiresAt)}">10:00</div>
                    </div>
                </div>
                <div class="sn-otp-grid">
                    <div class="sn-otp-box">
                        <div class="sn-otp-label">Generated phone number</div>
                        <div class="sn-phone-value">${phone ? esc(phone) : 'Number unavailable'}</div>
                        <div class="sn-otp-sub">Use this number on ${esc(String(order.product_name || 'the selected app'))} before the timer expires.</div>
                    </div>
                    <div class="sn-otp-box">
                        <div class="sn-otp-label">Verification code</div>
                        <div class="sn-code-slot ${code ? 'has-code' : ''}">
                            ${code ? `<div><div class="sn-code-value">${esc(code)}</div>${sender ? `<div class="sn-otp-sub">From ${esc(sender)}</div>` : ''}</div>` : `<div>${timedOut ? 'This number did not receive an OTP. Try another number.' : 'Code will appear here automatically.'}</div>`}
                        </div>
                    </div>
                </div>
                ${text ? `<div class="sn-otp-box"><div class="sn-otp-label">SMS message</div><div style="margin-top:8px;color:rgb(var(--ink-rgb) / .78);font-weight:800;line-height:1.5">${esc(text)}</div></div>` : ''}
            </div>
        `;
        socialUpdateCountdowns();
        if (code || ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT', 'RECEIVED'].includes(status)) {
            socialStopCountdown();
        } else {
            socialStartCountdown();
        }
    };

    const socialFormatDuration = (ms) => {
        const total = Math.max(0, Math.floor(ms / 1000));
        const minutes = Math.floor(total / 60);
        const seconds = total % 60;
        return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    };

    const socialUpdateCountdowns = () => {
        document.querySelectorAll('[data-sn-countdown]').forEach((el) => {
            const raw = el.getAttribute('data-sn-countdown') || '';
            const end = raw ? new Date(raw).getTime() : 0;
            if (!end || Number.isNaN(end)) {
                el.textContent = '10:00';
                return;
            }
            const remaining = end - Date.now();
            el.textContent = remaining > 0 ? socialFormatDuration(remaining) : '00:00';
        });
    };

    const socialStartCountdown = () => {
        if (socialState.countdownTimer) return;
        socialState.countdownTimer = window.setInterval(socialUpdateCountdowns, 1000);
    };

    const socialStopCountdown = () => {
        if (socialState.countdownTimer) {
            window.clearInterval(socialState.countdownTimer);
            socialState.countdownTimer = null;
        }
    };

    const socialShowApps = async () => {
        socialStopPolling();
        socialStopCountdown();
        socialState.view = 'apps';
        socialState.browseAll = false;
        socialState.selectedApp = null;
        socialState.selectedCountry = '';
        socialState.selectedOperator = 'any';
        socialState.availableOperators = [];
        socialState.operators = [];
        socialState.operatorsOffset = 0;
        socialState.operatorsHasMore = false;

        await socialEnsureProfile();
        await socialLoadApps();
        renderSocialNumbersPlaceholder();
    };

    const socialRenderCountries = () => {
        if (!socialEls.grid) return;
        const app = socialState.selectedApp;
        const q = socialQuery().toLowerCase();
        const countries = Array.isArray(socialState.countries) ? socialState.countries : [];
        const filtered = q
            ? countries.filter((c) => String(c.name || c.country || '').toLowerCase().includes(q))
            : countries;

        const perPage = 30;
        const max = Math.min(filtered.length, (socialState.countriesPage + 1) * perPage);
        const slice = filtered.slice(0, max);

        socialEls.grid.innerHTML = '';
        socialSetStatus(app ? `Choose a country for ${String(app.name || '')}` : 'Choose a country');

        slice.forEach((c) => {
            const card = document.createElement('div');
            card.className = 'card';
            const flag = socialFlagEmoji(c.iso || '');
            card.setAttribute('data-search-name', String(c.name || c.country || '').toLowerCase());
            card.innerHTML = `
                <div class="card-left">
                    <div class="flag"><span>${esc(flag)}</span></div>
                    <div class="meta">
                        <div class="name">${esc(String(c.name || c.country || ''))}</div>
                        <div class="subtext">${esc(String(c.prefix || ''))}</div>
                    </div>
                </div>
                <div class="card-right">
                    <button class="mini-btn" type="button" data-sn-country="${esc(String(c.country || ''))}">Generate</button>
                </div>
            `;
            socialEls.grid.appendChild(card);
            const btn = card.querySelector('[data-sn-country]');
            if (btn) {
                btn.addEventListener('click', () => socialShowCountry(String(c.country || '')).catch(() => {}));
            }
        });

        const canLoadMore = max < filtered.length;
        if (canLoadMore) {
            socialEls.grid.insertAdjacentHTML('beforeend', `<div style="grid-column:1 / -1; display:flex; gap:10px; justify-content:center; margin-top:10px"><button class="load-more-btn" type="button" id="snCountriesMoreBtn">Load more</button></div>`);
            document.getElementById('snCountriesMoreBtn')?.addEventListener('click', () => {
                socialState.countriesPage += 1;
                socialRenderCountries();
            });
        }
    };

    const socialShowCountries = async (appKey) => {
        const wanted = String(appKey || '');
        const app = socialState.apps.find((a) => String(a.key || '') === wanted)
            || (socialState.allServices || []).find((a) => String(a.key || '') === wanted);
        if (!app) return;
        socialEls.section?.classList.remove('sn-browsing');
        socialStopPolling();
        socialStopCountdown();
        socialState.view = 'countries';
        socialState.selectedApp = app;
        socialState.countriesPage = 0;
        socialState.selectedCountry = '';
        socialState.countrySummary = '';
        await socialLoadCountries();
        socialSetStatus('');
        socialRenderCountries();
    };

    const socialRenderCountry = () => {
        if (!socialEls.grid) return;
        const app = socialState.selectedApp;
        const countryKey = String(socialState.selectedCountry || '');
        const countryObj = (Array.isArray(socialState.countries) ? socialState.countries : []).find((c) => String(c.country || '') === countryKey);
        const countryName = countryObj ? (countryObj.name || countryKey) : countryKey;
        const order = socialState.order && typeof socialState.order === 'object' ? socialState.order : null;
        const status = order ? String(order.status || '') : '';
        const isFinal = ['FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'].includes(status);
        const hasSms = !!(order && Array.isArray(order.sms) && order.sms.length > 0);
        const showWaitingActions = !!(order && !isFinal && !hasSms);
        const showFinishAction = !!(order && !isFinal && hasSms);
        const showTimeoutAction = !!(order && status === 'TIMEOUT' && !hasSms);
        const showGenerate = !order || isFinal;
        const isBuying = !!socialState.isBuying;

        const ops = Array.isArray(socialState.availableOperators) ? socialState.availableOperators : [];
        const selectedOp = socialState.selectedOperator || 'any';
        const operatorPickerHtml = (showGenerate && ops.length > 0) ? `
            <div style="grid-column:1/-1;margin-bottom:12px">
                <div style="font-size:11px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgb(var(--ink-rgb) / .46);margin-bottom:8px">Select Operator</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px" id="snOperatorPicker">
                    <button type="button" class="vn-btn${selectedOp === 'any' ? ' primary' : ''}" data-op="any" style="font-size:12px;padding:6px 14px">Auto (any)</button>
                    ${ops.map(o => `<button type="button" class="vn-btn${selectedOp === String(o.key || '') ? ' primary' : ''}" data-op="${esc(String(o.key || ''))}" style="font-size:12px;padding:6px 14px">${esc(String(o.name || o.key || ''))}</button>`).join('')}
                </div>
            </div>` : '';

        socialEls.grid.innerHTML = `
            <div class="vn-head" style="grid-column: 1 / -1; margin-bottom: 8px">
                <div>
                    <div class="vn-title">${esc(String(app ? app.name : ''))} • ${esc(String(countryName || ''))}</div>
                    <div class="vn-sub">Click Generate to get a phone number for verification.</div>
                </div>
                <div class="vn-actions">
                    <button type="button" class="vn-btn" id="snBackToCountriesBtn" ${isBuying ? 'disabled' : ''}>Back</button>
                    ${showGenerate ? `<button type="button" class="vn-btn primary" id="snGenerateBtn" ${isBuying ? 'disabled' : ''}>${isBuying ? '<span class="btn-spinner" aria-hidden="true"></span><span>Generating...</span>' : 'Generate'}</button>` : ''}
                    ${showWaitingActions ? `
                        <button type="button" class="vn-btn" id="snCancelOrderHdr" ${isBuying ? 'disabled' : ''}>Cancel</button>
                        <button type="button" class="vn-btn" id="snBanOrderHdr" ${isBuying ? 'disabled' : ''}>Ban</button>
                        <button type="button" class="vn-btn primary" id="snTryAnotherHdr" ${isBuying ? 'disabled' : ''}>Try another</button>
                    ` : ''}
                    ${showTimeoutAction ? `
                        <button type="button" class="vn-btn primary" id="snTryAnotherHdr" ${isBuying ? 'disabled' : ''}>Try another</button>
                    ` : ''}
                    ${showFinishAction ? `
                        <button type="button" class="vn-btn primary" id="snFinishOrderHdr" ${isBuying ? 'disabled' : ''}>Finish</button>
                    ` : ''}
                </div>
            </div>
            ${operatorPickerHtml}
            <div style="grid-column:1 / -1">
                <div id="snCountryStatus" class="vn-status"></div>
                <div id="snOrderBox"></div>
            </div>
        `;

        const st = document.getElementById('snCountryStatus');
        if (st) st.textContent = socialState.countrySummary || '';
        socialRenderOrderBox(socialState.order);

        document.getElementById('snBackToCountriesBtn')?.addEventListener('click', () => {
            socialStopPolling();
            socialStopCountdown();
            socialState.view = 'countries';
            socialState.selectedCountry = '';
            socialState.countrySummary = '';
            socialState.selectedOperator = 'any';
            socialState.availableOperators = [];
            socialRenderCountries();
        });
        document.getElementById('snGenerateBtn')?.addEventListener('click', () => {
            if (socialState.isBuying) return;
            socialBuy(countryKey, socialState.selectedOperator || 'any');
        });
        document.getElementById('snOperatorPicker')?.querySelectorAll('[data-op]').forEach((btn) => {
            btn.addEventListener('click', () => {
                socialState.selectedOperator = String(btn.getAttribute('data-op') || 'any');
                socialRenderCountry();
            });
        });
        document.getElementById('snCancelOrderHdr')?.addEventListener('click', () => socialCancelOrder());
        document.getElementById('snBanOrderHdr')?.addEventListener('click', () => socialBanOrder());
        document.getElementById('snTryAnotherHdr')?.addEventListener('click', () => socialTryAnotherOrder());
        document.getElementById('snFinishOrderHdr')?.addEventListener('click', () => socialFinishOrder());
    };

    const socialLoadCountrySummary = async () => {
        if (!socialState.selectedApp || !socialState.selectedCountry) return;
        const appKey = String(socialState.selectedApp.key || '');
        const countryKey = String(socialState.selectedCountry || '');
        const url = `${socialApi.prices}?country=${encodeURIComponent(countryKey)}&product=${encodeURIComponent(appKey)}&limit=1&offset=0`;
        const r = await socialFetchJson(url);
        if (!r.ok) {
            socialState.countrySummary = String(r.json && r.json.message ? r.json.message : 'Unable to load availability.');
            socialRenderCountry();
            return;
        }
        const total = Number(r.json.total_count || 0);
        const minCost = r.json.min_cost !== null && r.json.min_cost !== undefined ? String(r.json.min_cost) : '';
        socialState.countrySummary = total > 0 ? `${total} available${minCost ? ` • from ${minCost}` : ''}` : 'No availability found.';
        socialRenderCountry();
    };

    const socialLoadOperators = async (countryKey) => {
        socialState.availableOperators = [];
        const r = await socialFetchJson(`${socialApi.operators}?country=${encodeURIComponent(String(countryKey || ''))}`);
        if (r.ok && r.json && Array.isArray(r.json.items)) {
            socialState.availableOperators = r.json.items;
        }
    };

    const socialShowCountry = async (countryKey) => {
        if (!socialState.selectedApp) return;
        socialStopPolling();
        socialStopCountdown();
        socialState.view = 'country';
        socialState.selectedCountry = String(countryKey || '');
        socialState.selectedOperator = 'any';
        socialState.countrySummary = 'Loading…';
        socialState.order = null;
        socialRenderCountry();
        await Promise.all([socialLoadCountrySummary(), socialLoadOperators(countryKey)]);
        socialRenderCountry();
    };

    const socialBuy = async (countryKey, operator) => {
        if (!socialState.selectedApp || socialState.isBuying) return;
        const appKey = String(socialState.selectedApp.key || '');
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        socialState.isBuying = true;
        socialSetHeaderDisabled(true);
        socialState.countrySummary = 'Buying number…';
        socialRenderCountry();
        try {
            const r = await socialFetchJson(socialApi.buy, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ product: appKey, country: String(countryKey || ''), operator: String(operator || 'any') }),
            });
            if (!r.ok) {
                socialState.countrySummary = String(r.json && r.json.message ? r.json.message : 'Buy failed.');
                return;
            }
            const order = r.json && r.json.order && typeof r.json.order === 'object' ? r.json.order : null;
            socialState.order = order;
            socialState.countrySummary = 'Waiting for SMS…';
            socialStartPolling();
        } catch (e) {
            socialState.countrySummary = 'Buy failed.';
        } finally {
            socialState.isBuying = false;
            socialSetHeaderDisabled(false);
            socialRenderCountry();
        }
    };

    const socialStopPolling = () => {
        if (socialState.pollTimer) {
            window.clearInterval(socialState.pollTimer);
            socialState.pollTimer = null;
        }
        socialState.pollingOrderId = null;
    };
    const socialStartPolling = () => {
        socialStopPolling();
        if (!socialState.order || !socialState.order.id) return;
        const id = Number(socialState.order.id);
        if (!id) return;
        socialState.pollingOrderId = id;
        let shouldContinue = true;
        let pollCount = 0;
        const startTime = Date.now();
        console.log(`[SocialOTP] Polling started — order #${id}`);
        const poll = async () => {
            if (socialState.pollingOrderId !== id) return;
            pollCount++;
            const elapsedSec = ((Date.now() - startTime) / 1000).toFixed(1);
            console.log(`[SocialOTP] Poll #${pollCount} — order #${id} — elapsed ${elapsedSec}s`);
            const r = await socialFetchJson(socialApi.checkBase + encodeURIComponent(String(id)));
            if (socialState.pollingOrderId !== id) return;
            if (!r.ok) {
                console.warn(`[SocialOTP] Poll #${pollCount} — order #${id} — request failed`);
                return;
            }
            const order = r.json && r.json.order && typeof r.json.order === 'object' ? r.json.order : null;
            if (!order) return;
            socialState.order = order;
            const status = String(order.status || '');
            console.log(`[SocialOTP] Poll #${pollCount} — order #${id} — status: ${status}${status === 'RECEIVED' ? ` — code: ${order.sms_code || '?'}` : ''}`);
            if (socialState.view === 'country') {
                socialRenderCountry();
            }
            if (['RECEIVED', 'FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'].includes(status)) {
                shouldContinue = false;
                console.log(`[SocialOTP] Polling stopped — order #${id} — final status: ${status} — polls: ${pollCount} — elapsed: ${((Date.now() - startTime) / 1000).toFixed(1)}s`);
                socialStopPolling();
            }
        };
        poll().finally(() => {
            if (shouldContinue && socialState.pollingOrderId === id && !socialState.pollTimer) {
                socialState.pollTimer = window.setInterval(poll, 5000);
            }
        });
    };

    const socialFinishOrder = async () => {
        if (!socialState.order || !socialState.order.id) return;
        const id = Number(socialState.order.id);
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        await socialFetchJson(`${socialApi.finishBase}${encodeURIComponent(String(id))}/finish`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        socialStopPolling();
        socialStopCountdown();
        socialLoadHistory(true);
    };
    const socialCancelOrder = async () => {
        if (!socialState.order || !socialState.order.id) return;
        const id = Number(socialState.order.id);
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        await socialFetchJson(`${socialApi.cancelBase}${encodeURIComponent(String(id))}/cancel`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        socialStopPolling();
        socialStopCountdown();
        socialLoadHistory(true);
    };
    const socialBanOrder = async () => {
        if (!socialState.order || !socialState.order.id) return;
        const id = Number(socialState.order.id);
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        await socialFetchJson(`${socialApi.banBase}${encodeURIComponent(String(id))}/ban`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        socialStopPolling();
        socialStopCountdown();
        socialLoadHistory(true);
    };
    const socialTryAnotherOrder = async () => {
        if (!socialState.order || !socialState.order.id) return;
        const id = Number(socialState.order.id);
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        socialState.countrySummary = 'Getting another number…';
        socialRenderCountry();
        const r = await socialFetchJson(`${socialApi.tryAnotherBase}${encodeURIComponent(String(id))}/try-another`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
        });
        if (!r.ok) {
            socialState.countrySummary = String(r.json && r.json.message ? r.json.message : 'Unable to get another number.');
            socialRenderCountry();
            return;
        }
        const order = r.json && r.json.order && typeof r.json.order === 'object' ? r.json.order : null;
        socialState.order = order;
        socialState.countrySummary = 'Waiting for SMS…';
        socialRenderCountry();
        socialStartPolling();
    };

    const socialLoadHistory = async (reset = false) => {
        if (!socialEls.grid) return;
        socialEls.section?.classList.remove('sn-browsing');
        socialState.browseAll = false;
        socialStopPolling();
        socialState.view = 'history';
        socialState.selectedApp = null;
        socialStopCountdown();
        if (reset) {
            socialState.historyOffset = 0;
        }
        socialSetStatus('Loading orders…');
        const url = `${socialApi.orders}?limit=20&offset=${encodeURIComponent(String(socialState.historyOffset || 0))}`;
        const r = await socialFetchJson(url);
        if (!r.ok) {
            socialSetStatus(String(r.json && r.json.message ? r.json.message : 'Failed to load orders.'));
            return;
        }
        const items = Array.isArray(r.json.items) ? r.json.items : [];
        if (reset) {
            socialEls.grid.innerHTML = '';
        }
        if (items.length === 0 && reset) {
            socialEls.grid.innerHTML = `<div class="no-results" style="grid-column:1 / -1; padding:44px 12px"><p>No social orders yet.</p></div>`;
            return;
        }
        items.forEach((o) => {
            const card = document.createElement('div');
            card.className = 'card';
            const sms = Array.isArray(o.sms) ? o.sms : [];
            const last = sms.length ? sms[sms.length - 1] : null;
            const code = last && last.code ? String(last.code) : '';
            const displayProduct = String(o.product_name || o.product || '');
            const displayCountry = String(o.country_name || o.country || '');
            const iconSrc = safeImgSrc(socialIconUrl(o.product || ''));
            const bg = socialBrandBg(o.product || '');
            card.innerHTML = `
                <div class="card-left">
                    <div class="flag" style="background:${esc(bg)};border-color:rgb(var(--surface-rgb) / .16)">
                        ${iconSrc ? `<img src="${iconSrc}" alt="${esc(displayProduct)}">` : `<span>📩</span>`}
                    </div>
                    <div class="meta">
                        <div class="name">${esc(displayProduct)} • ${esc(String(o.phone || ''))}</div>
                        <div class="subtext">${esc(displayCountry)} • ${esc(String(o.operator || ''))} • ${esc(String(o.status || ''))}</div>
                        <div class="subtext">${code ? `Last code: ${esc(code)}` : 'No SMS yet.'}</div>
                    </div>
                </div>
                <div class="card-right">
                    <button class="mini-btn" type="button" data-sn-open="${esc(String(o.id || ''))}">Open</button>
                </div>
            `;
            socialEls.grid.appendChild(card);
        });
        socialState.historyOffset = (socialState.historyOffset || 0) + items.length;
        socialSetStatus('');
        socialEls.grid.insertAdjacentHTML('beforeend', `<div style="grid-column:1 / -1; display:flex; gap:10px; justify-content:center; margin-top:10px"><button class="load-more-btn" type="button" id="snMoreBtn">Load more</button><button class="load-more-btn" type="button" id="snBackToApps">Back to apps</button></div>`);
        document.getElementById('snMoreBtn')?.addEventListener('click', () => socialLoadHistory(false));
        document.getElementById('snBackToApps')?.addEventListener('click', () => { socialState.historyOffset = 0; socialShowApps().catch(() => {}); });
        Array.from(document.querySelectorAll('[data-sn-open]')).forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.getAttribute('data-sn-open') || 0);
                if (!id) return;
                socialSetStatus('Loading order…');
                const rr = await socialFetchJson(socialApi.checkBase + encodeURIComponent(String(id)));
                if (!rr.ok) {
                    socialSetStatus(String(rr.json && rr.json.message ? rr.json.message : 'Failed to load order.'));
                    return;
                }
                const order = rr.json && rr.json.order && typeof rr.json.order === 'object' ? rr.json.order : null;
                if (!order) return;
                socialState.order = order;
                const key = String(order.product || '');
                const countryKey = String(order.country || '');
                await socialLoadApps();
                await socialEnsureProfile();
                await socialShowCountries(key);
                await socialShowCountry(countryKey);
                socialState.order = order;
                socialStartPolling();
            });
        });
    };

    const socialRentShowApps = async () => {
        socialStopPolling();
        socialSetMode('rent');
        socialRentState.view = 'apps';
        socialRentState.selectedApp = null;
        socialRentState.selectedCountry = '';
        socialRentState.quote = null;
        await socialRentLoadApps();
        socialRentRenderApps();
    };
    const socialRentRenderApps = () => {
        if (!socialEls.grid) return;
        const q = socialQuery().toLowerCase();
        const items = q ? socialRentState.apps.filter((a) => String(a.name || '').toLowerCase().includes(q)) : socialRentState.apps;
        socialSetStatus('Choose an app to rent for one month');
        socialEls.grid.innerHTML = '';
        if (items.length === 0) {
            socialEls.grid.innerHTML = `<div class="no-results" style="grid-column:1/-1;padding:44px 12px"><p>No monthly rental apps found.</p></div>`;
            return;
        }
        items.forEach((app) => {
            const card = document.createElement('div');
            const iconSrc = safeImgSrc(socialIconUrl(app.key || ''));
            const bg = socialBrandBg(app.key || '');
            card.className = 'card';
            card.innerHTML = `
                <div class="card-left">
                    <div class="flag" style="background:${esc(bg)};border-color:rgb(var(--surface-rgb) / .16)">
                        ${iconSrc ? `<img src="${iconSrc}" alt="${esc(String(app.name || ''))}">` : `<span>📩</span>`}
                    </div>
                    <div class="meta">
                        <div class="name">${esc(String(app.name || 'App'))}</div>
                        <div class="subtext">Keep a number for one month with wallet renewal.</div>
                    </div>
                </div>
                <div class="card-right">
                    <button class="mini-btn" type="button" data-snr-app="${esc(String(app.key || ''))}">Select</button>
                </div>
            `;
            socialEls.grid.appendChild(card);
            card.querySelector('[data-snr-app]')?.addEventListener('click', () => socialRentShowCountries(String(app.key || '')).catch(() => {}));
        });
    };
    const socialRentShowCountries = async (appKey) => {
        const app = socialRentState.apps.find((a) => String(a.key || '') === String(appKey || ''));
        if (!app) return;
        socialSetMode('rent');
        socialRentState.view = 'countries';
        socialRentState.selectedApp = app;
        socialRentState.selectedCountry = '';
        socialRentState.quote = null;
        socialRentState.countriesPage = 0;
        await socialRentLoadCountries();
        socialRentRenderCountries();
    };
    const socialRentRenderCountries = () => {
        if (!socialEls.grid) return;
        const q = socialQuery().toLowerCase();
        const filtered = q ? socialRentState.countries.filter((c) => String(c.name || c.country || '').toLowerCase().includes(q)) : socialRentState.countries;
        const perPage = 30;
        const max = Math.min(filtered.length, (socialRentState.countriesPage + 1) * perPage);
        const slice = filtered.slice(0, max);
        socialSetStatus(`Choose a monthly rental country for ${String(socialRentState.selectedApp?.name || '')}`);
        socialEls.grid.innerHTML = '';
        slice.forEach((c) => {
            const card = document.createElement('div');
            card.className = 'card';
            card.innerHTML = `
                <div class="card-left">
                    <div class="flag"><span>${esc(socialFlagEmoji(c.iso || ''))}</span></div>
                    <div class="meta">
                        <div class="name">${esc(String(c.name || c.country || ''))}</div>
                        <div class="subtext">${esc(String(c.prefix || ''))} • one month rental</div>
                    </div>
                </div>
                <div class="card-right">
                    <button class="mini-btn" type="button" data-snr-country="${esc(String(c.country || ''))}">View</button>
                </div>
            `;
            socialEls.grid.appendChild(card);
            card.querySelector('[data-snr-country]')?.addEventListener('click', () => socialRentShowCountry(String(c.country || '')).catch(() => {}));
        });
        if (max < filtered.length) {
            socialEls.grid.insertAdjacentHTML('beforeend', `<div style="grid-column:1/-1;display:flex;justify-content:center;margin-top:10px"><button class="load-more-btn" type="button" id="snrCountriesMoreBtn">Load more</button></div>`);
            document.getElementById('snrCountriesMoreBtn')?.addEventListener('click', () => {
                socialRentState.countriesPage += 1;
                socialRentRenderCountries();
            });
        }
    };
    const socialRentShowCountry = async (countryKey) => {
        if (!socialRentState.selectedApp) return;
        socialSetMode('rent');
        socialRentState.view = 'country';
        socialRentState.selectedCountry = String(countryKey || '');
        socialRentState.selectedProvider = 'all_providers';
        socialRentState.quote = null;
        socialRentRenderCountry('Loading monthly price…');
        await socialRentLoadQuote();
    };
    const socialRentLoadQuote = async () => {
        const appKey = String(socialRentState.selectedApp?.key || '');
        const countryKey = String(socialRentState.selectedCountry || '');
        const provider = String(socialRentState.selectedProvider || 'all_providers');
        const url = `${socialRentApi.quote}?product=${encodeURIComponent(appKey)}&country=${encodeURIComponent(countryKey)}&provider=${encodeURIComponent(provider)}`;
        const r = await socialFetchJson(url);
        if (!r.ok) {
            socialRentState.quote = null;
            socialRentRenderCountry(String(r.json && r.json.message ? r.json.message : 'Unable to load monthly price.'));
            return;
        }
        socialRentState.quote = r.json;
        socialRentRenderCountry('');
    };
    const socialRentRenderCountry = (message = '') => {
        if (!socialEls.grid) return;
        const countryKey = String(socialRentState.selectedCountry || '');
        const countryObj = (socialRentState.countries || []).find((c) => String(c.country || '') === countryKey);
        const countryName = countryObj ? (countryObj.name || countryKey) : countryKey;
        const q = socialRentState.quote || {};
        const providers = Array.isArray(q.providers) ? q.providers : [];
        const providerOptions = ['all_providers'].concat(providers).map((p) => `<option value="${esc(String(p))}" ${String(socialRentState.selectedProvider || 'all_providers') === String(p) ? 'selected' : ''}>${esc(String(p === 'all_providers' ? 'All providers' : p))}</option>`).join('');
        const count = Number(q.count || 0);
        const price = q.monthly_amount_formatted ? String(q.monthly_amount_formatted) : '';
        socialSetStatus(message || (count > 0 ? `${count} monthly numbers available${price ? ` • ${price}/month` : ''}` : 'No monthly numbers found.'));
        socialEls.grid.innerHTML = `
            <div class="sn-rental-detail">
                <div class="sn-rental-main">
                    <div class="sn-kicker">Monthly rental</div>
                    <div class="sn-rental-title">${esc(String(socialRentState.selectedApp?.name || ''))} in ${esc(String(countryName || ''))}</div>
                    <div class="sn-rental-copy">Choose a provider, rent the number for one month, then activate it when you are ready to receive messages. Auto-renew uses wallet balance.</div>
                    <div class="sn-metric-row">
                        <div class="sn-metric">
                            <div class="k">Available</div>
                            <div class="v">${esc(count > 0 ? String(count) : 'None')}</div>
                        </div>
                        <div class="sn-metric">
                            <div class="k">Monthly price</div>
                            <div class="v">${esc(price || 'Unavailable')}</div>
                        </div>
                        <div class="sn-metric">
                            <div class="k">Period</div>
                            <div class="v">1 month</div>
                        </div>
                    </div>
                </div>
                <div class="sn-rental-side">
                    <div>
                        <div class="sn-field-label">Provider</div>
                        <select id="snrProviderSelect" class="sn-provider-select">${providerOptions}</select>
                        <div class="vn-status">${esc(count > 0 ? `${count} available • ${price}/month` : (message || 'No availability.'))}</div>
                    </div>
                    <div class="sn-rental-actions">
                        <button type="button" class="vn-btn" id="snrBackCountries">Back</button>
                        <button type="button" class="vn-btn primary" id="snrBuyBtn" ${count > 0 ? '' : 'disabled'}>Rent for 1 month</button>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('snrBackCountries')?.addEventListener('click', () => {
            socialRentState.view = 'countries';
            socialRentRenderCountries();
        });
        document.getElementById('snrProviderSelect')?.addEventListener('change', (e) => {
            socialRentState.selectedProvider = String(e.target.value || 'all_providers');
            socialRentLoadQuote().catch(() => {});
        });
        document.getElementById('snrBuyBtn')?.addEventListener('click', () => socialRentBuy());
    };
    const socialRentBuy = async () => {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        socialSetStatus('Renting monthly number…');
        const r = await socialFetchJson(socialRentApi.buy, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                product: String(socialRentState.selectedApp?.key || ''),
                country: String(socialRentState.selectedCountry || ''),
                provider: String(socialRentState.selectedProvider || 'all_providers'),
            }),
        });
        if (!r.ok) {
            socialSetStatus(String(r.json && r.json.message ? r.json.message : 'Monthly rental failed.'));
            return;
        }
        socialRentLoadRentals(true).catch(() => {});
    };
    const socialRentRenderRentalCard = (rental) => {
        const card = document.createElement('div');
        card.className = 'card';
        const messages = Array.isArray(rental.sms) ? rental.sms : [];
        const last = messages.length ? messages[messages.length - 1] : null;
        card.innerHTML = `
            <div class="card-left">
                <div class="flag" style="background:${esc(socialBrandBg(rental.product || ''))};border-color:rgb(var(--surface-rgb) / .16)">
                    <img src="${safeImgSrc(socialIconUrl(rental.product || ''))}" alt="${esc(String(rental.product_name || ''))}">
                </div>
                <div class="meta">
                    <div class="name">${esc(String(rental.product_name || rental.product || ''))} • ${esc(String(rental.phone || ''))}</div>
                    <div class="subtext">${esc(String(rental.country_name || rental.country || ''))} • ${esc(String(rental.status || ''))} • ${esc(String(rental.monthly_amount_formatted || ''))}/month</div>
                    <div class="subtext">${rental.current_period_end ? `Renews ${esc(new Date(rental.current_period_end).toLocaleDateString())}` : ''}${last ? ` • Last SMS: ${esc(String(last.text || ''))}` : ''}</div>
                </div>
            </div>
            <div class="card-right">
                <button class="mini-btn" type="button" data-snr-activate="${esc(String(rental.id || ''))}">Activate</button>
                <button class="mini-btn" type="button" data-snr-sms="${esc(String(rental.id || ''))}">SMS</button>
                <button class="mini-btn" type="button" data-snr-cancel="${esc(String(rental.id || ''))}">Cancel renew</button>
            </div>
        `;
        socialEls.grid.appendChild(card);
    };
    const socialRentLoadRentals = async (reset = false) => {
        socialSetMode('rent');
        socialRentState.view = 'rentals';
        if (reset) socialRentState.rentalsOffset = 0;
        socialSetStatus('Loading monthly rentals…');
        const r = await socialFetchJson(`${socialRentApi.list}?limit=20&offset=${encodeURIComponent(String(socialRentState.rentalsOffset || 0))}`);
        if (!r.ok) {
            socialSetStatus(String(r.json && r.json.message ? r.json.message : 'Failed to load monthly rentals.'));
            return;
        }
        const items = Array.isArray(r.json.items) ? r.json.items : [];
        if (reset) socialEls.grid.innerHTML = '';
        if (items.length === 0 && reset) {
            socialEls.grid.innerHTML = `<div class="no-results" style="grid-column:1/-1;padding:44px 12px"><p>No monthly social numbers yet.</p></div>`;
            socialSetStatus('');
            return;
        }
        items.forEach(socialRentRenderRentalCard);
        socialRentState.rentalsOffset = (socialRentState.rentalsOffset || 0) + items.length;
        socialSetStatus('');
        socialEls.grid.insertAdjacentHTML('beforeend', `<div style="grid-column:1/-1;display:flex;gap:10px;justify-content:center;margin-top:10px"><button class="load-more-btn" type="button" id="snrMoreBtn">Load more</button><button class="load-more-btn" type="button" id="snrBackAppsBtn">Back to monthly apps</button></div>`);
        document.getElementById('snrMoreBtn')?.addEventListener('click', () => socialRentLoadRentals(false));
        document.getElementById('snrBackAppsBtn')?.addEventListener('click', () => socialRentShowApps().catch(() => {}));
        Array.from(document.querySelectorAll('[data-snr-activate]')).forEach((btn) => btn.addEventListener('click', () => socialRentAction(btn.getAttribute('data-snr-activate'), 'activate')));
        Array.from(document.querySelectorAll('[data-snr-sms]')).forEach((btn) => btn.addEventListener('click', () => socialRentAction(btn.getAttribute('data-snr-sms'), 'sms')));
        Array.from(document.querySelectorAll('[data-snr-cancel]')).forEach((btn) => btn.addEventListener('click', () => socialRentAction(btn.getAttribute('data-snr-cancel'), 'cancel')));
    };
    const socialRentAction = async (id, action) => {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        const method = action === 'sms' ? 'GET' : 'POST';
        const suffix = action === 'sms' ? '/sms' : `/${action}`;
        socialSetStatus(action === 'sms' ? 'Refreshing SMS…' : 'Updating rental…');
        await socialFetchJson(`${socialRentApi.activateBase}${encodeURIComponent(String(id || ''))}${suffix}`, {
            method,
            headers: method === 'POST' ? { 'X-CSRF-TOKEN': csrf } : {},
        });
        socialRentLoadRentals(true).catch(() => {});
    };

    const socialRenderSocialHome = async () => {
        socialSetMode('otp');
        await socialShowApps();
    };

    const updateLoadMoreUi = () => {
        if (viewMode !== 'assets') {
            loadMoreWrap.classList.remove('show');
            return;
        }
        if (activeTab === 'social') {
            loadMoreWrap.classList.remove('show');
            return;
        }
        const tabState = state[activeTab];
        const show = !!(tabState && tabState.hasMore && !tabState.loading && tabState.q === (searchInput.value || '').trim());
        loadMoreWrap.classList.toggle('show', show);
    };

    const updateEsimsLoadMoreUi = () => {
        const show = !!(viewMode === 'myesims' && esimsState.hasMore && !esimsState.loading);
        esimsLoadMoreWrap.classList.toggle('show', show);
    };

    const esimCopyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    const esimChipIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><rect x="8.5" y="11" width="7" height="6" rx="1.2"/></svg>';

    // Same markup as resources/views/partials/esim-card.blade.php.
    const createEsimCard = (item) => {
        const card = document.createElement('article');
        card.className = 'esim-card';
        const e = item.esim || {};
        const str = (k) => String(e[k] || '').trim();
        const status = item.status === 'expired' ? 'expired' : (item.status === 'processing' ? 'processing' : 'valid');
        const [statusLabel, statusTone] = { valid: ['Active', 'success'], expired: ['Expired', ''], processing: ['Processing', 'info'] }[status];
        card.dataset.status = status;
        const bundle = item.bundle || {};
        const title = bundle.name || 'eSIM';
        const sub = [bundle.data, bundle.validity].filter(Boolean).join(' · ');
        const iccid = str('iccid');
        const canRenew = (e.can_renew === true || e.can_renew === false) ? e.can_renew : null;
        const expiresAt = item.expires_at ? new Date(item.expires_at) : null;
        const expiresLabel = expiresAt && !isNaN(expiresAt) ? expiresAt.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

        const qrHref = safeHref(str('qr_code_url'));
        const iosHref = safeHref(str('direct_installation_link_ios'));
        const androidHref = safeHref(str('direct_installation_link_android'));
        const qrImg = safeImgSrc(str('qr_code_data_url'));
        const esimId = str('esim_id');
        const assetType = String(item.asset_type || '');
        const assetId = String(item.asset_id || '');
        const topupUrl = (canRenew === true && assetType && assetId && esimId)
            ? `/assets/${encodeURIComponent(assetType)}/${encodeURIComponent(assetId)}?topup_esim_id=${encodeURIComponent(esimId)}`
            : '';

        const details = [
            ['SM-DP+ address', str('smdp_address')],
            ['Activation code', str('activation_code')],
            ['LPA string', str('lpa') || str('qr_payload')],
            ['ICCID', iccid],
            ['eSIM ID', esimId],
            ['PUK', str('puk_code')],
            ['Number', str('number')],
            ['Provider status', str('esim_status')],
            ['Renewable', canRenew === null ? '' : (canRenew ? 'Yes' : 'No')],
        ].filter(([, v]) => v);
        const copyBtn = (label, value) => `<button type="button" class="ui-copy" data-copy="${esc(value)}" data-copy-label="${esc(label)}" aria-label="Copy ${esc(label)}">${esimCopyIcon}</button>`;
        const detailRows = details.map(([label, value]) => `<div><dt>${esc(label)}</dt><dd><span class="ui-mono" title="${esc(value)}">${esc(value)}</span>${['Renewable', 'Provider status'].includes(label) ? '' : copyBtn(label, value)}</dd></div>`).join('');

        const actions = [
            iosHref ? `<a class="ui-btn ui-btn--primary ui-btn--sm" href="${iosHref}" target="_blank" rel="noopener noreferrer">Install on iPhone</a>` : '',
            androidHref ? `<a class="ui-btn ui-btn--sm ${iosHref ? 'ui-btn--secondary' : 'ui-btn--primary'}" href="${androidHref}" target="_blank" rel="noopener noreferrer">Install on Android</a>` : '',
            qrHref ? `<a class="ui-btn ui-btn--secondary ui-btn--sm" href="${qrHref}" target="_blank" rel="noopener noreferrer">Open QR</a>` : '',
            topupUrl ? `<a class="ui-btn ui-btn--secondary ui-btn--sm" href="${topupUrl}">Top up</a>` : '',
        ].filter(Boolean).join('');

        card.innerHTML = `
            <div class="esim-head">
                <span class="esim-chip" aria-hidden="true">${esimChipIcon}</span>
                <div class="esim-titles">
                    <h3 class="esim-title">${esc(title)}</h3>
                    ${sub ? `<p class="esim-sub">${esc(sub)}</p>` : ''}
                </div>
                <span class="ui-pill ${statusTone ? `ui-pill--${statusTone}` : ''} ${status === 'processing' ? 'ui-pill--pulse' : ''}"><span class="ui-pill__dot"></span>${statusLabel}</span>
            </div>
            <dl class="esim-facts">
                <div><dt>${status === 'expired' ? 'Expired' : 'Expires'}</dt><dd>${esc(expiresLabel)}</dd></div>
                <div><dt>ICCID</dt><dd>${iccid ? `<span class="ui-mono" title="${esc(iccid)}">•••• ${esc(iccid.slice(-6))}</span>${copyBtn('ICCID', iccid)}` : '<span>—</span>'}</dd></div>
            </dl>
            ${actions ? `<div class="esim-actions">${actions}</div>` : ''}
            ${(detailRows || qrImg) ? `
            <details class="ui-disclosure esim-more">
                <summary>Installation details</summary>
                <div class="ui-disclosure__body">
                    ${qrImg ? `<div class="esim-qr"><img alt="eSIM QR code for ${esc(title)}" src="${qrImg}"></div>` : ''}
                    <dl class="ui-kv">${detailRows}</dl>
                </div>
            </details>` : ''}
        `;
        return card;
    };

    const esimSkeletonHtml = `
        <div class="esim-head">
            <span class="esim-chip skeleton"></span>
            <div class="esim-titles"><div class="skeleton" style="width:60%;height:14px"></div><div class="skeleton" style="width:40%;height:11px;margin-top:8px"></div></div>
            <span class="skeleton" style="width:64px;height:24px;border-radius:999px"></span>
        </div>
        <div class="esim-facts"><div><div class="skeleton" style="width:50%;height:10px"></div><div class="skeleton" style="width:70%;height:13px;margin-top:8px"></div></div><div><div class="skeleton" style="width:40%;height:10px"></div><div class="skeleton" style="width:65%;height:13px;margin-top:8px"></div></div></div>
        <div class="esim-actions"><span class="skeleton" style="width:128px;height:36px;border-radius:10px"></span><span class="skeleton" style="width:84px;height:36px;border-radius:10px"></span></div>
    `;

    const setEsimsSkeleton = () => {
        myEsimsGrid.innerHTML = '';
        for (let i = 0; i < 6; i++) {
            const sk = document.createElement('div');
            sk.className = 'esim-card';
            sk.setAttribute('data-esim-skeleton', 'initial');
            sk.innerHTML = esimSkeletonHtml;
            myEsimsGrid.appendChild(sk);
        }
    };

    const appendEsimsLoadMoreSkeleton = (count = 3) => {
        myEsimsGrid.querySelectorAll('[data-esim-skeleton="loadmore"]').forEach((el) => el.remove());
        for (let i = 0; i < count; i++) {
            const sk = document.createElement('div');
            sk.className = 'esim-card';
            sk.setAttribute('data-esim-skeleton', 'loadmore');
            sk.innerHTML = esimSkeletonHtml;
            myEsimsGrid.appendChild(sk);
        }
    };

    const fetchMyEsims = async ({ reset = false } = {}) => {
        if (esimsState.loading) return;
        const hasRenderedCards = myEsimsGrid.querySelectorAll('.esim-card:not([data-esim-skeleton])').length > 0;
        if (reset) {
            esimsState.page = 0;
            esimsState.hasMore = true;
            setEsimsSkeleton();
            noEsims.classList.add('hidden');
        }
        if (!esimsState.hasMore) {
            updateEsimsLoadMoreUi();
            return;
        }
        esimsState.loading = true;
        updateEsimsLoadMoreUi();
        if (!reset && esimsState.page > 0) {
            appendEsimsLoadMoreSkeleton(3);
        }

        try {
            const nextPage = esimsState.page + 1;
            const url = `/api/my-esims?filter=${encodeURIComponent(esimsState.filter)}&page=${nextPage}&per_page=10`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                myEsimsGrid.innerHTML = '<div class="no-results"><p>Failed to load eSIMs.</p></div>';
                esimsState.hasMore = false;
                return;
            }
            const items = Array.isArray(json.items) ? json.items : [];
            if (nextPage === 1 && items.length === 0 && hasRenderedCards && !reset) {
                esimsState.page = 1;
                esimsState.hasMore = false;
                noEsims.classList.add('hidden');
                return;
            }
            if (nextPage === 1) {
                myEsimsGrid.innerHTML = '';
            }
            myEsimsGrid.querySelectorAll('[data-esim-skeleton="loadmore"]').forEach((el) => el.remove());
            items.forEach((it) => myEsimsGrid.appendChild(createEsimCard(it)));
            esimsState.page = nextPage;
            esimsState.hasMore = !!json.has_more;
            noEsims.classList.toggle('hidden', (items.length > 0) || nextPage > 1);
        } catch (e) {
            if (!hasRenderedCards) {
                myEsimsGrid.innerHTML = '<div class="no-results"><p>Failed to load eSIMs.</p></div>';
            }
            esimsState.hasMore = false;
        } finally {
            esimsState.loading = false;
            myEsimsGrid.querySelectorAll('[data-esim-skeleton="loadmore"]').forEach((el) => el.remove());
            updateEsimsLoadMoreUi();
        }
    };

    const renderInitialMyEsims = () => {
        if (initialMyEsimsRendered || esimsState.filter !== 'valid') {
            return false;
        }
        const items = Array.isArray(initialMyEsims.items) ? initialMyEsims.items : [];
        if (items.length === 0) {
            return false;
        }

        myEsimsGrid.innerHTML = '';
        items.forEach((it) => myEsimsGrid.appendChild(createEsimCard(it)));
        esimsState.page = Number(initialMyEsims.page || 1);
        esimsState.hasMore = !!initialMyEsims.has_more;
        noEsims.classList.add('hidden');
        initialMyEsimsRendered = true;
        updateEsimsLoadMoreUi();

        return true;
    };

    const setSkeleton = (key) => {
        const grid = grids[key];
        if (!grid) return;
        grid.innerHTML = '';
        const count = key === 'countries' ? 9 : 6;
        for (let i = 0; i < count; i++) {
            const sk = document.createElement('div');
            sk.className = 'skeleton-card skeleton-placeholder';
            sk.innerHTML = `
                <div class="card-left">
                    <div class="skeleton-flag skeleton"></div>
                    <div class="meta">
                        <div class="skeleton-text-lg skeleton"></div>
                        <div class="skeleton-text-sm skeleton"></div>
                    </div>
                </div>
                <div class="card-right">
                    <div class="skeleton-text-sm skeleton"></div>
                    <div class="skeleton-btn skeleton"></div>
                </div>
            `;
            grid.appendChild(sk);
        }
    };

    const vnShowView = (view) => {
        vnState.view = view === 'numbers' ? 'numbers' : (view === 'my' ? 'my' : (view === 'inbox' ? 'inbox' : 'countries'));
        vnEls.countriesView.classList.toggle('vn-hidden', vnState.view !== 'countries');
        vnEls.numbersView.classList.toggle('vn-hidden', vnState.view !== 'numbers');
        vnEls.myView.classList.toggle('vn-hidden', vnState.view !== 'my');
        vnEls.inboxView.classList.toggle('vn-hidden', vnState.view !== 'inbox');
    };

    const vnSetSkeleton = (target) => {
        const grid = target === 'numbers' ? vnEls.numbersGrid : vnEls.countriesGrid;
        if (!grid) return;
        grid.innerHTML = '';
        const count = target === 'numbers' ? 9 : 9;
        for (let i = 0; i < count; i++) {
            const sk = document.createElement('div');
            sk.className = 'vn-skel-card';
            sk.innerHTML = `
                <div class="vn-skel-left">
                    <div class="vn-skel-flag skeleton"></div>
                    <div class="vn-skel-lines">
                        <div class="vn-skel-line-lg skeleton"></div>
                        <div class="vn-skel-line-sm skeleton"></div>
                    </div>
                </div>
                <div class="vn-skel-btn skeleton"></div>
            `;
            grid.appendChild(sk);
        }
    };

    const vnSetMySkeleton = () => {
        if (!vnEls.myGrid) return;
        vnEls.myGrid.innerHTML = '';
        for (let i = 0; i < 6; i++) {
            const sk = document.createElement('div');
            sk.className = 'vn-skel-card';
            sk.innerHTML = `
                <div class="vn-skel-left">
                    <div class="vn-skel-flag skeleton"></div>
                    <div class="vn-skel-lines">
                        <div class="vn-skel-line-lg skeleton"></div>
                        <div class="vn-skel-line-sm skeleton"></div>
                    </div>
                </div>
                <div class="vn-skel-btn skeleton"></div>
            `;
            vnEls.myGrid.appendChild(sk);
        }
    };

    const vnSetStatus = (el, text) => {
        if (!el) return;
        el.textContent = text || '';
    };

    const vnRenderCountryCard = (item) => {
        const code = String(item.country_code || '');
        const name = String(item.country_name || code);
        const displayName = name.length > 28 ? `${name.slice(0, 28)}…` : name;
        const flag = String(item.flag || '☎️');
        const price = String(item.starting_price_formatted || '');

        const card = document.createElement('div');
        card.className = 'card';
        card.setAttribute('data-country', code);
        card.innerHTML = `
            <div class="card-left">
                <div class="flag"><span>${esc(flag)}</span></div>
                <div class="meta">
                    <div class="name" title="${esc(name)}">${esc(displayName)}</div>
                    <div class="subtext">From ${esc(price)}/mo</div>
                </div>
            </div>
            <div class="card-right">
                <button class="mini-btn" type="button">View Plans</button>
            </div>
        `;
        card.querySelector('button')?.addEventListener('click', () => vnOpenCountry(code));
        return card;
    };

    const vnRenderNumberCard = (item) => {
        const phone = String(item.phone_number || '');
        const friendly = String(item.friendly_name || phone);
        const region = String(item.region || '');
        const locality = String(item.locality || '');
        const loc = [locality, region].filter(Boolean).join(', ');
        const locShort = loc.length > 18 ? `${loc.slice(0, 18)}…` : loc;
        const numberType = String(item.number_type || '');
        const caps = item.capabilities && typeof item.capabilities === 'object' ? item.capabilities : {};
        const capSms = !!caps.SMS || !!caps.sms || !!caps.Sms;
        const capVoice = !!caps.Voice || !!caps.voice;
        const fee = String(item.monthly_fee_formatted || '');

        const pid = vnState.selected.product_id;
        const buyUrl = `/dashboard/virtual-numbers/checkout?product_id=${encodeURIComponent(String(pid))}&phone_number=${encodeURIComponent(phone)}&number_type=${encodeURIComponent(numberType)}`;

        const tags = [
            numberType ? `<span class="vn-tag">${esc(numberType)}</span>` : '',
            capSms ? `<span class="vn-tag">SMS</span>` : '',
            capVoice ? `<span class="vn-tag">VOICE</span>` : '',
        ].filter(Boolean).join('');

        const card = document.createElement('div');
        card.className = 'vn-number-card';
        card.innerHTML = `
            <div class="vn-number-meta">
                <div class="vn-number-title">${esc(friendly)}</div>
                <div class="subtext" title="${esc(loc)}">${esc(locShort)}</div>
                <div class="vn-tags">${tags}</div>
            </div>
            <a class="vn-buy" href="${buyUrl}">${fee ? `Buy ${esc(fee)}` : 'Buy'}</a>
        `;
        return card;
    };

    const vnUpdateCountriesLoadMoreUi = () => {
        vnEls.countriesLoadMoreWrap.classList.toggle('show', !!(vnState.view === 'countries' && vnState.countries.hasMore && !vnState.countries.loading));
    };

    const vnUpdateNumbersLoadMoreUi = () => {
        vnEls.numbersLoadMoreWrap.classList.toggle('show', !!(vnState.view === 'numbers' && vnState.numbers.hasMore && !vnState.numbers.loading));
    };

    const vnLoadCountries = async ({ reset = false } = {}) => {
        if (vnState.countries.loading) return;
        const q = String(vnEls.countriesSearch.value || '').trim();
        if (reset) {
            vnState.countries.page = 0;
            vnState.countries.hasMore = true;
            vnSetSkeleton('countries');
            vnState.countries.q = q;
        }
        if (!vnState.countries.hasMore) {
            vnUpdateCountriesLoadMoreUi();
            return;
        }

        vnState.countries.loading = true;
        vnUpdateCountriesLoadMoreUi();
        vnSetStatus(vnEls.status, vnState.countries.page === 0 ? 'Loading…' : 'Loading more…');

        try {
            const nextPage = vnState.countries.page + 1;
            const url = `/api/virtual-numbers/countries?context=dashboard&page=${encodeURIComponent(String(nextPage))}&per_page=60${q ? `&q=${encodeURIComponent(q)}` : ''}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                vnSetStatus(vnEls.status, json.message || 'Failed to load countries.');
                vnState.countries.hasMore = false;
                return;
            }
            const items = Array.isArray(json.items) ? json.items : [];
            if (nextPage === 1) {
                vnEls.countriesGrid.innerHTML = '';
            }
            items.forEach((it) => vnEls.countriesGrid.appendChild(vnRenderCountryCard(it)));
            vnState.countries.page = nextPage;
            vnState.countries.hasMore = !!json.has_more;
            vnSetStatus(vnEls.status, items.length === 0 && nextPage === 1 ? 'No countries found.' : '');
        } catch (e) {
            vnSetStatus(vnEls.status, 'Failed to load countries.');
            vnState.countries.hasMore = false;
        } finally {
            vnState.countries.loading = false;
            vnUpdateCountriesLoadMoreUi();
        }
    };

    const vnOpenCountry = async (countryCode) => {
        const code = String(countryCode || '').toUpperCase();
        if (!/^[A-Z]{2}$/.test(code)) return;
        vnSetStatus(vnEls.numbersStatus, 'Loading…');
        vnSetSkeleton('numbers');
        vnState.numbers.page = 0;
        vnState.numbers.hasMore = true;
        vnState.numbers.q = '';
        vnEls.numbersSearch.value = '';

        try {
            const res = await fetch(`/api/virtual-numbers/country/${encodeURIComponent(code)}`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                vnSetStatus(vnEls.numbersStatus, json.message || 'Failed to load country.');
                return;
            }
            vnState.selected = {
                country_code: String(json.country_code || code),
                country_name: String(json.country_name || code),
                product_id: Number(json.product_id || 0),
                starting_from: String(json.starting_from || ''),
            };
            vnEls.numbersTitle.textContent = `Available phone numbers · ${vnState.selected.country_name}`;
            vnEls.numbersSub.textContent = vnState.selected.starting_from ? `Starting from ${vnState.selected.starting_from}/mo` : '';
            vnShowView('numbers');
            vnLoadNumbers({ reset: true });
        } catch (e) {
            vnSetStatus(vnEls.numbersStatus, 'Failed to load country.');
        }
    };

    const vnLoadNumbers = async ({ reset = false } = {}) => {
        if (vnState.numbers.loading) return;
        const pid = vnState.selected.product_id;
        if (!pid) return;
        const q = String(vnEls.numbersSearch.value || '').trim().toLowerCase();
        if (reset) {
            vnState.numbers.page = 0;
            vnState.numbers.hasMore = true;
            vnSetSkeleton('numbers');
            vnState.numbers.q = q;
        }
        if (!vnState.numbers.hasMore) {
            vnUpdateNumbersLoadMoreUi();
            return;
        }

        vnState.numbers.loading = true;
        vnUpdateNumbersLoadMoreUi();
        vnSetStatus(vnEls.numbersStatus, vnState.numbers.page === 0 ? 'Loading…' : 'Loading more…');

        try {
            const nextPage = vnState.numbers.page;
            const url = `/api/virtual-numbers/available?product_id=${encodeURIComponent(String(pid))}&limit=30&page=${encodeURIComponent(String(nextPage))}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                vnSetStatus(vnEls.numbersStatus, json.message || 'Failed to load numbers.');
                vnState.numbers.hasMore = false;
                return;
            }
            const itemsRaw = Array.isArray(json.items) ? json.items : [];
            const items = q
                ? itemsRaw.filter((it) => {
                    const p = String(it.phone_number || '').toLowerCase();
                    const f = String(it.friendly_name || '').toLowerCase();
                    const r = String(it.region || '').toLowerCase();
                    const l = String(it.locality || '').toLowerCase();
                    return p.includes(q) || f.includes(q) || r.includes(q) || l.includes(q);
                })
                : itemsRaw;
            if (nextPage === 0) {
                vnEls.numbersGrid.innerHTML = '';
            }
            items.forEach((it) => vnEls.numbersGrid.appendChild(vnRenderNumberCard(it)));
            vnState.numbers.page = nextPage + 1;
            vnState.numbers.hasMore = !!json.has_more;
            vnSetStatus(vnEls.numbersStatus, items.length === 0 && nextPage === 0 ? 'No numbers found.' : '');
        } catch (e) {
            vnSetStatus(vnEls.numbersStatus, 'Failed to load numbers.');
            vnState.numbers.hasMore = false;
        } finally {
            vnState.numbers.loading = false;
            vnUpdateNumbersLoadMoreUi();
        }
    };

    const vnLoadMy = async () => {
        vnSetStatus(vnEls.myStatus, 'Loading…');
        vnSetMySkeleton();
        try {
            const res = await fetch('/api/virtual-numbers/my', { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                vnSetStatus(vnEls.myStatus, json.message || 'Failed to load subscriptions.');
                vnEls.myGrid.innerHTML = '';
                return;
            }
            const items = Array.isArray(json.items) ? json.items : [];
            if (items.length === 0) {
                vnSetStatus(vnEls.myStatus, 'No subscriptions yet.');
                vnEls.myGrid.innerHTML = '';
                return;
            }
            vnEls.myGrid.innerHTML = '';
            vnSetStatus(vnEls.myStatus, '');

            items.forEach((it) => {
                const id = Number(it.id || 0);
                const phone = String(it.phone_number || '');
                const status = String(it.status || '');
                const label = String(it.plan_label || 'Virtual Number');
                const iso = String(it.country_iso || '');
                const end = String(it.current_period_end || '');
                const priceMinor = Number(it.monthly_amount_minor || 0);
                const cur = String(it.currency || 'USD').toUpperCase();
                const price = cur + ' ' + (priceMinor / 100).toFixed(2);

                const card = document.createElement('div');
                card.className = 'vn-number-card';
                card.innerHTML = `
                    <div class="vn-number-meta">
                        <div class="vn-number-title">${esc(phone || 'Pending provisioning')}</div>
                        <div class="subtext">${esc(iso)} · ${esc(label)} · ${esc(price)}/mo</div>
                        <div class="subtext">Next renewal: ${end ? esc(new Date(end).toLocaleString()) : '—'}</div>
                        <div class="vn-tags"><span class="vn-tag">${esc(status)}</span></div>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                        <button class="vn-btn" type="button" data-inbox-id="${esc(id)}">Inbox</button>
                        <button class="vn-buy" type="button" data-cancel-id="${esc(id)}" ${status === 'canceled' ? 'disabled' : ''}>Cancel</button>
                    </div>
                `;
                card.querySelector('[data-inbox-id]')?.addEventListener('click', () => {
                    vnOpenInbox(id, phone);
                });
                card.querySelector('[data-cancel-id]')?.addEventListener('click', async () => {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    try {
                        const r = await fetch(`/api/virtual-numbers/${encodeURIComponent(String(id))}/cancel`, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                        });
                        if (!r.ok) return;
                        vnLoadMy();
                    } catch (e) {
                    }
                });
                vnEls.myGrid.appendChild(card);
            });
        } catch (e) {
            vnSetStatus(vnEls.myStatus, 'Failed to load subscriptions.');
        }
    };

    const vnRenderChat = (items) => {
        if (!vnEls.chat) return;
        vnEls.chat.innerHTML = '';
        const list = Array.isArray(items) ? items.slice().reverse() : [];
        if (list.length === 0) {
            const d = document.createElement('div');
            d.className = 'vn-meta';
            d.textContent = 'No messages yet.';
            vnEls.chat.appendChild(d);
            return;
        }

        list.forEach((m) => {
            const id = Number(m.id || 0);
            const dir = String(m.direction || 'inbound');
            const type = String(m.message_type || 'sms');
            const body = String(m.body || '');
            const created = String(m.created_at || '');
            const from = String(m.from || '');
            const to = String(m.to || '');
            const mediaCount = Number(m.media_count || 0);
            const hasRecording = !!m.has_recording;

            const wrap = document.createElement('div');
            wrap.className = `vn-msg ${dir === 'outbound' ? 'out' : 'in'}`;

            const bubble = document.createElement('div');
            bubble.className = 'vn-bubble';
            bubble.textContent = type === 'voicemail' && body === '' ? 'Voicemail' : (body || '');
            wrap.appendChild(bubble);

            if (mediaCount > 0) {
                const media = document.createElement('div');
                media.className = 'vn-media';
                for (let i = 0; i < mediaCount; i++) {
                    const img = document.createElement('img');
                    img.loading = 'lazy';
                    img.src = `/api/virtual-numbers/messages/${encodeURIComponent(String(id))}/media/${encodeURIComponent(String(i))}`;
                    media.appendChild(img);
                }
                wrap.appendChild(media);
            }

            if (hasRecording) {
                const audio = document.createElement('audio');
                audio.controls = true;
                audio.src = `/api/virtual-numbers/messages/${encodeURIComponent(String(id))}/recording`;
                wrap.appendChild(audio);
            }

            const meta = document.createElement('div');
            meta.className = 'vn-meta';
            const when = created ? new Date(created).toLocaleString() : '';
            meta.textContent = `${dir === 'outbound' ? 'To' : 'From'} ${dir === 'outbound' ? to : from}${when ? ` · ${when}` : ''}`;
            wrap.appendChild(meta);

            vnEls.chat.appendChild(wrap);
        });

        vnEls.chat.scrollTop = vnEls.chat.scrollHeight;
    };

    const vnLoadInbox = async () => {
        const subId = Number(vnState.inbox.subscription_id || 0);
        if (!subId) return;
        vnSetStatus(vnEls.inboxStatus, 'Loading…');
        try {
            const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(String(subId))}/messages?per_page=120`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                vnSetStatus(vnEls.inboxStatus, json.message || 'Failed to load messages.');
                return;
            }
            const items = Array.isArray(json.items) ? json.items : [];
            vnRenderChat(items);
            vnSetStatus(vnEls.inboxStatus, '');
            const lastInbound = items.find((x) => String(x.direction || '') !== 'outbound');
            const lastFrom = lastInbound ? String(lastInbound.from || '') : '';
            if (vnEls.composeTo) {
                vnEls.composeTo.value = vnState.inbox.last_to || lastFrom || vnEls.composeTo.value || '';
            }
        } catch (e) {
            vnSetStatus(vnEls.inboxStatus, 'Failed to load messages.');
        }
    };

    const vnOpenInbox = (subscriptionId, phoneNumber) => {
        vnState.inbox.subscription_id = Number(subscriptionId || 0);
        vnState.inbox.phone_number = String(phoneNumber || '');
        if (vnEls.inboxTitle) vnEls.inboxTitle.textContent = `Inbox · ${vnState.inbox.phone_number || ''}`.trim();
        if (vnEls.inboxSub) vnEls.inboxSub.textContent = 'Receive SMS/MMS and voicemail here.';
        if (vnEls.composeBody) vnEls.composeBody.value = '';
        if (vnEls.composeMedia) vnEls.composeMedia.value = '';
        if (vnEls.forwardEmail) vnEls.forwardEmail.value = '';
        if (vnEls.forwardPhone) vnEls.forwardPhone.value = '';
        vnShowView('inbox');
        vnLoadForwarding();
        vnLoadInbox();
    };

    const vnLoadForwarding = async () => {
        const subId = Number(vnState.inbox.subscription_id || 0);
        if (!subId) return;
        try {
            const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(String(subId))}/settings`, { headers: { 'Accept': 'application/json' } });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                return;
            }
            const s = json.settings && typeof json.settings === 'object' ? json.settings : {};
            if (vnEls.forwardEmail) vnEls.forwardEmail.value = String(s.forward_to_email || '');
            if (vnEls.forwardPhone) vnEls.forwardPhone.value = String(s.forward_to_phone || '');
        } catch (e) {
        }
    };

    const vnSaveForwarding = async () => {
        const subId = Number(vnState.inbox.subscription_id || 0);
        if (!subId) return;
        const email = vnEls.forwardEmail ? String(vnEls.forwardEmail.value || '').trim() : '';
        const phone = vnEls.forwardPhone ? String(vnEls.forwardPhone.value || '').trim() : '';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        if (vnEls.saveForwardBtn) vnEls.saveForwardBtn.disabled = true;
        vnSetStatus(vnEls.inboxStatus, 'Saving…');
        try {
            const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(String(subId))}/settings`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ forward_to_email: email, forward_to_phone: phone })
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                vnSetStatus(vnEls.inboxStatus, json.message || 'Failed to save forwarding.');
                return;
            }
            vnSetStatus(vnEls.inboxStatus, 'Forwarding saved.');
            window.setTimeout(() => {
                if (vnState.view === 'inbox') vnSetStatus(vnEls.inboxStatus, '');
            }, 1200);
        } catch (e) {
            vnSetStatus(vnEls.inboxStatus, 'Failed to save forwarding.');
        } finally {
            if (vnEls.saveForwardBtn) vnEls.saveForwardBtn.disabled = false;
        }
    };

    const vnSend = async () => {
        const subId = Number(vnState.inbox.subscription_id || 0);
        if (!subId) return;
        const to = vnEls.composeTo ? String(vnEls.composeTo.value || '').trim() : '';
        const body = vnEls.composeBody ? String(vnEls.composeBody.value || '').trim() : '';
        const mediaUrl = vnEls.composeMedia ? String(vnEls.composeMedia.value || '').trim() : '';
        const mediaUrls = mediaUrl ? [mediaUrl] : [];
        if (!to || (!body && mediaUrls.length === 0)) {
            vnSetStatus(vnEls.inboxStatus, 'Enter a To number and a message.');
            return;
        }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        if (vnEls.sendBtn) vnEls.sendBtn.disabled = true;
        vnSetStatus(vnEls.inboxStatus, 'Sending…');
        try {
            const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(String(subId))}/messages/send`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ to, body, media_urls: mediaUrls })
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.ok) {
                vnSetStatus(vnEls.inboxStatus, json.message || 'Failed to send message.');
                return;
            }
            vnState.inbox.last_to = to;
            if (vnEls.composeBody) vnEls.composeBody.value = '';
            if (vnEls.composeMedia) vnEls.composeMedia.value = '';
            await vnLoadInbox();
        } catch (e) {
            vnSetStatus(vnEls.inboxStatus, 'Failed to send message.');
        } finally {
            if (vnEls.sendBtn) vnEls.sendBtn.disabled = false;
        }
    };

    let vnSearchTimer = null;
    vnEls.countriesSearch.addEventListener('input', () => {
        if (vnSearchTimer) window.clearTimeout(vnSearchTimer);
        vnSearchTimer = window.setTimeout(() => vnLoadCountries({ reset: true }), 250);
    });
    vnEls.countriesLoadMoreBtn.addEventListener('click', () => vnLoadCountries({ reset: false }));
    vnEls.refreshCountriesBtn.addEventListener('click', () => vnLoadCountries({ reset: true }));

    vnEls.backToCountriesBtn.addEventListener('click', () => vnShowView('countries'));
    vnEls.numbersLoadMoreBtn.addEventListener('click', () => vnLoadNumbers({ reset: false }));
    vnEls.refreshNumbersBtn.addEventListener('click', () => vnLoadNumbers({ reset: true }));
    vnEls.numbersSearch.addEventListener('input', () => {
        if (vnSearchTimer) window.clearTimeout(vnSearchTimer);
        vnSearchTimer = window.setTimeout(() => vnLoadNumbers({ reset: true }), 250);
    });

    vnEls.myBtnTop.addEventListener('click', () => { vnShowView('my'); vnLoadMy(); });
    vnEls.myBtn.addEventListener('click', () => { vnShowView('my'); vnLoadMy(); });
    vnEls.backFromMyBtn.addEventListener('click', () => {
        vnShowView(vnState.selected.product_id ? 'numbers' : 'countries');
    });
    vnEls.refreshMyBtn.addEventListener('click', () => vnLoadMy());

    vnEls.backFromInboxBtn?.addEventListener('click', () => {
        vnShowView('my');
    });
    vnEls.refreshInboxBtn?.addEventListener('click', () => vnLoadInbox());
    vnEls.sendBtn?.addEventListener('click', () => vnSend());
    vnEls.saveForwardBtn?.addEventListener('click', () => vnSaveForwarding());

    const fetchNextPage = async (tab, { reset = false } = {}) => {
        if (tab === 'virtual' || tab === 'social') {
            return;
        }
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
        }

        if (!tabState.hasMore) {
            updateLoadMoreUi();
            return;
        }

        tabState.loading = true;
        updateLoadMoreUi();

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

            const any = items.length > 0;
            noResultsSearch.classList.toggle('hidden', any || q === '');
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
        const normalizedMode = (mode === 'regions' || mode === 'social' || mode === 'countries') ? mode : 'countries';
        activeTab = normalizedMode;
        mode = normalizedMode;
        assetToggles.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-asset-toggle') === mode));
        assetSections.forEach(sec => sec.classList.toggle('hidden', sec.getAttribute('data-asset-section') !== mode));
        if (activeTab === 'social') {
            noResultsSearch.classList.add('hidden');
            loadMoreWrap.classList.remove('show');
            socialRenderSocialHome().catch(() => {});
            return;
        }

        const currentKey = tabToKey(activeTab);
        if (state[activeTab].page === 0) {
            fetchNextPage(activeTab, { reset: true });
        }
        const grid = grids[currentKey];
        const hasCards = grid && grid.querySelectorAll('.card, .vnum-card').length > 0;
        noResultsSearch.classList.toggle('hidden', hasCards || (searchInput.value || '').trim() === '');
        updateLoadMoreUi();
    };

    const syncMyEsims = async () => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 12000);
        try {
            const res = await fetch('/api/my-esims/sync', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({}),
                signal: controller.signal
            });
            if (!res.ok) {
                return false;
            }
            const json = await res.json().catch(() => ({}));
            return Number(json.updated || 0) > 0;
        } catch (e) {
            return false;
        } finally {
            window.clearTimeout(timeout);
        }
    };

    const setViewMode = (mode) => {
        viewMode = mode === 'myesims' ? 'myesims' : 'assets';
        const isAssets = viewMode === 'assets';
        assetsPanel.classList.toggle('hidden', !isAssets);
        myEsimsPanel.classList.toggle('hidden', isAssets);
        myEsimsNavBtn.classList.toggle('active', !isAssets);
        if (searchBar) searchBar.classList.toggle('hidden', !isAssets);
        searchInput.disabled = !isAssets;
        if (!isAssets) {
            assetToggles.forEach((b) => b.classList.remove('active'));
        }
        if (isAssets) {
            noEsims.classList.add('hidden');
            updateLoadMoreUi();
        } else {
            noResultsSearch.classList.add('hidden');
            loadMoreWrap.classList.remove('show');
            esimsState.page = 0;
            esimsState.hasMore = true;
            esimsState.loading = false;
            noEsims.classList.add('hidden');
            if (!renderInitialMyEsims()) {
                setEsimsSkeleton();
            }
            updateEsimsLoadMoreUi();
            fetchMyEsims({ reset: false });
            syncMyEsims().then((changed) => {
                if (changed && viewMode === 'myesims') {
                    fetchMyEsims({ reset: true });
                }
            });
        }
    };

    assetToggles.forEach(btn => {
        btn.addEventListener('click', () => {
            setViewMode('assets');
            setAssetSection(btn.getAttribute('data-asset-toggle'));
        });
    });

    if (socialEls.refreshBtn) {
        socialEls.refreshBtn.addEventListener('click', () => {
            if (socialState.isBuying) return;
            if (activeTab !== 'social') setAssetSection('social');
            if (socialState.mode === 'rent') {
                socialRentShowApps().catch(() => {});
            } else {
                socialRenderSocialHome().catch(() => {});
            }
        });
    }
    if (socialEls.otpModeBtn) {
        socialEls.otpModeBtn.addEventListener('click', () => {
            if (socialState.isBuying) return;
            if (activeTab !== 'social') setAssetSection('social');
            socialRenderSocialHome().catch(() => {});
        });
    }
    if (socialEls.rentModeBtn) {
        socialEls.rentModeBtn.addEventListener('click', () => {
            if (socialState.isBuying) return;
            if (activeTab !== 'social') setAssetSection('social');
            socialRentShowApps().catch(() => {});
        });
    }
    if (socialEls.rentalsBtn) {
        socialEls.rentalsBtn.addEventListener('click', () => {
            if (socialState.isBuying) return;
            if (activeTab !== 'social') setAssetSection('social');
            socialRentLoadRentals(true).catch(() => {});
        });
    }
    if (socialEls.ordersBtn) {
        socialEls.ordersBtn.addEventListener('click', () => {
            if (socialState.isBuying) return;
            if (activeTab !== 'social') setAssetSection('social');
            socialSetMode('otp');
            socialLoadHistory(true).catch(() => {});
        });
    }
    if (socialEls.inlineSearch) {
        let snTimer = null;
        socialEls.inlineSearch.addEventListener('input', () => {
            if (activeTab !== 'social') return;
            if (snTimer) window.clearTimeout(snTimer);
            snTimer = window.setTimeout(() => {
                if (socialState.mode === 'rent') {
                    if (socialRentState.view === 'countries') {
                        socialRentRenderCountries();
                    } else if (socialRentState.view === 'apps') {
                        socialRentRenderApps();
                    }
                } else if (socialState.view === 'countries') {
                    socialRenderCountries();
                } else if (socialState.view === 'country') {
                    socialRenderCountry();
                } else if (socialState.view === 'apps' && socialState.browseAll) {
                    socialLoadAllServices(socialQuery()).catch(() => {});
                } else {
                    renderSocialNumbersPlaceholder();
                }
            }, 150);
        });
    }

    let searchTimer = null;
    searchInput.addEventListener('input', () => {
        if (viewMode !== 'assets') return;
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            if (activeTab === 'social') {
                if (socialState.mode === 'rent') {
                    if (socialRentState.view === 'countries') {
                        socialRentRenderCountries();
                    } else if (socialRentState.view === 'apps') {
                        socialRentRenderApps();
                    }
                } else if (socialState.view === 'countries') {
                    socialRenderCountries();
                } else if (socialState.view === 'country') {
                    socialRenderCountry();
                } else if (socialState.view === 'apps' && socialState.browseAll) {
                    socialLoadAllServices(socialQuery()).catch(() => {});
                } else {
                    renderSocialNumbersPlaceholder();
                }
                return;
            }
            fetchNextPage(activeTab, { reset: true });
        }, 250);
    });

    loadMoreBtn.addEventListener('click', () => {
        fetchNextPage(activeTab, { reset: false });
    });

    esimsLoadMoreBtn.addEventListener('click', () => {
        fetchMyEsims({ reset: false });
    });

    esimFilterBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            const v = btn.getAttribute('data-esim-filter') || 'valid';
            esimsState.filter = v === 'expired' ? 'expired' : 'valid';
            esimFilterBtns.forEach((b) => b.classList.toggle('active', b === btn));
            fetchMyEsims({ reset: true });
        });
    });

    myEsimsNavBtn.addEventListener('click', () => {
        setViewMode(viewMode === 'assets' ? 'myesims' : 'assets');
    });

    if (walletEls.depositBtn) walletEls.depositBtn.addEventListener('click', depositToWallet);
    if (walletEls.refreshBtn) walletEls.refreshBtn.addEventListener('click', () => {
        refreshWallet();
    });
    refreshWallet();

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
