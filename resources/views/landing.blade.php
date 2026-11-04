<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'spacechip') }}">
        <title>{{ config('app.name', 'spacechip') }}</title>
        @include('partials.head-assets')
            <style>
                :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif}
                *{box-sizing:border-box}
                body{margin:0;color:var(--text);font-family:var(--font-sans);min-height:100vh;position:relative;overflow-x:hidden;display:flex;flex-direction:column;background:radial-gradient(900px 520px at 10% -10%, rgb(var(--accent-rgb) / .10) 0%, transparent 60%),
                var(--bg)}
                body::before{content:"";position:fixed;inset:-20%;background:radial-gradient(640px 460px at 90% 6%, rgb(var(--accent-rgb) / .07) 0%, transparent 70%);
                    filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}
                body::after{content:"";position:fixed;left:-120px;top:-120px;width:520px;height:520px;border-radius:9999px;background:conic-gradient(from 220deg, rgb(var(--accent-rgb) / .38), rgb(var(--accent-fill-hover-rgb) / .26), rgb(var(--accent-rgb) / .18));filter:blur(34px);opacity:.6;z-index:-1;pointer-events:none}
                a{color:inherit;text-decoration:none}
                .container{max-width:1120px;margin:0 auto;padding:0 24px}
                .header{padding:16px 0;display:flex;align-items:center;justify-content:space-between}
                .brand-wrap{display:flex;align-items:center;gap:10px}
                .brand{font-weight:700;letter-spacing:.02em;color:rgb(var(--accent-text-rgb) / .92)}
                .actions{display:flex;gap:12px;align-items:center}
                .btn-primary{padding:10px 16px;border-radius:9999px;background:var(--accent-fill);color:#fff;font-size:14px;font-weight:600;box-shadow:0 14px 35px rgba(0,0,0,.14),0 2px 6px rgba(0,0,0,.06);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
                .btn-secondary{padding:10px 16px;border-radius:9999px;background:rgb(var(--surface-rgb) / .75);backdrop-filter:blur(10px);border:1px solid rgb(var(--ink-rgb) / .18);color:rgb(var(--accent-text-rgb) / .92);font-size:14px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
                .pill{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:9999px;background:rgb(var(--surface-rgb) / .78);backdrop-filter:blur(10px);border:1px solid rgb(var(--ink-rgb) / .14);font-size:14px;color:rgb(var(--ink-rgb) / .72)}
                .hero{padding:24px 0 14px}
                .hero-grid{display:grid;grid-template-columns:1fr;gap:14px;align-items:center}
                .badge{display:inline-flex;align-items:center;gap:10px;padding:8px 12px;border-radius:9999px;background:rgb(var(--surface-rgb) / .72);border:1px solid rgb(var(--accent-rgb) / .22);backdrop-filter:blur(10px);color:rgb(var(--ink-rgb) / .74);font-size:13px;font-weight:600}
                .badge-dot{height:8px;width:8px;border-radius:9999px;background:var(--accent-fill)}
                .headline{margin-top:14px;font-size:34px;line-height:1.08;font-weight:750;letter-spacing:-.03em;color:var(--text)}
                .headline .accent{background:var(--accent-fill);-webkit-background-clip:text;background-clip:text;color:transparent}
                .lead{margin-top:12px;font-size:16px;line-height:1.6;color:rgb(var(--ink-rgb) / .72);max-width:46ch}
                .stat-row{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px}
                .stat{display:inline-flex;align-items:center;gap:10px;padding:10px 12px;border-radius:16px;background:rgb(var(--surface-rgb) / .72);backdrop-filter:blur(10px);border:1px solid rgb(var(--ink-rgb) / .12);box-shadow:0 10px 30px rgba(0,0,0,.06)}
                .stat strong{font-size:14px}
                .stat span{font-size:13px;color:rgb(var(--ink-rgb) / .66)}
                .cta-row{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;align-items:center}
                .store-hero{min-width:230px;justify-content:flex-start}
                .store-hero svg{flex:0 0 auto;display:block}
                .store-text{display:flex;flex-direction:column;line-height:1.1}
                .store-kicker{font-size:11px;font-weight:700;color:rgb(var(--ink-rgb) / .62)}
                .store-label{font-size:14px;font-weight:900;color:rgb(var(--accent-text-rgb) / .92)}
                .store.store-hero-primary{background:var(--accent-fill);border-color:transparent;color:#fff;box-shadow:0 18px 45px rgba(0,0,0,.18),0 2px 10px rgba(0,0,0,.08)}
                .store.store-hero-primary .store-kicker{color:rgba(255,255,255,.82)}
                .store.store-hero-primary .store-label{color:#fff}
                .store.store-hero-secondary{background:rgb(var(--surface-rgb) / .78);border-color:rgb(var(--ink-rgb) / .10);color:rgb(var(--ink-rgb) / .88)}
                .store.store-hero-secondary .store-label{color:rgb(var(--ink-rgb) / .88)}
                .cta-btn{display:inline-flex;align-items:center;gap:12px;padding:10px 14px;border-radius:9999px;text-decoration:none;border:1px solid rgb(var(--ink-rgb) / .14);background:rgb(var(--surface-rgb) / .72);backdrop-filter:blur(10px);box-shadow:0 12px 28px rgba(0,0,0,.06)}
                .cta-btn.primary{background:var(--accent-fill);border-color:transparent;box-shadow:0 18px 45px rgba(0,0,0,.18),0 2px 10px rgba(0,0,0,.08);color:#fff}
                .cta-btn.secondary{background:rgb(var(--surface-rgb) / .78);border-color:rgb(var(--ink-rgb) / .18);color:rgb(var(--accent-text-rgb) / .92)}
                .cta-btn .ico{height:34px;width:34px;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:rgb(var(--ink-rgb) / .06);color:rgb(var(--accent-text-rgb) / .92);flex:0 0 auto}
                .cta-btn.primary .ico{background:rgb(var(--surface-rgb) / .18);color:#fff}
                .cta-btn .txt{display:flex;flex-direction:column;line-height:1.05}
                .cta-btn .kicker{font-size:11px;font-weight:700;color:rgb(var(--ink-rgb) / .62)}
                .cta-btn.primary .kicker{color:rgba(255,255,255,.82)}
                .cta-btn .label{font-size:14px;font-weight:900;letter-spacing:-.01em}
                .cta-btn.primary .label{color:#fff}
                .cta-btn:hover{transform:translateY(-1px)}
                /* Destination finder (hero search) */
                .finder-label{display:block;margin-top:26px;font-size:15px;font-weight:650;color:var(--text)}
                .finder{margin-top:10px;position:relative;max-width:480px}
                .finder input{width:100%;padding:18px 70px 18px 22px;border-radius:20px;background:var(--surface);border:1px solid rgb(var(--ink-rgb) / .12);box-shadow:0 18px 45px rgba(0,0,0,.10);outline:none;font-size:16px;color:var(--text);transition:border-color .2s,box-shadow .2s}
                .finder input:focus{border-color:rgb(var(--accent-rgb) / .55);box-shadow:0 0 0 4px rgb(var(--accent-rgb) / .14),0 18px 45px rgba(0,0,0,.10)}
                .finder-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);height:46px;width:46px;border-radius:14px;background:var(--accent-fill);color:#fff;display:flex;align-items:center;justify-content:center;pointer-events:none}
                .finder-indicator{position:absolute;right:66px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:700;color:var(--accent-text);text-transform:uppercase;letter-spacing:.05em}
                .toggle{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
                .toggle .t{padding:9px 14px;border-radius:9999px;background:rgb(var(--surface-rgb) / .75);border:1px solid rgb(var(--ink-rgb) / .10);font-size:13px;font-weight:650;color:rgb(var(--ink-rgb) / .62)}
                .toggle .t.active{border-color:rgb(var(--accent-rgb) / .32);background:var(--accent-soft);color:rgb(var(--accent-text-rgb) / .92)}

                /* Hero visual: iPhone 15 Pro–proportioned frame (or a lifestyle photo) on a brand glow */
                .hero-visual{position:relative;display:flex;justify-content:center;align-items:center;min-height:560px;padding:20px 0}
                .hv-glow{position:absolute;inset:6% 4%;border-radius:40px;background:radial-gradient(60% 55% at 50% 45%, rgb(var(--accent-rgb) / .38), transparent 70%);filter:blur(30px);z-index:0}
                .hv-photo{position:relative;z-index:1;width:100%;max-width:460px;aspect-ratio:9/11;object-fit:cover;border-radius:32px;box-shadow:0 40px 90px rgba(0,0,0,.28)}
                .phones{--w:270px;position:relative;z-index:1;width:calc(var(--w) * 1.78);height:calc(var(--w) * 2.24)}
                .iphone{position:absolute;width:var(--w);aspect-ratio:1/2.06;padding:calc(var(--w) * .038);border-radius:calc(var(--w) * .19);background:linear-gradient(145deg,#3a3a3c,#1c1c1e 45%,#2c2c2e);box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.14),0 0 0 1px rgba(0,0,0,.6),0 50px 100px -20px rgba(0,0,0,.45),0 30px 60px -30px rgba(0,0,0,.5);transform:rotate(var(--r));animation:hv-float 7s ease-in-out infinite}
                .iphone-front{--r:-4deg;left:0;bottom:0;z-index:2}
                .iphone-back{--r:13deg;right:calc(var(--w) * .2);top:0;z-index:1;animation-delay:-3.5s}
                .iphone-screen{position:relative;height:100%;border-radius:calc(var(--w) * .152);overflow:hidden;background:#000}
                .iphone-screen img{display:block;width:100%;height:100%;object-fit:cover;object-position:top}
                .iphone-island{position:absolute;top:2.2%;left:50%;transform:translateX(-50%);width:31%;height:4.4%;border-radius:9999px;background:#000}
                .iphone-btn{position:absolute;width:3px;border-radius:2px;background:#2c2c2e}
                .iphone-btn-action{left:-3px;top:17%;height:5%}
                .iphone-btn-vol-up{left:-3px;top:25%;height:9%}
                .iphone-btn-vol-down{left:-3px;top:36%;height:9%}
                .iphone-btn-power{right:-3px;top:28%;height:14%}
                .hv-chip{position:absolute;z-index:2;display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:16px;background:rgb(var(--surface-rgb) / .86);backdrop-filter:blur(14px);border:1px solid rgb(var(--ink-rgb) / .10);box-shadow:0 18px 40px rgba(0,0,0,.14);font-size:13px;font-weight:650;color:var(--text)}
                .hv-chip strong{color:var(--accent-text)}
                .hv-chip-bottom{bottom:7%;right:0}
                .hv-dot{height:8px;width:8px;border-radius:9999px;background:#30d158;box-shadow:0 0 0 4px rgb(48 209 88 / .2)}
                @keyframes hv-float{0%,100%{transform:rotate(var(--r)) translateY(0)}50%{transform:rotate(var(--r)) translateY(-10px)}}
                @media(prefers-reduced-motion:reduce){.iphone{animation:none}}
                @media(max-width:859px){.hero-visual{min-height:0;padding:28px 0 8px}.phones{--w:min(190px, 46vw)}.hv-chip{font-size:12px;padding:8px 12px}.iphone{box-shadow:inset 0 0 0 1.5px rgba(255,255,255,.14),0 0 0 1px rgba(0,0,0,.6),0 24px 40px -18px rgba(0,0,0,.35)}}

                /* Phones: iOS Safari can leave backdrop-filter layers unpainted when they sit over the fixed,
                   blurred page glows (blank pills, invisible cards). Use solid surfaces and unblurred glows instead. */
                @media(max-width:859px){
                    body::before,body::after{filter:none}
                    body::after{display:none}
                    .badge,.pill,.stat,.store,.hv-chip,.country,.skeleton-card,.more .btn{-webkit-backdrop-filter:none;backdrop-filter:none;background:var(--surface)}
                    .skeleton-card{border-color:rgb(var(--ink-rgb) / .10);box-shadow:0 8px 22px rgba(0,0,0,.05)}
                }
                .section{padding:18px 0 0}
                .section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap}
                .section-title{font-size:18px;font-weight:800;letter-spacing:-.01em;color:var(--text)}
                .section-sub{color:rgb(var(--ink-rgb) / .64);font-size:14px}
                .hidden{display:none!important}
                .grid{margin-top:14px;display:grid;grid-template-columns:1fr;gap:12px}
                .country{padding:14px 14px 12px;border-radius:18px;background:rgb(var(--surface-rgb) / .75);backdrop-filter:blur(12px);border:1px solid rgb(var(--ink-rgb) / .12);box-shadow:0 14px 35px rgba(0,0,0,.07);display:flex;justify-content:space-between;gap:12px;align-items:center}
                .country-left{display:flex;gap:12px;align-items:center;min-width:0}
                .flag{height:32px;width:32px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgb(var(--surface-rgb) / .92);border:1px solid rgb(var(--ink-rgb) / .12);overflow:hidden;flex:0 0 auto;font-size:22px}
                .flag img{height:100%;width:100%;object-fit:cover;display:block}
                .c-meta{min-width:0}
                .c-name{font-weight:750;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
                .c-note {
                    font-size: 13px;
                    color: rgb(var(--ink-rgb) / .62);
                    margin-top: 3px;
                    font-weight: 500;
                }
                .c-pill {
                    display: inline-block;
                    margin-top: 8px;
                    padding: 4px 10px;
                    background: var(--accent-soft);
                    color: var(--accent-text);
                    font-size: 11px;
                    font-weight: 800;
                    border-radius: 99px;
                    text-transform: uppercase;
                    letter-spacing: .02em;
                }
                .dark .c-note { color: rgba(255,255,255,.5); }

                .country-right {
                    text-align: right;
                    display: flex;
                    flex-direction: column;
                    align-items: flex-end;
                    gap: 8px;
                }
                .country-right .price {
                    font-weight: 800;
                    font-size: 17px;
                    color: var(--text);
                }
                .country-right .price span {
                    font-size: 12px;
                    color: rgb(var(--ink-rgb) / .48);
                    font-weight: 600;
                    margin-left: 2px;
                }
                .dark .country-right .price { color: #fff; }
                .dark .country-right .price span { color: rgba(255,255,255,.4); }

                .mini-btn {
                    padding: 8px 16px;
                    border-radius: 12px;
                    border: 1.5px solid rgb(var(--ink-rgb) / .12);
                    font-size: 13px;
                    font-weight: 700;
                    color: var(--accent-text);
                    background: var(--surface);
                    transition: all .2s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                }
                .mini-btn.is-loading{pointer-events:none;opacity:.75}
                .btn-spinner{width:14px;height:14px;border-radius:9999px;border:2px solid rgb(var(--accent-strong-rgb) / .25);border-top-color:rgb(var(--accent-strong-rgb) / .92);animation:btn-spin .8s linear infinite}
                @keyframes btn-spin{to{transform:rotate(360deg)}}
                .mini-btn:hover {
                    background: var(--bg);
                    border-color: var(--accent-strong);
                }
                .dark .mini-btn {
                    background: rgb(var(--surface-rgb) / .05);
                    border-color: rgb(var(--surface-rgb) / .1);
                    color: #fff;
                }
                .dark .mini-btn:hover {
                    background: rgb(var(--surface-rgb) / .1);
                    border-color: var(--surface);
                }
                .more{margin-top:14px;display:flex;justify-content:center}
                .more .btn{padding:10px 18px;border-radius:9999px;background:rgb(var(--surface-rgb) / .78);border:1px solid rgb(var(--ink-rgb) / .14);box-shadow:0 12px 28px rgba(0,0,0,.06);font-weight:700;color:rgb(var(--accent-text-rgb) / .92)}



                .skeleton-card {
                    padding: 14px 14px 12px;
                    border-radius: 18px;
                    background: rgb(var(--surface-rgb) / .75);
                    backdrop-filter: blur(12px);
                    border: 1px solid rgb(var(--ink-rgb) / .12);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    gap: 12px;
                }

                .skeleton-flag { width: 32px; height: 32px; border-radius: 12px; }
                .skeleton-text-lg { width: 100px; height: 14px; margin-bottom: 6px; }
                .skeleton-text-sm { width: 60px; height: 10px; }
                .skeleton-btn { width: 60px; height: 28px; border-radius: 9999px; }

                main{flex:1;display:flex;flex-direction:column}
                footer{margin-top:auto;border-top:1px solid rgb(var(--ink-rgb) / .10);background:rgb(var(--surface-rgb) / .35);backdrop-filter:blur(10px)}
                .footer{padding:34px 0;display:grid;grid-template-columns:1fr;gap:22px}
                .links{color:rgb(var(--ink-rgb) / .64);font-size:14px;line-height:22px;text-decoration:none}
                .links:hover{color:rgb(var(--accent-text-rgb) / .92)}
                .social{display:flex;gap:10px;margin-top:12px}
                .sbtn{height:38px;width:38px;border-radius:14px;background:rgb(var(--surface-rgb) / .78);border:1px solid rgb(var(--ink-rgb) / .14);display:flex;align-items:center;justify-content:center;color:rgb(var(--accent-text-rgb) / .88)}
                .store{display:inline-flex;align-items:center;gap:10px;padding:10px 14px;background:rgb(var(--surface-rgb) / .78);border:1px solid rgb(var(--ink-rgb) / .14);border-radius:9999px;box-shadow:0 12px 28px rgba(0,0,0,.06);cursor:pointer;user-select:none}
                @media(min-width:860px){.hero-grid{grid-template-columns:1.1fr .9fr;gap:40px}.grid{grid-template-columns:repeat(3,1fr)}.footer{grid-template-columns:repeat(3,1fr);align-items:start}.headline{font-size:46px}}
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
                    .footer .store{width:100%;justify-content:center}
                }
                @media(max-width:380px){
                    .pill{display:none}
                    .headline{font-size:28px}
                }
                .footer-wordmark{height:26px;width:auto;display:block}
        </style>
    </head>
    <body>
        @include('partials.skip-link')
        @include('partials.site-header')
        <main id="main" tabindex="-1">
            <section class="hero">
                <div class="container">
                    <div class="hero-grid">
                        <div>
                            <div class="badge"><span class="badge-dot"></span><span>Virtual numbers, eSIMs, and calling plans — in one app</span></div>
                            <h1 class="headline">Stay connected everywhere with <span class="accent">spacechip</span></h1>
                            <div class="lead">Pick a destination, choose a plan, and activate instantly. Keep your number private, get reliable coverage, and manage everything from one place.</div>

                            {{-- Destination search: landing.js jumps to the first matching country or region. --}}
                            <label class="finder-label" for="landingSearch">Where do you need mobile data?</label>
                            <div class="finder">
                                <input type="text" id="landingSearch" placeholder="Search for destination" autocomplete="off">
                                <div id="searchIndicator" class="hidden finder-indicator">Searching...</div>
                                <span class="finder-btn" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="2.2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                                </span>
                            </div>
                            <div class="toggle">
                                <button class="t active" type="button" data-plan-toggle="countries" aria-pressed="true">Countries</button>
                                <button class="t" type="button" data-plan-toggle="regions" aria-pressed="false">Regions</button>
                                <a class="t" href="/dashboard?tab=social">Social numbers</a>
                            </div>
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
                        {{-- Hero visual: a lifestyle photo when public/images/hero-person.jpg exists, otherwise two
                             overlapping iPhones (plan picker tilted behind, home screen in front). --}}
                        <div class="hero-visual" aria-hidden="true">
                            <div class="hv-glow"></div>
                            @if (file_exists(public_path('images/hero-person.jpg')))
                                <img class="hv-photo" src="{{ asset('images/hero-person.jpg') }}" alt="" width="900" height="1100" fetchpriority="high">
                            @else
                                <div class="phones">
                                    @foreach (['back' => 'app-plans.jpg', 'front' => 'app-home.jpg'] as $position => $screen)
                                        <div class="iphone iphone-{{ $position }}">
                                            <span class="iphone-btn iphone-btn-action"></span>
                                            <span class="iphone-btn iphone-btn-vol-up"></span>
                                            <span class="iphone-btn iphone-btn-vol-down"></span>
                                            <span class="iphone-btn iphone-btn-power"></span>
                                            <div class="iphone-screen">
                                                <img src="{{ asset('images/'.$screen) }}?v={{ @filemtime(public_path('images/'.$screen)) }}" alt="" width="640" height="1391" @if ($position === 'front') fetchpriority="high" @else loading="lazy" @endif>
                                                <span class="iphone-island"></span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <div class="hv-chip hv-chip-bottom"><span class="hv-dot"></span><strong>190+</strong> countries · instant eSIM</div>
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
                        <div class="badge" style="border-color:rgb(var(--ink-rgb) / .18)"><span class="badge-dot"></span><span>Updated daily</span></div>
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
                        @auth
                            <a href="{{ route('dashboard', ['tab' => 'countries']) }}" class="btn" style="text-decoration:none">View More</a>
                        @else
                            <a href="/allassets?tab=countries" class="btn" style="text-decoration:none">View More</a>
                        @endauth
                    </div>
                    <div class="more hidden" data-plan-section="regions">
                        @auth
                            <a href="{{ route('dashboard', ['tab' => 'regions']) }}" class="btn" style="text-decoration:none">View Regional Plans</a>
                        @else
                            <a href="/allassets?tab=regions" class="btn" style="text-decoration:none">View Regional Plans</a>
                        @endauth
                    </div>
                </div>
            </section>

            @include('partials.landing-explore')

            <footer>
                <div class="container">
                    <div class="footer">
                        <div>
                            <div style="color:var(--text)">@include('partials.brand-wordmark', ['class' => 'footer-wordmark'])</div>
                            <div style="margin-top:10px;color:rgb(var(--ink-rgb) / .64);max-width:34ch;font-size:14px;line-height:1.6">Be local anywhere with flexible plans for data, calls, and privacy-first numbers.</div>
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
                            <div style="font-weight:800;color:var(--text)">Company</div>
                            <div style="margin-top:10px;display:grid;gap:8px">
                                <a class="links" href="{{ route('contact') }}">Contact Us</a>
                                <a class="links" href="{{ route('terms') }}">Terms &amp; Conditions</a>
                                <a class="links" href="{{ route('privacy') }}">Privacy Policy</a>
                                <a class="links" href="{{ route('refund') }}">Refund Policy</a>
                                <a class="links" href="{{ route('acceptable.use') }}">Acceptable Use</a>
                                <a class="links" href="{{ route('cookies') }}">Cookie Policy</a>
                                <a class="links" href="{{ route('privacy.request') }}">Privacy Request</a>
                                <a class="links" href="{{ route('accessibility') }}">Accessibility</a>
                                <a class="links" href="#">License Acknowledgement</a>
                            </div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-weight:800;color:var(--text)">Download spacechip</div>
                            <div style="margin-top:10px;color:rgb(var(--ink-rgb) / .64);font-size:14px;line-height:1.6">Get the app to manage eSIMs, numbers, and plans on the go.</div>
                            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
                                <div class="store" role="button" tabindex="0" aria-label="Available on Google Play">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 4.3c0-.8.8-1.3 1.5-1l14.9 8.6c.7.4.7 1.5 0 1.9L4.5 22.4c-.7.4-1.5-.1-1.5-1V4.3Z" fill="currentColor"/>
                                    </svg>
                                    <div style="display:flex;flex-direction:column;line-height:1.1">
                                        <span style="font-size:11px;color:rgb(var(--ink-rgb) / .62);font-weight:700">Available on</span>
                                        <span style="font-size:14px;font-weight:900;color:rgb(var(--accent-text-rgb) / .92)">Google Play</span>
                                    </div>
                                </div>
                                <div class="store" role="button" tabindex="0" aria-label="Available on App Store">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16.9 13.2c0-1.9 1.6-2.9 1.7-3-1-1.4-2.5-1.6-3-1.6-1.3-.1-2.5.8-3.1.8-.6 0-1.6-.8-2.7-.8-1.4 0-2.7.8-3.4 2.1-1.5 2.6-.4 6.5 1.1 8.6.7 1 1.6 2.2 2.7 2.1 1.1 0 1.5-.7 2.8-.7 1.3 0 1.7.7 2.9.7 1.2 0 1.9-1.1 2.6-2.1.8-1.2 1.1-2.4 1.1-2.5-.1 0-2.7-1.1-2.7-3.6Z" fill="currentColor"/>
                                        <path d="M14.8 6.9c.6-.8 1-1.9.9-3-1 .1-2.1.7-2.7 1.5-.6.7-1.1 1.9-.9 3 1.1.1 2.1-.6 2.7-1.5Z" fill="currentColor"/>
                                    </svg>
                                    <div style="display:flex;flex-direction:column;line-height:1.1">
                                        <span style="font-size:11px;color:rgb(var(--ink-rgb) / .62);font-weight:700">Available on</span>
                                        <span style="font-size:14px;font-weight:900;color:rgb(var(--accent-text-rgb) / .92)">App Store</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </main>
        <script src="{{ asset('js/landing.js') }}?v={{ @filemtime(public_path('js/landing.js')) }}"></script>
        <script src="{{ asset('pwa-install.js') }}" defer></script>
    </body>
</html>
