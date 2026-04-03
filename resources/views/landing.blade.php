<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'spacechip') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
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
                .header{padding:16px 0;display:flex;align-items:center;justify-content:space-between}
                .brand-wrap{display:flex;align-items:center;gap:10px}
                .logo{height:32px;width:32px;border-radius:12px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;letter-spacing:.02em}
                .brand{font-weight:700;letter-spacing:.02em;color:rgba(20,84,84,.92)}
                .actions{display:flex;gap:12px;align-items:center}
                .btn-primary{padding:10px 16px;border-radius:9999px;background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;font-size:14px;font-weight:600;box-shadow:0 14px 35px rgba(20,84,84,.14),0 2px 6px rgba(0,0,0,.06);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
                .btn-secondary{padding:10px 16px;border-radius:9999px;background:rgba(255,255,255,.75);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.18);color:rgba(20,84,84,.92);font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
                .pill{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9999px;background:rgba(255,255,255,.78);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.14);font-size:14px;color:rgba(15,31,31,.72)}
                .hero{padding:24px 0 14px}
                .hero-grid{display:grid;grid-template-columns:1fr;gap:14px;align-items:start}
                .badge{display:inline-flex;align-items:center;gap:10px;padding:8px 12px;border-radius:9999px;background:rgba(255,255,255,.72);border:1px solid rgba(242,116,87,.22);backdrop-filter:blur(10px);color:rgba(15,31,31,.74);font-size:13px;font-weight:600}
                .badge-dot{height:8px;width:8px;border-radius:9999px;background:linear-gradient(90deg,var(--primary),var(--secondary))}
                .headline{margin-top:14px;font-size:34px;line-height:1.08;font-weight:750;letter-spacing:-.03em;color:#0b1a1a}
                .headline .accent{background:linear-gradient(90deg,var(--primary),var(--secondary));-webkit-background-clip:text;background-clip:text;color:transparent}
                .lead{margin-top:12px;font-size:16px;line-height:1.6;color:rgba(15,31,31,.72);max-width:46ch}
                .stat-row{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px}
                .stat{display:inline-flex;align-items:center;gap:10px;padding:10px 12px;border-radius:16px;background:rgba(255,255,255,.72);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.12);box-shadow:0 10px 30px rgba(15,31,31,.06)}
                .stat strong{font-size:14px}
                .stat span{font-size:13px;color:rgba(15,31,31,.66)}
                .cta-row{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
                .store-hero{min-width:230px;justify-content:flex-start}
                .store-hero svg{flex:0 0 auto;display:block}
                .store-text{display:flex;flex-direction:column;line-height:1.1}
                .store-kicker{font-size:11px;font-weight:700;color:rgba(15,31,31,.62)}
                .store-label{font-size:14px;font-weight:900;color:rgba(20,84,84,.92)}
                .store.store-hero-primary{background:linear-gradient(90deg,var(--primary),var(--secondary));border-color:transparent;color:#fff;box-shadow:0 18px 45px rgba(20,84,84,.18),0 2px 10px rgba(0,0,0,.08)}
                .store.store-hero-primary .store-kicker{color:rgba(255,255,255,.82)}
                .store.store-hero-primary .store-label{color:#fff}
                .store.store-hero-secondary{background:rgba(255,255,255,.78);border-color:rgba(15,31,31,.10);color:rgba(15,31,31,.88)}
                .store.store-hero-secondary .store-label{color:rgba(15,31,31,.88)}
                .cta-btn{display:inline-flex;align-items:center;gap:12px;padding:10px 14px;border-radius:9999px;text-decoration:none;border:1px solid rgba(20,84,84,.14);background:rgba(255,255,255,.72);backdrop-filter:blur(10px);box-shadow:0 12px 28px rgba(15,31,31,.06)}
                .cta-btn.primary{background:linear-gradient(90deg,var(--primary),var(--secondary));border-color:transparent;box-shadow:0 18px 45px rgba(20,84,84,.18),0 2px 10px rgba(0,0,0,.08);color:#fff}
                .cta-btn.secondary{background:rgba(255,255,255,.78);border-color:rgba(20,84,84,.18);color:rgba(20,84,84,.92)}
                .cta-btn .ico{height:34px;width:34px;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:rgba(15,31,31,.06);color:rgba(20,84,84,.92);flex:0 0 auto}
                .cta-btn.primary .ico{background:rgba(255,255,255,.18);color:#fff}
                .cta-btn .txt{display:flex;flex-direction:column;line-height:1.05}
                .cta-btn .kicker{font-size:11px;font-weight:700;color:rgba(15,31,31,.62)}
                .cta-btn.primary .kicker{color:rgba(255,255,255,.82)}
                .cta-btn .label{font-size:14px;font-weight:900;letter-spacing:-.01em}
                .cta-btn.primary .label{color:#fff}
                .cta-btn:hover{transform:translateY(-1px)}
                .panel{border-radius:22px;background:rgba(255,255,255,.68);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.16);box-shadow:0 18px 55px rgba(15,31,31,.10);overflow:hidden}
                .panel-top{padding:18px}
                .panel-title{font-size:14px;font-weight:750;letter-spacing:.02em;color:rgba(20,84,84,.92)}
                .segmented{margin-top:12px;background:rgba(20,84,84,.06);border:1px solid rgba(20,84,84,.12);border-radius:18px;padding:6px;display:flex;gap:6px;flex-wrap:nowrap;align-items:center;overflow-x:auto;-webkit-overflow-scrolling:touch}
                .segmented::-webkit-scrollbar{display:none}
                .segmented .seg{padding:10px 12px;border-radius:14px;font-size:13px;font-weight:650;color:rgba(15,31,31,.64);background:transparent;border:1px solid transparent;cursor:default;white-space:nowrap;flex:0 0 auto;line-height:1}
                .segmented .seg.active{background:#fff;color:rgba(20,84,84,.92);box-shadow:0 10px 20px rgba(15,31,31,.06);border-color:rgba(20,84,84,.14)}
                .search{margin-top:12px;position:relative}
                .search input{width:100%;padding:14px 16px 14px 46px;border-radius:16px;background:rgba(255,255,255,.92);border:1px solid rgba(15,31,31,.10);box-shadow:0 10px 24px rgba(15,31,31,.06);outline:none;font-size:14px}
                .toggle{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
                .toggle .t{padding:10px 12px;border-radius:9999px;background:rgba(255,255,255,.75);border:1px solid rgba(15,31,31,.10);font-size:13px;font-weight:650;color:rgba(15,31,31,.62)}
                .toggle .t.active{border-color:rgba(242,116,87,.32);background:linear-gradient(90deg, rgba(242,116,87,.14), rgba(20,84,84,.12));color:rgba(20,84,84,.92)}
                .quick{padding:18px 18px 20px;border-top:1px solid rgba(15,31,31,.08);display:grid;grid-template-columns:1fr;gap:12px;background:linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.55) 100%)}
                .quick-item{display:flex;gap:14px;align-items:flex-start;padding:14px 14px 13px;border-radius:18px;background:rgba(255,255,255,.72);border:1px solid rgba(15,31,31,.08);min-height:108px;min-width:0}
                .quick-item>div:last-child{display:flex;flex-direction:column;gap:6px;min-width:0}
                .qi-ico{height:40px;width:40px;border-radius:16px;background:linear-gradient(135deg, rgba(242,116,87,.22), rgba(20,84,84,.18));display:flex;align-items:center;justify-content:center;color:rgba(20,84,84,.92);flex:0 0 auto}
                .qi-title{font-weight:800;font-size:14px;line-height:1.2}
                .qi-desc{color:rgba(15,31,31,.64);font-size:13px;line-height:1.55}
                .section{padding:18px 0 0}
                .section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap}
                .section-title{font-size:18px;font-weight:800;letter-spacing:-.01em;color:#0b1a1a}
                .section-sub{color:rgba(15,31,31,.64);font-size:14px}
                .hidden{display:none!important}
                .grid{margin-top:14px;display:grid;grid-template-columns:1fr;gap:12px}
                .country{padding:14px 14px 12px;border-radius:18px;background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);display:flex;justify-content:space-between;gap:12px;align-items:center}
                .country-left{display:flex;gap:12px;align-items:center;min-width:0}
                .flag{height:32px;width:32px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.92);border:1px solid rgba(20,84,84,.12);overflow:hidden;flex:0 0 auto;font-size:22px}
                .flag img{height:100%;width:100%;object-fit:cover;display:block}
                .c-meta{min-width:0}
                .c-name{font-weight:750;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
                .c-note{margin-top:2px;font-size:12px;color:rgba(15,31,31,.62)}
                .c-pill{margin-top:8px;display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;background:rgba(242,116,87,.12);border:1px solid rgba(242,116,87,.22);font-size:12px;font-weight:700;color:rgba(15,31,31,.74)}
                .country-right{text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
                .price{font-weight:800;font-size:14px;color:rgba(20,84,84,.92)}
                .price span{font-weight:650;color:rgba(15,31,31,.64);font-size:12px}
                .mini-btn{padding:8px 12px;border-radius:9999px;background:rgba(255,255,255,.82);border:1px solid rgba(20,84,84,.14);font-size:13px;font-weight:700;color:rgba(20,84,84,.92)}
                .more{margin-top:14px;display:flex;justify-content:center}
                .more .btn{padding:10px 18px;border-radius:9999px;background:rgba(255,255,255,.78);border:1px solid rgba(20,84,84,.14);box-shadow:0 12px 28px rgba(15,31,31,.06);font-weight:700;color:rgba(20,84,84,.92)}

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
                    padding: 14px 14px 12px;
                    border-radius: 18px;
                    background: rgba(255,255,255,.75);
                    backdrop-filter: blur(12px);
                    border: 1px solid rgba(20,84,84,.12);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 12px;
                }

                .skeleton-flag { width: 32px; height: 32px; border-radius: 12px; }
                .skeleton-text-lg { width: 100px; height: 14px; margin-bottom: 6px; }
                .skeleton-text-sm { width: 60px; height: 10px; }
                .skeleton-btn { width: 60px; height: 28px; border-radius: 9999px; }

                footer{margin-top:34px;border-top:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.35);backdrop-filter:blur(10px)}
                .footer{padding:34px 0;display:grid;grid-template-columns:1fr;gap:22px}
                .links{color:rgba(15,31,31,.64);font-size:14px;line-height:22px;text-decoration:none}
                .links:hover{color:rgba(20,84,84,.92)}
                .social{display:flex;gap:10px;margin-top:12px}
                .sbtn{height:38px;width:38px;border-radius:14px;background:rgba(255,255,255,.78);border:1px solid rgba(20,84,84,.14);display:flex;align-items:center;justify-content:center;color:rgba(20,84,84,.88)}
                .store{display:inline-flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,.78);border:1px solid rgba(20,84,84,.14);border-radius:9999px;box-shadow:0 12px 28px rgba(15,31,31,.06);cursor:pointer;user-select:none}
                @media(min-width:860px){.hero-grid{grid-template-columns:1.1fr .9fr;gap:18px}.grid{grid-template-columns:repeat(3,1fr)}.footer{grid-template-columns:repeat(3,1fr);align-items:start}.quick{grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}.headline{font-size:46px}}
                @media(min-width:520px){.grid{grid-template-columns:repeat(2,1fr)}}
                @media(max-width:520px){
                    .container{padding:0 16px}
                    .header{flex-wrap:wrap;gap:12px}
                    .actions{flex-wrap:wrap;gap:10px;justify-content:flex-start;width:100%}
                    .btn-primary,.btn-secondary{padding:10px 14px;font-size:13px}
                    .pill{padding:8px 10px;font-size:13px}
                    .headline{font-size:30px}
                    .lead{font-size:15px}
                    .cta-row{flex-direction:column;align-items:stretch}
                    .store-hero{width:100%;min-width:0;justify-content:center}
                    .panel-top{padding:16px}
                    .quick{padding:16px}
                    .footer .store{width:100%;justify-content:center}
                }
                @media(max-width:380px){
                    .pill{display:none}
                    .headline{font-size:28px}
                }
            </style>
        @endif
    </head>
    <body>
        <header>
            <div class="container">
                <div class="header">
                    <div class="brand-wrap">
                        <div class="logo">SC</div>
                        <div class="brand">spacechip</div>
                    </div>
                    <div class="actions">
                        <a href="/allassets" class="btn-secondary">Explore plans</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary">
                                <span>Dashboard</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary">
                                <span>Sign In</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        @endauth
                        <div class="pill"><span style="font-size:18px">🇬🇧</span><span>English</span></div>
                    </div>
                </div>
            </div>
        </header>
        <main>
            <section class="hero">
                <div class="container">
                    <div class="hero-grid">
                        <div>
                            <div class="badge"><span class="badge-dot"></span><span>Virtual numbers, eSIMs, and calling plans — in one app</span></div>
                            <div class="headline">Stay connected everywhere with <span class="accent">spacechip</span></div>
                            <div class="lead">Pick a destination, choose a plan, and activate instantly. Keep your number private, get reliable coverage, and manage everything from one place.</div>
                            <div class="stat-row">
                                <div class="stat"><strong>30M+</strong><span>users</span></div>
                                <div class="stat"><strong>190+</strong><span>countries</span></div>
                                <div class="stat"><strong>Instant</strong><span>activation</span></div>
                            </div>
                            <div class="cta-row">
                                <a class="store store-hero store-hero-primary" href="#" aria-label="Download on Google Play Store">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 4.3c0-.8.8-1.3 1.5-1l14.9 8.6c.7.4.7 1.5 0 1.9L4.5 22.4c-.7.4-1.5-.1-1.5-1V4.3Z" fill="currentColor"/>
                                    </svg>
                                    <div class="store-text">
                                        <span class="store-kicker">Download on</span>
                                        <span class="store-label">Google Play Store</span>
                                    </div>
                                </a>
                                <a class="store store-hero store-hero-secondary" href="#" aria-label="Download on App Store">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16.9 13.2c0-1.9 1.6-2.9 1.7-3-1-1.4-2.5-1.6-3-1.6-1.3-.1-2.5.8-3.1.8-.6 0-1.6-.8-2.7-.8-1.4 0-2.7.8-3.4 2.1-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.6 2.2 2.7 2.1 1.1 0 1.5-.7 2.8-.7 1.3 0 1.7.7 2.9.7 1.2 0 1.9-1.1 2.6-2.1.8-1.2 1.1-2.4 1.1-2.5-.1 0-2.7-1.1-2.7-3.6Z" fill="currentColor"/>
                                        <path d="M14.8 6.9c.6-.8 1-1.9.9-3-1 .1-2.1.7-2.7 1.5-.6.7-1.1 1.9-.9 3 1.1.1 2.1-.6 2.7-1.5Z" fill="currentColor"/>
                                    </svg>
                                    <div class="store-text">
                                        <span class="store-kicker">Download on</span>
                                        <span class="store-label">App Store</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="panel">
                            <div class="panel-top">
                                <div class="panel-title">Find your plan</div>
                                <div class="segmented">
                                    <a href="/allassets?tab=countries" class="seg active" style="text-decoration:none">Data eSIMs</a>
                                    <a href="/allassets?tab=virtual" class="seg" style="text-decoration:none">Virtual Phone Numbers</a>
                                    <a href="/allassets?tab=countries&type=calls" class="seg" style="text-decoration:none">Data + Calls eSIMs</a>
                                </div>
                                <div class="search">
                                    <input type="text" id="landingSearch" placeholder="Search country or region">
                                    <svg style="position:absolute;left:14px;top:50%;transform:translateY(-50%);height:18px;width:18px;color:rgba(15,31,31,.48)" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="2"/>
                                        <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                    <div id="searchIndicator" class="hidden" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:.05em">Searching...</div>
                                </div>
                                <div class="toggle">
                                    <button class="t active" type="button" data-plan-toggle="countries" aria-pressed="true">Countries Plan</button>
                                    <button class="t" type="button" data-plan-toggle="regions" aria-pressed="false">Regional Plan</button>
                                </div>
                            </div>
                            <div class="quick">
                                <div class="quick-item">
                                    <div class="qi-ico">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 22s8-4 8-10V6l-8-4-8 4v6c0 6 8 10 8 10Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                            <path d="M9 12l2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="qi-title">Secure & private</div>
                                        <div class="qi-desc">Use virtual numbers to keep your personal number protected.</div>
                                    </div>
                                </div>
                                <div class="quick-item">
                                    <div class="qi-ico">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2v20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M7 6h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            <path d="M7 18h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="qi-title">Fast activation</div>
                                        <div class="qi-desc">Buy and activate plans in minutes — no waiting lines.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section">
                <div class="container">
                    <div class="section-head">
                        <div>
                            <div data-plan-head="countries">
                                <div class="section-title">Popular Countries</div>
                                <div class="section-sub">Start with a top destination and choose a plan that fits your trip.</div>
                            </div>
                            <div class="hidden" data-plan-head="regions">
                                <div class="section-title">Regional Plans</div>
                                <div class="section-sub">Pick a region for multi-country coverage and flexible bundles.</div>
                            </div>
                        </div>
                        <div class="badge" style="border-color:rgba(20,84,84,.18)"><span class="badge-dot"></span><span>Updated daily</span></div>
                    </div>
                    <div class="grid" data-plan-section="countries" id="popularCountriesGrid">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="skeleton-card">
                                <div class="country-left">
                                    <div class="skeleton-flag skeleton"></div>
                                    <div class="c-meta">
                                        <div class="skeleton-text-lg skeleton"></div>
                                        <div class="skeleton-text-sm skeleton"></div>
                                    </div>
                                </div>
                                <div class="country-right">
                                    <div class="skeleton-text-sm skeleton"></div>
                                    <div class="skeleton-btn skeleton"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <div class="grid hidden" data-plan-section="regions" id="popularRegionsGrid">
                        @for ($i = 0; $i < 6; $i++)
                            <div class="skeleton-card">
                                <div class="country-left">
                                    <div class="skeleton-flag skeleton"></div>
                                    <div class="c-meta">
                                        <div class="skeleton-text-lg skeleton"></div>
                                        <div class="skeleton-text-sm skeleton"></div>
                                    </div>
                                </div>
                                <div class="country-right">
                                    <div class="skeleton-text-sm skeleton"></div>
                                    <div class="skeleton-btn skeleton"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <div class="more" data-plan-section="countries">
                        <a href="/allassets" class="btn" style="text-decoration:none">View More</a>
                    </div>
                    <div class="more hidden" data-plan-section="regions">
                        <a href="/allassets" class="btn" style="text-decoration:none">View Regional Plans</a>
                    </div>
                </div>
            </section>

            <footer>
                <div class="container">
                    <div class="footer">
                        <div>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="logo" style="height:34px;width:34px;border-radius:14px">SC</div>
                                <div style="font-weight:800;color:rgba(20,84,84,.92)">spacechip</div>
                            </div>
                            <div style="margin-top:10px;color:rgba(15,31,31,.64);max-width:34ch;font-size:14px;line-height:1.6">Be local anywhere with flexible plans for data, calls, and privacy-first numbers.</div>
                            <div class="social">
                                <a class="sbtn" href="#" aria-label="Facebook">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M14 8h2V5h-2c-2.2 0-4 1.8-4 4v2H8v3h2v7h3v-7h2.2l.8-3H13V9c0-.6.4-1 1-1Z" fill="currentColor"/>
                                    </svg>
                                </a>
                                <a class="sbtn" href="#" aria-label="Instagram">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5Zm10 2H7a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3Z" fill="currentColor"/>
                                        <path d="M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z" fill="currentColor"/>
                                        <path d="M17.5 6.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z" fill="currentColor"/>
                                    </svg>
                                </a>
                                <a class="sbtn" href="#" aria-label="YouTube">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M21.6 7.2a3 3 0 0 0-2.1-2.1C17.7 4.6 12 4.6 12 4.6s-5.7 0-7.5.5A3 3 0 0 0 2.4 7.2 31 31 0 0 0 2 12a31 31 0 0 0 .4 4.8 3 3 0 0 0 2.1 2.1c1.8.5 7.5.5 7.5.5s5.7 0 7.5-.5a3 3 0 0 0 2.1-2.1A31 31 0 0 0 22 12a31 31 0 0 0-.4-4.8Z" fill="currentColor"/>
                                        <path d="M10 15.5V8.5L16 12l-6 3.5Z" fill="#fff"/>
                                    </svg>
                                </a>
                                <a class="sbtn" href="#" aria-label="X">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18.6 2H22l-7.4 8.5L23 22h-6.6l-5.2-6.8L5.2 22H2l8-9.2L1 2h6.8l4.7 6.1L18.6 2Zm-1.2 18h1.9L6.7 4H4.6l12.8 16Z" fill="currentColor"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                        <div>
                            <div style="font-weight:800;color:#0b1a1a">Company</div>
                            <div style="margin-top:10px;display:grid;gap:8px">
                                <a class="links" href="#">Contact Us</a>
                                <a class="links" href="#">Terms &amp; Conditions</a>
                                <a class="links" href="#">Privacy Policy</a>
                                <a class="links" href="#">License Acknowledgement</a>
                            </div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-weight:800;color:#0b1a1a">Download spacechip</div>
                            <div style="margin-top:10px;color:rgba(15,31,31,.64);font-size:14px;line-height:1.6">Get the app to manage eSIMs, numbers, and plans on the go.</div>
                            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
                                <div class="store" role="button" tabindex="0" aria-label="Available on Google Play">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 4.3c0-.8.8-1.3 1.5-1l14.9 8.6c.7.4.7 1.5 0 1.9L4.5 22.4c-.7.4-1.5-.1-1.5-1V4.3Z" fill="currentColor"/>
                                    </svg>
                                    <div style="display:flex;flex-direction:column;line-height:1.1">
                                        <span style="font-size:11px;color:rgba(15,31,31,.62);font-weight:700">Available on</span>
                                        <span style="font-size:14px;font-weight:900;color:rgba(20,84,84,.92)">Google Play</span>
                                    </div>
                                </div>
                                <div class="store" role="button" tabindex="0" aria-label="Available on App Store">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16.9 13.2c0-1.9 1.6-2.9 1.7-3-1-1.4-2.5-1.6-3-1.6-1.3-.1-2.5.8-3.1.8-.6 0-1.6-.8-2.7-.8-1.4 0-2.7.8-3.4 2.1-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.6 2.2 2.7 2.1 1.1 0 1.5-.7 2.8-.7 1.3 0 1.7.7 2.9.7 1.2 0 1.9-1.1 2.6-2.1.8-1.2 1.1-2.4 1.1-2.5-.1 0-2.7-1.1-2.7-3.6Z" fill="currentColor"/>
                                        <path d="M14.8 6.9c.6-.8 1-1.9.9-3-1 .1-2.1.7-2.7 1.5-.6.7-1.1 1.9-.9 3 1.1.1 2.1-.6 2.7-1.5Z" fill="currentColor"/>
                                    </svg>
                                    <div style="display:flex;flex-direction:column;line-height:1.1">
                                        <span style="font-size:11px;color:rgba(15,31,31,.62);font-weight:700">Available on</span>
                                        <span style="font-size:14px;font-weight:900;color:rgba(20,84,84,.92)">App Store</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </main>
        <script>
            (() => {
                let searchableAssets = [];
                const searchInput = document.getElementById('landingSearch');
                const searchIndicator = document.getElementById('searchIndicator');
                const popularCountriesGrid = document.getElementById('popularCountriesGrid');
                const popularRegionsGrid = document.getElementById('popularRegionsGrid');
                let searchTimeout = null;

                const createCountryCard = (item, type) => {
                    const country = document.createElement('div');
                    country.className = 'country';
                    const url = `/assets/${type}/${item.id || item.code || item.slug}`;
                    
                    country.innerHTML = `
                        <div class="country-left">
                            <div class="flag">
                                ${item.flag_url ? `<img src="${item.flag_url}" alt="${item.name} flag" loading="lazy">` : `<span>${item.flag || '🌐'}</span>`}
                            </div>
                            <div class="c-meta">
                                <div class="c-name">${item.name || 'Worldwide'}</div>
                                ${item.note ? `<div class="c-note">${item.note}</div>` : ''}
                                ${item.badge ? `<div class="c-pill">${item.badge}</div>` : ''}
                            </div>
                        </div>
                        <div class="country-right">
                            <div class="price">${item.starting_price_formatted || 'View'} <span>${item.starting_price_formatted ? 'from' : 'plans'}</span></div>
                            <a href="${url}" class="mini-btn" style="text-decoration:none">View plans</a>
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
                            popularCountriesGrid.innerHTML = '';
                            (data.popularCountries || []).forEach(country => {
                                popularCountriesGrid.appendChild(createCountryCard(country, 'country'));
                            });
                        }

                        // Render Popular Regions
                        if (popularRegionsGrid) {
                            popularRegionsGrid.innerHTML = '';
                            (data.popularRegions || []).forEach(region => {
                                popularRegionsGrid.appendChild(createCountryCard(region, 'region'));
                            });
                        }

                    } catch (error) {
                        console.error('Error fetching landing data:', error);
                    }
                };

                fetchData();

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
        </script>
    </body>
</html>
