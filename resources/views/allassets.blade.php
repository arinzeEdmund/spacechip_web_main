<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>All Assets - {{ config('app.name', 'spacechip') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif;--primary:#f27457;--secondary:#145454}
            *{box-sizing:border-box}
            body{margin:0;color:#0f1f1f;font-family:var(--font-sans);min-height:100vh;position:relative;overflow-x:hidden;display:flex;flex-direction:column;background:
                radial-gradient(900px 520px at 12% 14%, rgba(242,116,87,.32) 0%, rgba(242,116,87,0) 60%),
                radial-gradient(980px 560px at 88% 18%, rgba(20,84,84,.26) 0%, rgba(20,84,84,0) 62%),
                radial-gradient(1100px 700px at 50% 92%, rgba(242,116,87,.18) 0%, rgba(242,116,87,0) 65%),
                linear-gradient(180deg, #F7F7F8 0%, #F5F6F8 60%, #F7F7F8 100%)}
            body::before{content:"";position:fixed;inset:-20%;background:
                radial-gradient(520px 420px at 18% 32%, rgba(242,116,87,.35) 0%, rgba(242,116,87,0) 70%),
                radial-gradient(560px 460px at 82% 38%, rgba(20,84,84,.28) 0%, rgba(20,84,84,0) 72%),
                radial-gradient(700px 520px at 58% 66%, rgba(242,116,87,.22) 0%, rgba(242,116,87,0) 74%);
                filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}
            a{color:inherit;text-decoration:none}
            a:hover,a:focus,a:active{text-decoration:none}
            .container{max-width:1120px;margin:0 auto;padding:0 24px}
            .header{padding:16px 0;display:flex;align-items:center;justify-content:space-between}
            .brand-wrap{display:flex;align-items:center;gap:10px}
            .logo{height:32px;width:32px;border-radius:12px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;letter-spacing:.02em}
            .brand{font-weight:700;letter-spacing:.02em;color:rgba(20,84,84,.92)}
            .actions{display:flex;gap:12px;align-items:center}
            .btn-primary{padding:10px 16px;border-radius:9999px;background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;font-size:14px;font-weight:600;box-shadow:0 14px 35px rgba(20,84,84,.14),0 2px 6px rgba(0,0,0,.06);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
            .btn-secondary{padding:10px 16px;border-radius:9999px;background:rgba(255,255,255,.75);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.18);color:rgba(20,84,84,.92);font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
            
            .section{padding:40px 0}
            .section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:24px}
            .section-title{font-size:24px;font-weight:800;letter-spacing:-.01em;color:#0b1a1a}
            .section-sub{color:rgba(15,31,31,.64);font-size:15px;margin-top:4px}
            
            .search-bar{margin-bottom:32px;position:relative;max-width:600px}
            .search-bar input{width:100%;padding:16px 20px 16px 52px;border-radius:20px;background:rgba(255,255,255,.85);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.15);box-shadow:0 14px 35px rgba(15,31,31,.08);outline:none;font-size:16px;color:#0b1a1a;transition:all .3s}
            .search-bar input:focus{border-color:var(--primary);box-shadow:0 14px 35px rgba(242,116,87,.12)}
            .search-bar svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);height:22px;width:22px;color:rgba(15,31,31,.4);pointer-events:none}

            .asset-toggles{display:flex;gap:10px;margin-bottom:24px;background:rgba(255,255,255,.5);padding:6px;border-radius:9999px;border:1px solid rgba(20,84,84,.1);width:fit-content}
            .asset-toggles button{padding:12px 24px;border-radius:9999px;border:none;background:transparent;font-size:14px;font-weight:700;color:rgba(15,31,31,.6);cursor:pointer;transition:all .2s}
            .asset-toggles button.active{background:#fff;color:rgba(20,84,84,.92);box-shadow:0 10px 25px rgba(15,31,31,.08);border:1px solid rgba(20,84,84,.1)}
            
            .sub-toggles{display:flex;gap:8px;margin-bottom:32px;flex-wrap:wrap}
            .sub-toggles button{padding:10px 18px;border-radius:9999px;background:rgba(255,255,255,.6);border:1px solid rgba(20,84,84,.12);font-size:13px;font-weight:650;color:rgba(15,31,31,.62);cursor:pointer;transition:all .2s}
            .sub-toggles button.active{background:linear-gradient(90deg, rgba(242,116,87,.14), rgba(20,84,84,.12));border-color:rgba(242,116,87,.32);color:rgba(20,84,84,.92)}

            .grid{display:grid;grid-template-columns:1fr;gap:14px}
            @media(min-width:640px){.grid{grid-template-columns:repeat(2,1fr)}}
            @media(min-width:1024px){.grid{grid-template-columns:repeat(3,1fr)}}

            @media(max-width:640px){
                .container{padding:0 16px}
                .header{flex-direction:column;align-items:flex-start;gap:12px}
                .actions{width:100%;flex-wrap:wrap}
                .actions a{flex:1 1 auto;justify-content:center}
                .section{padding:28px 0}
                .section-title{font-size:20px}
                .search-bar{max-width:none;margin-bottom:20px}
                .search-bar input{padding:14px 16px 14px 46px;border-radius:18px;font-size:15px}
                .search-bar svg{left:16px;height:20px;width:20px}
                .asset-toggles{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px;border-radius:24px}
                .asset-toggles button{width:100%;padding:10px 12px;font-size:13px;text-align:center}
                .asset-toggles button:nth-child(3){grid-column:1/-1}
                .card{flex-direction:column;align-items:flex-start}
                .card-left{width:100%}
                .card-right{width:100%;flex-direction:row;align-items:center;justify-content:flex-end;text-align:left}
                .name{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
                .no-results{padding:42px 12px}
            }

            @media(max-width:480px){
                .vnum-top{flex-direction:column;gap:10px}
                .vnum-price-box{text-align:left}
            }
            
            .card{padding:16px;border-radius:22px;background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);display:flex;justify-content:space-between;gap:14px;align-items:center;transition:all .3s}
            .card.hidden-by-search{display:none!important}
            .card-left{display:flex;gap:14px;align-items:center;min-width:0}
            .flag{height:40px;width:40px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.92);border:1px solid rgba(20,84,84,.12);overflow:hidden;flex:0 0 auto;font-size:26px}
            .flag img{height:100%;width:100%;object-fit:cover;display:block}
            .meta{min-width:0}
            .name{font-weight:800;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#0b1a1a}
            .subtext{margin-top:2px;font-size:12px;color:rgba(15,31,31,.62)}
            .card-right{text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
            .price{font-weight:800;font-size:15px;color:rgba(20,84,84,.92)}
            .price span{font-weight:650;color:rgba(15,31,31,.64);font-size:12px}
            .mini-btn{padding:8px 14px;border-radius:9999px;background:rgba(255,255,255,.85);border:1px solid rgba(20,84,84,.14);font-size:13px;font-weight:700;color:rgba(20,84,84,.92);cursor:pointer}
            
            .vnum-card{padding:18px;border-radius:24px;background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);display:flex;flex-direction:column;gap:16px;transition:all .3s}
            .vnum-card.hidden-by-search{display:none!important}
            .vnum-top{display:flex;justify-content:space-between;align-items:flex-start}
            .vnum-info{display:flex;gap:14px;align-items:center;min-width:0}
            .vnum-name{font-weight:800;font-size:16px;color:#0b1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .vnum-desc{font-size:13px;color:rgba(15,31,31,.64);line-height:1.5;margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
            .vnum-price-box{text-align:right}
            .vnum-price{font-weight:800;font-size:18px;color:rgba(20,84,84,.92)}
            .vnum-price span{font-size:12px;color:rgba(15,31,31,.6);font-weight:600}
            .vnum-btn{width:100%;padding:12px;border-radius:14px;background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;font-weight:700;border:none;cursor:pointer;box-shadow:0 8px 20px rgba(242,116,87,.15)}

            .no-results{grid-column: 1/-1; text-align: center; padding: 60px; color: rgba(15,31,31,.5);}
            .no-results svg{margin-bottom: 12px; opacity: .5;}
            .load-more-wrap{margin-top:18px;display:none;justify-content:center}
            .load-more-wrap.show{display:flex}
            .load-more-btn{padding:12px 16px;border-radius:14px;background:rgba(255,255,255,.8);border:1px solid rgba(20,84,84,.14);font-weight:800;color:rgba(20,84,84,.92);cursor:pointer}

            /* Skeleton Styles */
            .skeleton {
                background: #e1e1e1;
                background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                background-size: 200% 100%;
                animation: skeleton-loading 1.5s infinite;
                border-radius: 4px;
            }

            @keyframes skeleton-loading {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }

            .skeleton-card {
                padding: 16px;
                border-radius: 22px;
                background: rgba(255,255,255,.75);
                backdrop-filter: blur(12px);
                border: 1px solid rgba(20,84,84,.12);
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 14px;
            }

            .skeleton-flag { width: 40px; height: 40px; border-radius: 14px; }
            .skeleton-text-lg { width: 120px; height: 18px; margin-bottom: 8px; }
            .skeleton-text-sm { width: 80px; height: 12px; }
            .skeleton-btn { width: 70px; height: 32px; border-radius: 9999px; }

            .hidden{display:none!important}
            
            main{flex:1}
            footer{margin-top:auto;border-top:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.35);backdrop-filter:blur(10px)}
            .footer{padding:40px 0;display:grid;grid-template-columns:1fr;gap:24px}
            .links{color:rgba(15,31,31,.64);font-size:14px;line-height:22px;text-decoration:none}
            @media(min-width:860px){.footer{grid-template-columns:repeat(3,1fr)}}
        </style>
    </head>
    <body>
        <header>
            <div class="container">
                <div class="header">
                    <a href="/" class="brand-wrap">
                        <div class="logo">SC</div>
                        <div class="brand">spacechip</div>
                    </a>
                    <div class="actions">
                        <a href="/" class="btn-secondary">Back to Home</a>
                        <a href="#" class="btn-primary">Sign In</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="container">
            <section class="section">
                <div class="section-head">
                    <div>
                        <h1 class="section-title">All Assets</h1>
                        <p class="section-sub">Browse all available eSIMs, Regional Plans, and Virtual Numbers.</p>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="search-bar">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="2.5"/>
                        <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                    <input type="text" id="assetSearch" placeholder="Search countries, regions, or plans...">
                </div>

                <div class="asset-toggles">
                    <button class="active" data-asset-toggle="countries">Countries eSIMs</button>
                    <button data-asset-toggle="regions">Regional Plans</button>
                    <button data-asset-toggle="virtual">Virtual Numbers</button>
                </div>

                <!-- Countries Section -->
                <div data-asset-section="countries" id="countriesGrid">
                    <div class="grid">
                        <!-- Skeletons -->
                        @for($i = 0; $i < 9; $i++)
                            <div class="skeleton-card skeleton-placeholder">
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
                            </div>
                        @endfor
                    </div>
                </div>

                <!-- Regions Section -->
                <div class="hidden" data-asset-section="regions" id="regionsGrid">
                    <div class="grid">
                        <!-- Skeletons -->
                        @for($i = 0; $i < 6; $i++)
                            <div class="skeleton-card skeleton-placeholder">
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
                            </div>
                        @endfor
                    </div>
                </div>

                <!-- Virtual Numbers Section -->
                <div class="hidden" data-asset-section="virtual" id="virtualNumbersGrid">
                    <div class="grid">
                        <!-- Skeletons -->
                        @for($i = 0; $i < 6; $i++)
                            <div class="skeleton-card skeleton-placeholder">
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
                            </div>
                        @endfor
                    </div>
                </div>

                <!-- Shared No Results Placeholder (Dynamic) -->
                <div id="noResultsSearch" class="no-results hidden">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.3-4.3"/>
                        <path d="M15 11h-8"/>
                    </svg>
                    <p>No matches found for your search.</p>
                </div>
                <div class="load-more-wrap" id="loadMoreWrap">
                    <button class="load-more-btn" type="button" id="loadMoreBtn">Load more</button>
                </div>
            </section>
        </main>

        <footer>
            <div class="container">
                <div class="footer">
                    <div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="logo" style="height:34px;width:34px;border-radius:14px">SC</div>
                            <div style="font-weight:800;color:rgba(20,84,84,.92)">spacechip</div>
                        </div>
                        <p style="margin-top:10px;color:rgba(15,31,31,.64);font-size:14px;line-height:1.6">Be local anywhere with flexible plans for data, calls, and privacy-first numbers.</p>
                    </div>
                    <div>
                        <div style="font-weight:800;color:#0b1a1a">Company</div>
                        <div style="margin-top:10px;display:grid;gap:8px">
                            <a class="links" href="{{ route('contact') }}">Contact Us</a>
                            <a class="links" href="{{ route('terms') }}">Terms &amp; Conditions</a>
                            <a class="links" href="{{ route('privacy') }}">Privacy Policy</a>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight:800;color:#0b1a1a">Support</div>
                        <div style="margin-top:10px;display:grid;gap:8px">
                            <a class="links" href="{{ route('help') }}">Help Center</a>
                            <a class="links" href="{{ route('esim.guide') }}">eSIM Guide</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>

        <script>
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

                const createCard = (item, type) => {
                    const card = document.createElement('div');
                    card.className = type === 'virtual' ? 'vnum-card' : 'card';
                    card.setAttribute('data-search-name', item.name.toLowerCase());

                    if (type === 'virtual') {
                        card.innerHTML = `
                            <div class="vnum-top">
                                <div class="vnum-info">
                                    <div class="flag">
                                        ${item.flag_url ? `<img src="${item.flag_url}" alt="${item.name}">` : `<span>${item.flag || '🌐'}</span>`}
                                    </div>
                                    <div class="vnum-name">${item.name}</div>
                                </div>
                                <div class="vnum-price-box">
                                    <div class="vnum-price">${item.price_formatted}<span>/mo</span></div>
                                </div>
                            </div>
                            <div class="vnum-desc">${item.description || 'Virtual phone number for calls and SMS.'}</div>
                            <button class="vnum-btn">Get Number</button>
                        `;
                    } else {
                        const url = `/assets/${type === 'regions' ? 'region' : 'country'}/${item.id}`;
                        card.innerHTML = `
                            <div class="card-left">
                                <div class="flag">
                                    ${item.flag_url ? `<img src="${item.flag_url}" alt="${item.name}">` : `<span>${item.flag || '🌐'}</span>`}
                                </div>
                                <div class="meta">
                                    <div class="name">${item.name}</div>
                                </div>
                            </div>
                            <div class="card-right">
                                <a href="${url}" class="mini-btn">View Plans</a>
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
                    assetToggles.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-asset-toggle') === mode));
                    assetSections.forEach(sec => sec.classList.toggle('hidden', sec.getAttribute('data-asset-section') !== mode));
                    activeTab = mode === 'virtual' ? 'virtual' : mode;
                    const currentKey = tabToKey(activeTab);
                    if (state[activeTab].page === 0) {
                        fetchNextPage(activeTab, { reset: true });
                    }
                    const grid = grids[currentKey];
                    const hasCards = grid && grid.querySelectorAll('.card, .vnum-card').length > 0;
                    noResultsSearch.classList.toggle('hidden', hasCards || (searchInput.value || '').trim() === '');
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
        </script>
    </body>
</html>
