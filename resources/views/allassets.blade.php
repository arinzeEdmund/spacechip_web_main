<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'spacechip') }}">
        <title>All Assets - {{ config('app.name', 'spacechip') }}</title>
        @include('partials.head-assets')
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif}
            *{box-sizing:border-box}
            body{margin:0;color:var(--text);font-family:var(--font-sans);min-height:100vh;position:relative;overflow-x:hidden;display:flex;flex-direction:column;background:radial-gradient(900px 520px at 10% -10%, rgb(var(--accent-rgb) / .10) 0%, transparent 60%),
                var(--bg)}
            body::before{content:"";position:fixed;inset:-20%;background:radial-gradient(640px 460px at 90% 6%, rgb(var(--accent-rgb) / .07) 0%, transparent 70%);
                filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}
            a{color:inherit;text-decoration:none}
            a:hover,a:focus,a:active{text-decoration:none}
            .container{max-width:1120px;margin:0 auto;padding:0 24px}
            .header{padding:16px 0;display:flex;align-items:center;justify-content:space-between}
            .brand-wrap{display:flex;align-items:center;gap:10px}
            .brand{font-weight:700;letter-spacing:.02em;color:rgb(var(--accent-text-rgb) / .92)}
            .actions{display:flex;gap:12px;align-items:center}
            .btn-primary{padding:10px 16px;border-radius:9999px;background:var(--accent-fill);color:#fff;font-size:14px;font-weight:600;box-shadow:0 14px 35px rgba(0,0,0,.14),0 2px 6px rgba(0,0,0,.06);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
            .btn-secondary{padding:10px 16px;border-radius:9999px;background:rgb(var(--surface-rgb) / .75);backdrop-filter:blur(10px);border:1px solid rgb(var(--ink-rgb) / .18);color:rgb(var(--accent-text-rgb) / .92);font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
            
            .section{padding:40px 0}
            .section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:24px}
            .section-title{font-size:24px;font-weight:800;letter-spacing:-.01em;color:var(--text)}
            .section-sub{color:rgb(var(--ink-rgb) / .64);font-size:15px;margin-top:4px}
            
            .search-bar{margin-bottom:32px;position:relative;max-width:600px}
            .search-bar input{width:100%;padding:16px 20px 16px 52px;border-radius:20px;background:rgb(var(--surface-rgb) / .85);backdrop-filter:blur(10px);border:1px solid rgb(var(--ink-rgb) / .15);box-shadow:0 14px 35px rgba(0,0,0,.08);outline:none;font-size:16px;color:var(--text);transition:all .3s}
            .search-bar input:focus{border-color:var(--primary);box-shadow:0 14px 35px rgb(var(--accent-rgb) / .12)}
            .search-bar svg{position:absolute;z-index:1;left:18px;top:50%;transform:translateY(-50%);height:22px;width:22px;color:rgb(var(--ink-rgb) / .4);pointer-events:none}

            .asset-toggles{display:flex;gap:2px;margin-bottom:24px;background:var(--surface-2);padding:3px;border-radius:9999px;border:1px solid var(--border);width:fit-content;max-width:100%}
            .asset-toggles button{padding:9px 18px;border-radius:9999px;border:none;background:transparent;font-size:13.5px;font-weight:600;color:var(--muted);cursor:pointer;white-space:nowrap;transition:color .15s,background-color .15s}
            .asset-toggles button.active{background:var(--surface);color:var(--text);box-shadow:var(--shadow-1),0 0 0 1px var(--border)}
            
            .sub-toggles{display:flex;gap:8px;margin-bottom:32px;flex-wrap:wrap}
            .sub-toggles button{padding:10px 18px;border-radius:9999px;background:rgb(var(--surface-rgb) / .6);border:1px solid rgb(var(--ink-rgb) / .12);font-size:13px;font-weight:650;color:rgb(var(--ink-rgb) / .62);cursor:pointer;transition:all .2s}
            .sub-toggles button.active{background:var(--accent-soft);border-color:rgb(var(--accent-rgb) / .32);color:rgb(var(--accent-text-rgb) / .92)}

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
                .asset-toggles{width:100%;overflow-x:auto;scrollbar-width:none}
                .asset-toggles button{flex:1 0 auto;padding:9px 14px;font-size:13px}
                .card{padding:12px 12px 12px 14px}
                .no-results{padding:42px 12px}
            }

            @media(max-width:480px){
                                            }
            
            .card{position:relative;padding:14px 14px 14px 16px;border-radius:18px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-1);display:flex;justify-content:space-between;gap:14px;align-items:center;transition:border-color .18s,box-shadow .18s,transform .18s}
            .card.hidden-by-search{display:none!important}
            .card-left{display:flex;gap:14px;align-items:center;min-width:0}
            .flag{height:40px;width:40px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:rgb(var(--surface-rgb) / .92);border:1px solid rgb(var(--ink-rgb) / .12);overflow:hidden;flex:0 0 auto;font-size:26px}
            .flag img{height:100%;width:100%;object-fit:cover;display:block}
            .meta{min-width:0}
            .name{font-weight:600;font-size:15px;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
            .subtext{margin-top:2px;font-size:12px;color:rgb(var(--ink-rgb) / .62)}
            .card-right{text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
            .price{font-weight:800;font-size:15px;color:rgb(var(--accent-text-rgb) / .92)}
            .price span{font-weight:650;color:rgb(var(--ink-rgb) / .64);font-size:12px}
            .mini-btn{display:inline-flex;align-items:center;height:34px;padding:0 14px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);font-size:13px;font-weight:600;color:var(--text);cursor:pointer;white-space:nowrap;transition:background-color .15s,color .15s,border-color .15s}
            .mini-btn.is-loading{pointer-events:none;opacity:.75}
            .btn-spinner{width:14px;height:14px;border-radius:9999px;border:2px solid rgb(var(--accent-strong-rgb) / .25);border-top-color:rgb(var(--accent-strong-rgb) / .92);animation:btn-spin .8s linear infinite}
            @keyframes btn-spin{to{transform:rotate(360deg)}}
            
            .vnum-card{padding:20px;border-radius:24px;background:rgb(var(--surface-rgb) / .75);backdrop-filter:blur(12px);border:1px solid rgb(var(--ink-rgb) / .12);box-shadow:0 14px 35px rgba(0,0,0,.07);display:flex;flex-direction:column;gap:14px;transition:transform .2s,box-shadow .2s,border-color .2s}
            .vnum-card:hover{transform:translateY(-3px);border-color:rgb(var(--accent-rgb) / .35);box-shadow:0 18px 40px rgba(0,0,0,.1)}
            .vnum-card.hidden-by-search{display:none!important}
            .vnum-top{display:flex;gap:14px;align-items:center;min-width:0}
            .vnum-title{min-width:0;display:flex;flex-direction:column;gap:5px;align-items:flex-start}
            .vnum-name{font-weight:800;font-size:16px;line-height:1.25;color:var(--text);max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .vnum-code{font-size:11px;font-weight:800;letter-spacing:.06em;padding:2px 8px;border-radius:999px;background:rgb(var(--accent-rgb) / .12);color:rgb(var(--accent-text-rgb) / .95)}
            .vnum-price{display:flex;align-items:baseline;gap:4px;padding:12px 0;border-top:1px dashed rgb(var(--ink-rgb) / .14);border-bottom:1px dashed rgb(var(--ink-rgb) / .14)}
            .vnum-price b{font-weight:800;font-size:24px;letter-spacing:-.01em;color:rgb(var(--accent-text-rgb) / .95)}
            .vnum-price span{font-size:13px;color:rgb(var(--ink-rgb) / .6);font-weight:600}
            .vnum-chips{display:flex;flex-wrap:wrap;gap:6px}
            .vnum-chips span{font-size:12px;font-weight:600;padding:4px 10px;border-radius:999px;background:rgb(var(--ink-rgb) / .06);color:rgb(var(--ink-rgb) / .72)}
            .vnum-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;border-radius:14px;background:var(--accent-fill);color:#fff;font-weight:700;border:none;cursor:pointer;text-decoration:none;box-shadow:0 8px 20px rgb(var(--accent-rgb) / .15);transition:filter .15s}
            .vnum-btn span{transition:transform .2s}
            .vnum-btn:hover span{transform:translateX(4px)}

            .no-results{grid-column: 1/-1; text-align: center; padding: 60px; color: rgb(var(--ink-rgb) / .5);}
            .no-results svg{margin-bottom: 12px; opacity: .5;}
            .load-more-wrap{margin-top:18px;display:none;justify-content:center}
            .load-more-wrap.show{display:flex}
            .load-more-btn{padding:12px 16px;border-radius:14px;background:rgb(var(--surface-rgb) / .8);border:1px solid rgb(var(--ink-rgb) / .14);font-weight:800;color:rgb(var(--accent-text-rgb) / .92);cursor:pointer}



            .skeleton-card {
                padding: 16px;
                border-radius: 22px;
                background: rgb(var(--surface-rgb) / .75);
                backdrop-filter: blur(12px);
                border: 1px solid rgb(var(--ink-rgb) / .12);
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
            
            main{flex:1;width:100%;min-width:0}
            footer{margin-top:auto;border-top:1px solid rgb(var(--ink-rgb) / .10);background:rgb(var(--surface-rgb) / .35);backdrop-filter:blur(10px)}
            .footer{padding:40px 0;display:grid;grid-template-columns:1fr;gap:24px}
            .links{color:rgb(var(--ink-rgb) / .64);font-size:14px;line-height:22px;text-decoration:none}
            @media(min-width:860px){.footer{grid-template-columns:repeat(3,1fr)}}
            .footer-wordmark{height:26px;width:auto;display:block}
        </style>
    </head>
    <body>
        @include('partials.skip-link')
        @include('partials.site-header')

        <main id="main" tabindex="-1" class="container">
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
                    <input type="text" id="assetSearch" aria-label="Search countries, regions or plans" placeholder="Search countries, regions, or plans...">
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
                        <div style="color:var(--text)">@include('partials.brand-wordmark', ['class' => 'footer-wordmark'])</div>
                        <p style="margin-top:10px;color:rgb(var(--ink-rgb) / .64);font-size:14px;line-height:1.6">Be local anywhere with flexible plans for data, calls, and privacy-first numbers.</p>
                    </div>
                    <div>
                        <div style="font-weight:800;color:var(--text)">Company</div>
                        <div style="margin-top:10px;display:grid;gap:8px">
                            <a class="links" href="{{ route('contact') }}">Contact Us</a>
                            <a class="links" href="{{ route('terms') }}">Terms &amp; Conditions</a>
                            <a class="links" href="{{ route('privacy') }}">Privacy Policy</a>
                        </div>
                    </div>
                    <div>
                        <div style="font-weight:800;color:var(--text)">Support</div>
                        <div style="margin-top:10px;display:grid;gap:8px">
                            <a class="links" href="{{ route('help') }}">Help Center</a>
                            <a class="links" href="{{ route('esim.guide') }}">eSIM Guide</a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>

        <script src="{{ asset('js/allassets.js') }}?v={{ @filemtime(public_path('js/allassets.js')) }}"></script>
        <script src="{{ asset('pwa-install.js') }}" defer></script>
    </body>
</html>
