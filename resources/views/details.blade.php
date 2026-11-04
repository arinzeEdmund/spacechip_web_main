<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'spacechip') }}">
        <title>{{ $asset['name'] }} Details - {{ config('app.name', 'spacechip') }}</title>
        @include('partials.head-assets')
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif}
            *{box-sizing:border-box}
            body{margin:0;color:var(--text);font-family:var(--font-sans);min-height:100vh;position:relative;overflow-x:hidden;background:radial-gradient(900px 520px at 10% -10%, rgb(var(--accent-rgb) / .10) 0%, transparent 60%),
                var(--bg)}
            body::before{content:"";position:fixed;inset:-20%;background:radial-gradient(640px 460px at 90% 6%, rgb(var(--accent-rgb) / .07) 0%, transparent 70%);
                filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}
            body::after{content:"";position:fixed;left:-120px;top:-120px;width:520px;height:520px;border-radius:9999px;background:conic-gradient(from 220deg, rgb(var(--accent-rgb) / .38), rgb(var(--accent-fill-hover-rgb) / .26), rgb(var(--accent-rgb) / .18));filter:blur(34px);opacity:.6;z-index:-1;pointer-events:none}
            a{color:inherit;text-decoration:none}
            .container{max-width:1120px;margin:0 auto;padding:0 24px}
            
            .header-flex{display:flex;align-items:center;justify-content:space-between}
            .brand-wrap{display:flex;align-items:center;gap:10px}
            .brand{font-weight:700;color:rgb(var(--accent-text-rgb) / .92)}

            .details-hero{padding:24px 0;background:linear-gradient(180deg, rgb(var(--surface-rgb) / .4) 0%, rgba(247,247,248,0) 100%)}
            .hero-flex{display:flex;align-items:center;gap:24px}
            .asset-flag{height:64px;width:64px;border-radius:18px;overflow:hidden;border:1px solid rgb(var(--ink-rgb) / .1);display:flex;align-items:center;justify-content:center;background:rgb(var(--surface-rgb) / .9);backdrop-filter:blur(8px);font-size:36px;flex:0 0 auto}
            .asset-flag img{height:100%;width:100%;object-fit:cover}
            .asset-info h1{font-size:28px;font-weight:800;margin:0;color:var(--text)}
            .asset-type{display:inline-flex;padding:4px 10px;background:rgb(var(--ink-rgb) / .08);color:rgb(var(--accent-text-rgb) / .92);border-radius:9999px;font-size:12px;font-weight:700;margin-top:4px}

            .bundle-section{padding:24px 0}
            .section-title{font-size:22px;font-weight:800;margin-bottom:18px;color:var(--text)}
            .sub-toggles{display:flex;gap:8px;margin:10px 0 22px;flex-wrap:wrap}
            .sub-toggles button{padding:10px 18px;border-radius:9999px;background:rgb(var(--surface-rgb) / .7);border:1px solid rgb(var(--ink-rgb) / .12);font-size:13px;font-weight:750;color:rgb(var(--ink-rgb) / .62);cursor:pointer;transition:all .2s}
            .sub-toggles button.active{background:var(--accent-soft);border-color:rgb(var(--accent-rgb) / .32);color:rgb(var(--accent-text-rgb) / .92)}
            .sub-toggles button[disabled]{opacity:.6;cursor:not-allowed}
            .hidden{display:none!important}
            .bundle-skel{display:grid;gap:12px}
            .bundle-skel-top{display:flex;justify-content:space-between;gap:16px}
            .bundle-skel-left{display:grid;gap:8px}
            .bundle-skel-right{display:grid;gap:8px;justify-items:end}
            .skel-line-lg{height:20px;width:140px}
            .skel-line-md{height:14px;width:110px}
            .skel-pill{height:44px;width:100%}
            
            .bundle-grid{display:grid;grid-template-columns:1fr;gap:20px}
            @media(min-width:768px){.bundle-grid{grid-template-columns:repeat(2,1fr)}}
            @media(min-width:1024px){.bundle-grid{grid-template-columns:repeat(3,1fr)}}

            .bundle-card{background:rgb(var(--surface-rgb) / .75);backdrop-filter:blur(12px);border-radius:24px;padding:24px;border:1px solid rgb(var(--ink-rgb) / .08);box-shadow:0 10px 30px rgba(0,0,0,.04);display:flex;flex-direction:column;gap:20px;transition:all .3s}
            .bundle-card:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(0,0,0,.08)}
            
            .bundle-top{display:flex;justify-content:space-between;align-items:flex-start}
            .bundle-data{font-size:28px;font-weight:800;color:var(--text)}
            .bundle-price{text-align:right}
            .price-val{font-size:24px;font-weight:800;color:var(--accent-text)}
            .price-sub{font-size:12px;color:rgb(var(--ink-rgb) / .5);font-weight:600;margin-top:-2px}

            .bundle-meta{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;padding:16px 0;border-top:1px solid rgb(var(--ink-rgb) / .05);border-bottom:1px solid rgb(var(--ink-rgb) / .05);margin-top:4px}
            .meta-item{display:flex;flex-direction:column;gap:4px}
            .meta-label{font-size:11px;font-weight:700;text-transform:uppercase;color:rgb(var(--ink-rgb) / .4);letter-spacing:.05em}
            .meta-val{font-size:15px;font-weight:800;color:var(--text);line-height:1.2}

            .bundle-features{display:flex;flex-direction:column;gap:10px;margin:4px 0}
            .feature{display:flex;align-items:center;gap:10px;font-size:14px;color:rgb(var(--ink-rgb) / .65);font-weight:500}
            .feature svg{color:var(--accent-text);opacity:.8}

            .buy-btn{width:100%;padding:14px;border-radius:18px;background:var(--accent-fill);color:#fff;font-weight:700;border:none;cursor:pointer;box-shadow:0 8px 20px rgb(var(--accent-rgb) / .15);transition:all .2s;margin-top:4px}
            .buy-btn:hover{filter:brightness(1.05);box-shadow:0 12px 25px rgb(var(--accent-rgb) / .25)}
            .buy-btn.is-loading{opacity:.82;filter:saturate(.95);box-shadow:0 10px 26px rgba(0,0,0,.18)}
            .buy-btn-content{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%}
            .buy-spinner{width:18px;height:18px;border-radius:9999px;border:3px solid rgb(var(--surface-rgb) / .38);border-top-color:var(--surface);animation:buy-spin .75s linear infinite;flex:0 0 auto}
            @keyframes buy-spin{to{transform:rotate(360deg)}}

            .back-link{display:inline-flex;align-items:center;gap:8px;color:rgb(var(--ink-rgb) / .6);font-weight:600;font-size:14px;margin-bottom:24px}
            .back-link:hover{color:var(--secondary)}

            .no-bundles{text-align:center;padding:80px 0;color:rgb(var(--ink-rgb) / .4)}
            .no-bundles svg{margin-bottom:16px;opacity:.3}

            @media(max-width:640px){
                .container{padding:0 16px}
                .header-flex{gap:12px;flex-wrap:wrap}
                .details-hero{padding:18px 0}
                .hero-flex{flex-direction:column;align-items:flex-start;gap:14px}
                .asset-flag{height:56px;width:56px;border-radius:16px;font-size:32px}
                .asset-info h1{font-size:22px}
                .bundle-card{padding:18px}
                .bundle-top{flex-direction:column;gap:10px}
                .bundle-price{text-align:left}
                .bundle-meta{grid-template-columns:1fr;gap:10px}
                .no-bundles{padding:56px 0}
            }

            @media(max-width:380px){
                .sub-toggles button{width:100%;justify-content:center}
            }
        </style>
    </head>
    <body>
        @include('partials.site-header')

        <main>
            <section class="details-hero">
                <div class="container">
                    <a href="javascript:history.back()" class="back-link">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        Back
                    </a>
                    <div class="hero-flex">
                        <div class="asset-flag">
                            @if($asset['flag_url'])
                                <img src="{{ $asset['flag_url'] }}" alt="flag">
                            @else
                                {{ $asset['flag'] }}
                            @endif
                        </div>
                        <div class="asset-info">
                            <h1>{{ $asset['name'] }}</h1>
                            <div class="asset-type">{{ $asset['type'] }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bundle-section">
                <div class="container">
                    <h2 class="section-title">Available Bundles</h2>
                    
                    @php
                        $bundlesDataOnlyList = $bundlesDataOnly ?? [];
                        $bundlesDataCallsList = $bundlesDataCalls ?? [];
                        $hasAnyBundles = !empty($bundlesDataOnlyList) || !empty($bundlesDataCallsList);
                    @endphp

                    @if(!$hasAnyBundles)
                        <div class="no-bundles">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg>
                            <p>No plans available for this selection at the moment.</p>
                        </div>
                    @else
                        <div class="sub-toggles">
                            <button class="active" type="button" data-bundle-toggle="data" {{ empty($bundlesDataOnlyList) ? 'disabled' : '' }}>eSIMs for Data</button>
                            <button type="button" data-bundle-toggle="calls">eSIMs for Data and Call</button>
                        </div>

                        <div class="bundle-grid" data-bundle-grid="data">
                            @foreach($bundlesDataOnlyList as $bundle)
                                <div class="bundle-card">
                                    <div class="bundle-top">
                                        <div>
                                            <div class="meta-label" style="margin-bottom: 2px;">Valid for</div>
                                            <div class="bundle-data" style="font-size: 24px; color: var(--text);">{{ $bundle['validity'] }}</div>
                                        </div>
                                        <div class="bundle-price">
                                            <div class="price-val">{{ $bundle['price_formatted'] }}</div>
                                            <div class="price-sub">One-time payment</div>
                                        </div>
                                    </div>
                                    
                                    <div class="bundle-meta">
                                        <div class="meta-item">
                                            <span class="meta-label">Data</span>
                                            <span class="meta-val">{{ $bundle['data'] }}</span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-label">Type</span>
                                            <span class="meta-val">{{ $bundle['package_type'] === 'DATA-ONLY' ? 'Data Only' : 'Data + Calls' }}</span>
                                        </div>
                                    </div>

                                    <div class="bundle-features">
                                        <div class="feature">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            <span>Hotspot: <strong>{{ $bundle['features']['Hotspot'] }}</strong></span>
                                        </div>
                                        <div class="feature">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            <span>Network: <strong>2G,3G,4G,5G</strong></span>
                                        </div>
                                        <div class="feature">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            <span>Activation: <strong>Instant</strong></span>
                                        </div>
                                        <div class="feature">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                            <span>Renewal: <strong>{{ ($bundle['can_renew'] ?? null) === true ? 'Yes' : 'No' }}</strong></span>
                                        </div>
                                    </div>

                                    @auth
                                        <a class="buy-btn" href="{{ route('checkout', array_filter(['type' => $type, 'id' => $id, 'bundle' => $bundle['id'], 'package_type' => 'DATA-ONLY', 'topup_esim_id' => $topupEsimId ?? null])) }}" style="display:block;text-align:center">{{ ($topupEsimId ?? null) ? 'Top up' : 'Buy eSIM' }}</a>
                                    @else
                                        <a class="buy-btn" href="{{ route('login') }}" style="display:block;text-align:center">Sign in to buy</a>
                                    @endauth
                                </div>
                            @endforeach
                        </div>

                        <div class="bundle-grid hidden" data-bundle-grid="calls" id="callsBundleGrid" data-calls-loaded="0">
                            <div class="bundle-card">
                                <div class="bundle-skel">
                                    <div class="bundle-skel-top">
                                        <div class="bundle-skel-left">
                                            <div class="skeleton skel-line-md"></div>
                                            <div class="skeleton skel-line-lg"></div>
                                        </div>
                                        <div class="bundle-skel-right">
                                            <div class="skeleton skel-line-md"></div>
                                            <div class="skeleton skel-line-md"></div>
                                        </div>
                                    </div>
                                    <div class="skeleton skel-pill"></div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </main>
        <script type="application/json" id="detailsPageConfig">{!! json_encode([
            'authBuyUrlBase' => route('checkout'),
            'isAuthed' => auth()->check(),
            'loginUrl' => route('login'),
            'assetType' => (string) $type,
            'assetId' => (string) $id,
            'topupEsimId' => $topupEsimId ?? null,
        ]) !!}</script>
        <script src="{{ asset('js/asset-details.js') }}?v={{ @filemtime(public_path('js/asset-details.js')) }}"></script>
        <script src="{{ asset('pwa-install.js') }}" defer></script>
    </body>
</html>
