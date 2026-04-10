<x-app-layout>
    <style>
        :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif;--primary:#f27457;--secondary:#145454}
        *{box-sizing:border-box}
        body{margin:0;color:#0f1f1f;font-family:var(--font-sans);min-height:100vh;position:relative;overflow-x:hidden;background:
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
        .nav-row{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:24px}
        .myesims-nav{display:flex;gap:10px;background:rgba(255,255,255,.5);padding:6px;border-radius:9999px;border:1px solid rgba(20,84,84,.1)}
        .myesims-nav button{padding:12px 18px;border-radius:9999px;border:none;background:transparent;font-size:14px;font-weight:800;color:rgba(15,31,31,.6);cursor:pointer;transition:all .2s;white-space:nowrap}
        .myesims-nav button.active{background:#fff;color:rgba(20,84,84,.92);box-shadow:0 10px 25px rgba(15,31,31,.08);border:1px solid rgba(20,84,84,.1)}
        .esim-card{display:flex;flex-direction:column;gap:12px;padding:18px;border-radius:20px;background:rgba(255,255,255,.75);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.12);box-shadow:0 16px 40px rgba(15,31,31,.08)}
        .esim-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
        .esim-title{font-weight:900;color:rgba(15,31,31,.92);font-size:15px;line-height:1.2}
        .esim-sub{margin-top:6px;color:rgba(15,31,31,.62);font-size:13px}
        .pill{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:9999px;border:1px solid rgba(20,84,84,.12);background:rgba(20,84,84,.06);font-weight:800;font-size:11.5px;color:rgba(20,84,84,.92);flex-shrink:0}
        .pill.expired{background:rgba(242,116,87,.08);border-color:rgba(242,116,87,.16);color:rgba(242,116,87,.92)}
        .esim-meta{display:flex;gap:10px;flex-wrap:wrap}
        .kv{padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.7);border:1px solid rgba(15,31,31,.08);min-width:160px;flex:1 1 auto}
        .kv .k{font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:rgba(15,31,31,.42)}
        .kv .v{margin-top:6px;font-size:13px;font-weight:800;color:rgba(15,31,31,.88);word-break:break-word}
        .esim-actions{display:flex;gap:10px;flex-wrap:wrap}
        .mini-link{padding:10px 12px;border-radius:14px;background:rgba(20,84,84,.08);border:1px solid rgba(20,84,84,.14);font-weight:900;color:rgba(20,84,84,.92);text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
        .mini-link.secondary{background:rgba(255,255,255,.65);border-color:rgba(15,31,31,.10);color:rgba(15,31,31,.75)}
        .qr-box{margin-top:8px;border-radius:16px;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.7);padding:12px;display:flex;justify-content:center}
        .qr-box img{width:220px;max-width:100%;height:auto}
        
        .sub-toggles{display:flex;gap:8px;margin-bottom:32px;flex-wrap:wrap}
        .sub-toggles button{padding:10px 18px;border-radius:9999px;background:rgba(255,255,255,.6);border:1px solid rgba(20,84,84,.12);font-size:13px;font-weight:650;color:rgba(15,31,31,.62);cursor:pointer;transition:all .2s}
        .sub-toggles button.active{background:linear-gradient(90deg, rgba(242,116,87,.14), rgba(20,84,84,.12));border-color:rgba(242,116,87,.32);color:rgba(20,84,84,.92)}

        .grid{display:grid;grid-template-columns:1fr;gap:14px}
        @media(min-width:640px){.grid{grid-template-columns:repeat(2,1fr)}}
        @media(min-width:1024px){.grid{grid-template-columns:repeat(3,1fr)}}

        @media(max-width:640px){
            .container{padding:0 16px}
            .section{padding:28px 0}
            .section-title{font-size:20px}
            .search-bar{max-width:none;margin-bottom:20px}
            .search-bar input{padding:14px 16px 14px 46px;border-radius:18px;font-size:15px}
            .search-bar svg{left:16px;height:20px;width:20px}
            .asset-toggles{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px;border-radius:24px}
            .asset-toggles button{width:100%;padding:10px 12px;font-size:13px;text-align:center}
            .asset-toggles button:nth-child(3){grid-column:1/-1}
            .myesims-nav{width:100%}
            .myesims-nav button{width:100%;text-align:center;padding:10px 12px;font-size:13px}
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
        .tiny-btn{padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.75);border:1px solid rgba(15,31,31,.10);font-weight:850;color:rgba(15,31,31,.78);cursor:pointer}

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
        
        .py-12{flex:1;display:flex;flex-direction:column}
        .py-12 > main{flex:1}
        footer{margin-top:auto;border-top:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.35);backdrop-filter:blur(10px)}
        .footer{padding:40px 0;display:grid;grid-template-columns:1fr;gap:24px}
        .links{color:rgba(15,31,31,.64);font-size:14px;line-height:22px;text-decoration:none}
        @media(min-width:860px){.footer{grid-template-columns:repeat(3,1fr)}}
    </style>

    <div class="py-12">
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

                <div class="nav-row">
                    <div class="asset-toggles">
                        <button class="active" data-asset-toggle="countries">Countries eSIMs</button>
                        <button data-asset-toggle="regions">Regional Plans</button>
                        <button data-asset-toggle="virtual">Virtual Numbers</button>
                    </div>

                    <div class="myesims-nav">
                        <button type="button" id="myEsimsNavBtn">My eSIMs</button>
                    </div>
                </div>

                <div id="assetsPanel">
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
                        <path d="m21 21l-4.3-4.3"/>
                        <path d="M15 11h-8"/>
                    </svg>
                    <p>No matches found for your search.</p>
                </div>
                <div class="load-more-wrap" id="loadMoreWrap">
                    <button class="load-more-btn" type="button" id="loadMoreBtn">Load more</button>
                </div>
                </div>

                <div id="myEsimsPanel" class="hidden">
                    <div style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                        <div style="color:rgba(15,31,31,.55);font-weight:750;font-size:13px">
                            Signed in as {{ auth()->user()->email }}
                        </div>
                    </div>
                    <div class="asset-toggles" style="margin-bottom:18px">
                        <button class="active" type="button" data-esim-filter="valid">Valid</button>
                        <button type="button" data-esim-filter="expired">Expired</button>
                    </div>

                    <div class="grid" id="myEsimsGrid"></div>

                    <div id="noEsims" class="no-results hidden">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/>
                            <path d="M9 6h6"/>
                            <path d="M9 10h6"/>
                            <path d="M9 14h4"/>
                        </svg>
                        <p>No eSIMs found.</p>
                    </div>

                    <div class="load-more-wrap" id="esimsLoadMoreWrap">
                        <button class="load-more-btn" type="button" id="esimsLoadMoreBtn">Load more</button>
                    </div>
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
                    virtualNumbers: document.getElementById('virtualNumbersGrid').querySelector('.grid')
                };

                const state = {
                    countries: { page: 0, hasMore: true, loading: false, q: '' },
                    regions: { page: 0, hasMore: true, loading: false, q: '' },
                    virtual: { page: 0, hasMore: true, loading: false, q: '' },
                };
                let activeTab = 'countries';
                let viewMode = 'assets';
                const esimsState = { page: 0, hasMore: true, loading: false, filter: 'valid' };

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
                    if (viewMode !== 'assets') {
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

                const createEsimCard = (item) => {
                    const card = document.createElement('div');
                    card.className = 'esim-card';
                    const status = item.status === 'expired' ? 'expired' : (item.status === 'processing' ? 'processing' : 'valid');
                    const title = (item.bundle && item.bundle.name) ? item.bundle.name : 'eSIM';
                    const data = (item.bundle && item.bundle.data) ? item.bundle.data : '';
                    const validity = (item.bundle && item.bundle.validity) ? item.bundle.validity : '';
                    const iccid = item.esim ? (item.esim.iccid || '') : '';
                    const activation = item.esim ? (item.esim.activation_code || '') : '';
                    const qr = item.esim ? (item.esim.qr_code_url || '') : '';
                    const smdp = item.esim ? (item.esim.smdp_address || '') : '';
                    const lpa = item.esim ? (item.esim.lpa || item.esim.qr_payload || '') : '';
                    const esimId = item.esim ? (item.esim.esim_id || '') : '';
                    const puk = item.esim ? (item.esim.puk_code || '') : '';
                    const number = item.esim ? (item.esim.number || '') : '';
                    const providerStatus = item.esim ? (item.esim.esim_status || '') : '';
                    const installIos = item.esim ? (item.esim.direct_installation_link_ios || '') : '';
                    const installAndroid = item.esim ? (item.esim.direct_installation_link_android || '') : '';
                    const qrDataUrl = item.esim ? (item.esim.qr_code_data_url || '') : '';
                    const expiresAt = item.expires_at ? new Date(item.expires_at) : null;
                    const expiresLabel = expiresAt ? expiresAt.toLocaleString() : '';
                    const showExpires = !!expiresLabel;
                    const showPuk = !!puk;
                    const showNumber = !!number;
                    const showProviderStatus = !!providerStatus;

                    const kv = (label, value) => `<div class="kv"><div class="k">${label}</div><div class="v">${value || '-'}</div></div>`;
                    const metaParts = [
                        kv('eSIM ID', esimId),
                        kv('ICCID', iccid),
                        kv('Activation Code', activation),
                        showExpires ? kv('Expires', expiresLabel) : '',
                        kv('SM-DP+', smdp),
                        kv('LPA', lpa),
                        showPuk ? kv('PUK', puk) : '',
                        showNumber ? kv('Number', number) : '',
                        showProviderStatus ? kv('Provider Status', providerStatus) : '',
                    ].filter(Boolean).join('');

                    card.innerHTML = `
                        <div class="esim-top">
                            <div>
                                <div class="esim-title">${title}</div>
                                <div class="esim-sub">${[data, validity].filter(Boolean).join(' • ')}</div>
                            </div>
                            <span class="pill ${status === 'expired' ? 'expired' : ''}">${status === 'expired' ? 'Expired' : (status === 'processing' ? 'Processing' : 'Valid')}</span>
                        </div>
                        <div class="esim-meta">${metaParts}</div>
                        ${qrDataUrl ? `<div class="qr-box"><img alt="eSIM QR code" src="${qrDataUrl}"></div>` : ''}
                        <div class="esim-actions">
                            ${qr ? `<a class="mini-link" href="${qr}" target="_blank" rel="noopener noreferrer">Open QR</a>` : (qrDataUrl ? `<span class="mini-link secondary">QR Ready</span>` : `<span class="mini-link secondary">No QR</span>`)}
                            ${installIos ? `<a class="mini-link" href="${installIos}" target="_blank" rel="noopener noreferrer">Install iOS</a>` : ``}
                            ${installAndroid ? `<a class="mini-link" href="${installAndroid}" target="_blank" rel="noopener noreferrer">Install Android</a>` : ``}
                            <a class="mini-link secondary" href="/dashboard">Dashboard</a>
                        </div>
                    `;
                    return card;
                };

                const setEsimsSkeleton = () => {
                    myEsimsGrid.innerHTML = '';
                    for (let i = 0; i < 6; i++) {
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
                        myEsimsGrid.appendChild(sk);
                    }
                };

                const fetchMyEsims = async ({ reset = false } = {}) => {
                    if (esimsState.loading) return;
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
                        if (nextPage === 1) {
                            myEsimsGrid.innerHTML = '';
                        }
                        items.forEach((it) => myEsimsGrid.appendChild(createEsimCard(it)));
                        esimsState.page = nextPage;
                        esimsState.hasMore = !!json.has_more;
                        noEsims.classList.toggle('hidden', (items.length > 0) || nextPage > 1);
                    } catch (e) {
                        myEsimsGrid.innerHTML = '<div class="no-results"><p>Failed to load eSIMs.</p></div>';
                        esimsState.hasMore = false;
                    } finally {
                        esimsState.loading = false;
                        updateEsimsLoadMoreUi();
                    }
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

                const setViewMode = (mode) => {
                    viewMode = mode === 'myesims' ? 'myesims' : 'assets';
                    const isAssets = viewMode === 'assets';
                    assetsPanel.classList.toggle('hidden', !isAssets);
                    myEsimsPanel.classList.toggle('hidden', isAssets);
                    myEsimsNavBtn.classList.toggle('active', !isAssets);
                    searchInput.disabled = !isAssets;
                    if (isAssets) {
                        noEsims.classList.add('hidden');
                        updateLoadMoreUi();
                    } else {
                        noResultsSearch.classList.add('hidden');
                        loadMoreWrap.classList.remove('show');
                        fetchMyEsims({ reset: true });
                    }
                };

                assetToggles.forEach(btn => {
                    btn.addEventListener('click', () => {
                        setViewMode('assets');
                        setAssetSection(btn.getAttribute('data-asset-toggle'));
                    });
                });

                let searchTimer = null;
                searchInput.addEventListener('input', () => {
                    if (viewMode !== 'assets') return;
                    if (searchTimer) window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(() => {
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
    </div>
</x-app-layout>
