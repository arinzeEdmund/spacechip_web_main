<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $asset['name'] }} Details - {{ config('app.name', 'spacechip') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
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
            body::after{content:"";position:fixed;left:-120px;top:-120px;width:520px;height:520px;border-radius:9999px;background:conic-gradient(from 220deg, rgba(242,116,87,.38), rgba(20,84,84,.26), rgba(242,116,87,.18));filter:blur(34px);opacity:.6;z-index:-1;pointer-events:none}
            a{color:inherit;text-decoration:none}
            .container{max-width:1120px;margin:0 auto;padding:0 24px}
            
            header{padding:16px 0;background:rgba(255,255,255,.45);backdrop-filter:blur(12px);border-bottom:1px solid rgba(15,31,31,.08);position:sticky;top:0;z-index:100}
            .header-flex{display:flex;align-items:center;justify-content:space-between}
            .brand-wrap{display:flex;align-items:center;gap:10px}
            .logo{height:32px;width:32px;border-radius:12px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700}
            .brand{font-weight:700;color:rgba(20,84,84,.92)}

            .details-hero{padding:24px 0;background:linear-gradient(180deg, rgba(255,255,255,.4) 0%, rgba(247,247,248,0) 100%)}
            .hero-flex{display:flex;align-items:center;gap:24px}
            .asset-flag{height:64px;width:64px;border-radius:18px;overflow:hidden;border:1px solid rgba(15,31,31,.1);display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.9);backdrop-filter:blur(8px);font-size:36px;flex:0 0 auto}
            .asset-flag img{height:100%;width:100%;object-fit:cover}
            .asset-info h1{font-size:28px;font-weight:800;margin:0;color:#0b1a1a}
            .asset-type{display:inline-flex;padding:4px 10px;background:rgba(20,84,84,.08);color:rgba(20,84,84,.92);border-radius:9999px;font-size:12px;font-weight:700;margin-top:4px}

            .bundle-section{padding:24px 0}
            .section-title{font-size:22px;font-weight:800;margin-bottom:18px;color:#0b1a1a}
            
            .bundle-grid{display:grid;grid-template-columns:1fr;gap:20px}
            @media(min-width:768px){.bundle-grid{grid-template-columns:repeat(2,1fr)}}
            @media(min-width:1024px){.bundle-grid{grid-template-columns:repeat(3,1fr)}}

            .bundle-card{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border-radius:24px;padding:24px;border:1px solid rgba(15,31,31,.08);box-shadow:0 10px 30px rgba(15,31,31,.04);display:flex;flex-direction:column;gap:20px;transition:all .3s}
            .bundle-card:hover{transform:translateY(-4px);box-shadow:0 20px 40px rgba(15,31,31,.08)}
            
            .bundle-top{display:flex;justify-content:space-between;align-items:flex-start}
            .bundle-data{font-size:28px;font-weight:800;color:#0b1a1a}
            .bundle-price{text-align:right}
            .price-val{font-size:24px;font-weight:800;color:#145454}
            .price-sub{font-size:12px;color:rgba(15,31,31,.5);font-weight:600;margin-top:-2px}

            .bundle-meta{display:grid;grid-template-columns:1.2fr .8fr;gap:12px;padding:16px 0;border-top:1px solid rgba(15,31,31,.05);border-bottom:1px solid rgba(15,31,31,.05);margin-top:4px}
            .meta-item{display:flex;flex-direction:column;gap:4px}
            .meta-label{font-size:11px;font-weight:700;text-transform:uppercase;color:rgba(15,31,31,.4);letter-spacing:.05em}
            .meta-val{font-size:15px;font-weight:800;color:#0b1a1a;line-height:1.2}

            .bundle-features{display:flex;flex-direction:column;gap:10px;margin:4px 0}
            .feature{display:flex;align-items:center;gap:10px;font-size:14px;color:rgba(15,31,31,.65);font-weight:500}
            .feature svg{color:#145454;opacity:.8}

            .buy-btn{width:100%;padding:14px;border-radius:18px;background:linear-gradient(90deg, #f27457, #145454);color:#fff;font-weight:700;border:none;cursor:pointer;box-shadow:0 8px 20px rgba(242,116,87,.15);transition:all .2s;margin-top:4px}
            .buy-btn:hover{filter:brightness(1.05);box-shadow:0 12px 25px rgba(242,116,87,.25)}

            .back-link{display:inline-flex;align-items:center;gap:8px;color:rgba(15,31,31,.6);font-weight:600;font-size:14px;margin-bottom:24px}
            .back-link:hover{color:var(--secondary)}

            .no-bundles{text-align:center;padding:80px 0;color:rgba(15,31,31,.4)}
            .no-bundles svg{margin-bottom:16px;opacity:.3}
        </style>
    </head>
    <body>
        <header>
            <div class="container">
                <div class="header-flex">
                    <a href="/" class="brand-wrap">
                        <div class="logo">SC</div>
                        <div class="brand">spacechip</div>
                    </a>
                    <a href="/allassets" class="btn-secondary" style="padding:10px 16px;border-radius:9999px;border:1px solid rgba(15,31,31,.1);font-size:14px;font-weight:600">Browse all</a>
                </div>
            </div>
        </header>

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
                    
                    @if(empty($asset['bundles']))
                        <div class="no-bundles">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10H3M21 6H3M21 14H3M21 18H3"/></svg>
                            <p>No plans available for this selection at the moment.</p>
                        </div>
                    @else
                        <div class="bundle-grid">
                            @foreach($asset['bundles'] as $bundle)
                                <div class="bundle-card">
                                    <div class="bundle-top">
                                        <div>
                                            <div class="meta-label" style="margin-bottom: 2px;">Valid for</div>
                                            <div class="bundle-data" style="font-size: 24px; color: #0b1a1a;">{{ $bundle['validity'] }}</div>
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
                                    </div>

                                    <button class="buy-btn">Buy eSIM</button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </body>
</html>
