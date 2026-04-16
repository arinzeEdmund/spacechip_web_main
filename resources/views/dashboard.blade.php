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
        
        .controls-row{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:24px}
        .search-bar{margin-bottom:0;position:relative;max-width:none;flex:1;min-width:280px}
        .search-bar input{width:100%;padding:16px 20px 16px 52px;border-radius:20px;background:rgba(255,255,255,.85);backdrop-filter:blur(10px);border:1px solid rgba(20,84,84,.15);box-shadow:0 14px 35px rgba(15,31,31,.08);outline:none;font-size:16px;color:#0b1a1a;transition:all .3s}
        .search-bar input:focus{border-color:var(--primary);box-shadow:0 14px 35px rgba(242,116,87,.12)}
        .search-bar svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);height:22px;width:22px;color:rgba(15,31,31,.4);pointer-events:none}

        .asset-toggles{display:flex;gap:10px;margin-bottom:0;background:rgba(255,255,255,.5);padding:6px;border-radius:9999px;border:1px solid rgba(20,84,84,.1);width:fit-content}
        .asset-toggles button{padding:12px 24px;border-radius:9999px;border:none;background:transparent;font-size:14px;font-weight:700;color:rgba(15,31,31,.6);cursor:pointer;transition:all .2s}
        .asset-toggles button.active{background:#fff;color:rgba(20,84,84,.92);box-shadow:0 10px 25px rgba(15,31,31,.08);border:1px solid rgba(20,84,84,.1)}
        .nav-row{display:none}
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
            .controls-row{margin-bottom:18px}
            .search-bar{min-width:0}
            .search-bar input{padding:14px 16px 14px 46px;border-radius:18px;font-size:15px}
            .search-bar svg{left:16px;height:20px;width:20px}
            .asset-toggles{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:8px;border-radius:24px}
            .asset-toggles button{width:100%;padding:10px 12px;font-size:13px;text-align:center}
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

        .vn-panel{padding:18px;border-radius:24px;background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07)}
        .vn-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:12px}
        .vn-title{font-weight:950;color:#0b1a1a;font-size:18px}
        .vn-sub{color:rgba(15,31,31,.62);font-weight:650;font-size:13px;margin-top:6px}
        .vn-controls{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-top:12px}
        .vn-controls input{padding:12px 14px;border-radius:16px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.78);outline:none;min-width:280px}
        .vn-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .vn-btn{padding:10px 14px;border-radius:9999px;background:rgba(255,255,255,.85);border:1px solid rgba(20,84,84,.14);font-size:13px;font-weight:900;color:rgba(20,84,84,.92);cursor:pointer}
        .vn-btn.primary{background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;border:none}
        .vn-status{margin-top:10px;font-size:13px;color:rgba(15,31,31,.62);font-weight:700}
        .vn-hidden{display:none!important}
        .vn-number-card{padding:14px;border-radius:20px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6);display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        .vn-number-meta{display:flex;flex-direction:column;gap:6px;min-width:0}
        .vn-number-title{font-weight:950;color:#0b1a1a}
        .vn-tags{display:flex;gap:6px;flex-wrap:wrap}
        .vn-tag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);font-weight:900;font-size:11px;color:rgba(15,31,31,.72);background:rgba(255,255,255,.6)}
        .vn-buy{padding:10px 14px;border-radius:14px;background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;font-weight:900;border:none;cursor:pointer;box-shadow:0 8px 20px rgba(242,116,87,.12);white-space:nowrap}
        .vn-chat{margin-top:10px;display:flex;flex-direction:column;gap:10px;max-height:520px;overflow:auto;padding:10px;border-radius:20px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.55)}
        .vn-msg{display:flex;flex-direction:column;gap:6px;max-width:82%}
        .vn-msg.in{align-self:flex-start}
        .vn-msg.out{align-self:flex-end}
        .vn-bubble{padding:10px 12px;border-radius:16px;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.86);color:rgba(15,31,31,.9);font-weight:750;white-space:pre-wrap;word-break:break-word}
        .vn-msg.out .vn-bubble{background:linear-gradient(90deg, rgba(242,116,87,.18), rgba(20,84,84,.12));border-color:rgba(242,116,87,.28)}
        .vn-meta{font-size:11px;color:rgba(15,31,31,.56);font-weight:700}
        .vn-media{display:flex;gap:8px;flex-wrap:wrap}
        .vn-media img{max-width:160px;border-radius:14px;border:1px solid rgba(15,31,31,.10)}
        .vn-compose{margin-top:12px;display:grid;gap:10px}
        .vn-compose input,.vn-compose textarea{width:100%;padding:12px 14px;border-radius:16px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.82);outline:none;font-weight:750;color:rgba(15,31,31,.78)}
        .vn-compose textarea{min-height:88px;resize:vertical}
        .vn-skel-card{padding:14px;border-radius:20px;background:rgba(255,255,255,.65);border:1px solid rgba(20,84,84,.10);display:flex;justify-content:space-between;gap:12px;align-items:center}
        .vn-skel-left{display:flex;align-items:center;gap:12px;min-width:0}
        .vn-skel-flag{width:40px;height:40px;border-radius:14px}
        .vn-skel-lines{display:flex;flex-direction:column;gap:8px;min-width:0}
        .vn-skel-line-lg{width:140px;height:14px;border-radius:8px}
        .vn-skel-line-sm{width:100px;height:12px;border-radius:8px}
        .vn-skel-btn{width:90px;height:34px;border-radius:9999px}
        .wallet-panel{margin-top:14px;margin-bottom:18px;padding:18px;border-radius:26px;position:relative;overflow:hidden;background:
            radial-gradient(900px 220px at 12% 0%, rgba(242,116,87,.18), rgba(242,116,87,0) 60%),
            radial-gradient(700px 240px at 92% 0%, rgba(20,84,84,.16), rgba(20,84,84,0) 60%),
            linear-gradient(180deg, rgba(255,255,255,.86), rgba(255,255,255,.72));
            backdrop-filter:blur(14px);border:1px solid rgba(20,84,84,.14);box-shadow:0 18px 45px rgba(15,31,31,.09)}
        .wallet-panel::before{content:"";position:absolute;inset:-2px;border-radius:28px;padding:1px;background:linear-gradient(120deg, rgba(242,116,87,.35), rgba(20,84,84,.28), rgba(242,116,87,.18));-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
        .wallet-panel::after{content:"";position:absolute;right:-90px;bottom:-110px;width:260px;height:260px;background:radial-gradient(circle at 30% 30%, rgba(242,116,87,.18), rgba(242,116,87,0) 70%);filter:blur(1px);pointer-events:none}
        .wallet-row{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-end;position:relative;z-index:1}
        .wallet-title{font-weight:1000;color:#0b1a1a;letter-spacing:-.01em}
        .wallet-sub{color:rgba(15,31,31,.62);font-weight:700;font-size:13px;margin-top:6px}
        .wallet-status.error{color:rgba(242,116,87,.92)}
        .wallet-status.success{color:rgba(20,84,84,.92)}
        .wallet-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .wallet-balance{font-weight:1000;color:#0b1a1a;font-size:20px;letter-spacing:-.02em;padding:8px 12px;border-radius:16px;background:rgba(255,255,255,.62);border:1px solid rgba(15,31,31,.08)}
        .wallet-input{padding:12px 14px;border-radius:16px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.82);outline:none;min-width:160px;font-weight:800;color:rgba(15,31,31,.78)}
        .wallet-panel .tiny-btn{padding:11px 14px;border-radius:16px;border:1px solid rgba(20,84,84,.14);background:rgba(255,255,255,.78);font-weight:950;color:rgba(20,84,84,.92)}
        .wallet-panel .tiny-btn:hover{background:rgba(255,255,255,.9)}
        .wallet-panel .tiny-btn:active{transform:translateY(1px)}
        .wallet-panel .tiny-btn:first-of-type{background:linear-gradient(90deg, rgba(242,116,87,.95), rgba(20,84,84,.95));border:none;color:#fff;box-shadow:0 10px 28px rgba(20,84,84,.14)}
        .wallet-panel .tiny-btn:first-of-type:hover{filter:saturate(1.05) brightness(1.02)}
        .wallet-tx{margin-top:14px;display:grid;gap:10px;position:relative;z-index:1}
        .wallet-tx-item{padding:12px 14px;border-radius:20px;border:1px solid rgba(15,31,31,.08);background:
            linear-gradient(180deg, rgba(255,255,255,.78), rgba(255,255,255,.58));
            display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
        .wallet-tx-left{display:flex;flex-direction:column;gap:4px}
        .wallet-tx-action{font-weight:950;color:#0b1a1a;text-transform:capitalize}
        .wallet-tx-meta{font-size:12px;color:rgba(15,31,31,.62);font-weight:700}
        .wallet-tx-amt{font-weight:1000;white-space:nowrap;background:linear-gradient(90deg, rgba(242,116,87,.98), rgba(20,84,84,.98));-webkit-background-clip:text;background-clip:text;color:transparent}

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
        .esim-skel-title{width:180px;height:16px;border-radius:10px}
        .esim-skel-pill{width:84px;height:26px;border-radius:9999px}
        .esim-skel-kv{height:54px;border-radius:14px}
        .esim-skel-kv .k{width:74px;height:10px;border-radius:6px}
        .esim-skel-kv .v{width:120px;height:12px;border-radius:6px;margin-top:8px}
        .esim-skel-actions{display:flex;gap:10px;flex-wrap:wrap}
        .esim-skel-action{width:120px;height:38px;border-radius:14px}

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

                <div class="wallet-panel">
                    <div class="wallet-row">
                        <div>
                            <div class="wallet-title">Wallet</div>
                            <div class="wallet-sub">Balance in USD. Deposit with card, or pay instantly with wallet.</div>
                        </div>
                        <div class="wallet-actions">
                            <div class="wallet-balance" id="walletBalance">$0.00</div>
                            <input class="wallet-input" id="walletDepositAmount" type="number" min="1" step="1" placeholder="Deposit (USD)">
                            <button class="tiny-btn" type="button" id="walletDepositBtn">Deposit</button>
                            <button class="tiny-btn" type="button" id="walletRefreshBtn">Refresh</button>
                        </div>
                    </div>
                    <div id="walletStatus" class="wallet-sub wallet-status"></div>
                    <div id="walletTx" class="wallet-tx"></div>
                </div>

                <div class="controls-row">
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
                    <div class="vn-panel">
                        <div id="vnCountriesView">
                            <div class="vn-head">
                                <div>
                                    <div class="vn-title">Virtual Numbers</div>
                                    <div class="vn-sub">Browse supported countries, then choose a number and subscribe monthly.</div>
                                </div>
                                <div class="vn-actions">
                                    <button type="button" class="vn-btn" id="vnMyBtnTop">My Numbers</button>
                                    <button type="button" class="vn-btn primary" id="vnRefreshCountries">Refresh</button>
                                </div>
                            </div>
                            <div class="vn-controls">
                                <input type="text" id="vnCountrySearch" placeholder="Search countries…">
                            </div>
                            <div id="vnStatus" class="vn-status"></div>
                            <div class="grid" id="vnCountriesGrid"></div>
                            <div class="load-more-wrap show" id="vnCountriesLoadMoreWrap">
                                <button class="load-more-btn" type="button" id="vnCountriesLoadMoreBtn">Load more</button>
                            </div>
                        </div>

                        <div id="vnNumbersView" class="vn-hidden">
                            <div class="vn-head">
                                <div>
                                    <div class="vn-title" id="vnNumbersTitle">Available phone numbers</div>
                                    <div class="vn-sub" id="vnNumbersSub"></div>
                                </div>
                                <div class="vn-actions">
                                    <button type="button" class="vn-btn" id="vnBackToCountries">Back</button>
                                    <button type="button" class="vn-btn" id="vnMyBtn">My Numbers</button>
                                    <button type="button" class="vn-btn primary" id="vnRefreshNumbers">Refresh</button>
                                </div>
                            </div>
                            <div class="vn-controls">
                                <input type="text" id="vnNumberSearch" placeholder="Search by number, city, or region…">
                            </div>
                            <div id="vnNumbersStatus" class="vn-status"></div>
                            <div class="grid" id="vnNumbersGrid"></div>
                            <div class="load-more-wrap show" id="vnNumbersLoadMoreWrap">
                                <button class="load-more-btn" type="button" id="vnNumbersLoadMoreBtn">Load more</button>
                            </div>
                        </div>

                        <div id="vnMyView" class="vn-hidden">
                            <div class="vn-head">
                                <div>
                                    <div class="vn-title">My Numbers</div>
                                    <div class="vn-sub">Manage your virtual number subscriptions.</div>
                                </div>
                                <div class="vn-actions">
                                    <button type="button" class="vn-btn" id="vnBackFromMy">Back</button>
                                    <button type="button" class="vn-btn primary" id="vnRefreshMy">Refresh</button>
                                </div>
                            </div>
                            <div id="vnMyStatus" class="vn-status"></div>
                            <div class="grid" id="vnMyGrid"></div>
                        </div>

                        <div id="vnInboxView" class="vn-hidden">
                            <div class="vn-head">
                                <div>
                                    <div class="vn-title" id="vnInboxTitle">Inbox</div>
                                    <div class="vn-sub" id="vnInboxSub"></div>
                                </div>
                                <div class="vn-actions">
                                    <button type="button" class="vn-btn" id="vnBackFromInbox">Back</button>
                                    <button type="button" class="vn-btn primary" id="vnRefreshInbox">Refresh</button>
                                </div>
                            </div>
                            <div id="vnInboxStatus" class="vn-status"></div>
                            <div class="vn-compose" style="margin-top:10px">
                                <input id="vnForwardEmail" type="text" placeholder="Forward incoming to email (optional)">
                                <input id="vnForwardPhone" type="text" placeholder="Forward incoming to phone (optional, E.164 e.g. +14155552671)">
                                <button type="button" class="vn-btn" id="vnSaveForward">Save forwarding</button>
                            </div>
                            <div class="vn-chat" id="vnChat"></div>
                            <div class="vn-compose">
                                <input id="vnComposeTo" type="text" placeholder="To (e.g. +14155552671)">
                                <textarea id="vnComposeBody" placeholder="Message…"></textarea>
                                <input id="vnComposeMedia" type="text" placeholder="MMS media URL (optional)">
                                <button type="button" class="vn-btn primary" id="vnSendMsg">Send</button>
                            </div>
                        </div>
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

        <script src="https://js.paystack.co/v1/inline.js"></script>
        <script>
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

                const state = {
                    countries: { page: 0, hasMore: true, loading: false, q: '' },
                    regions: { page: 0, hasMore: true, loading: false, q: '' },
                    virtual: { page: 0, hasMore: false, loading: false, q: '' },
                };
                let activeTab = 'countries';
                let viewMode = 'assets';
                const esimsState = { page: 0, hasMore: true, loading: false, filter: 'valid' };

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

                const paystackKey = normalizeEnvValue(@json((string) (config('services.paystack.public_key') ?: env('PAYSTACK_PUBLIC_KEY'))));
                const csrfToken = @json(csrf_token());

                const walletEls = {
                    balance: document.getElementById('walletBalance'),
                    amount: document.getElementById('walletDepositAmount'),
                    depositBtn: document.getElementById('walletDepositBtn'),
                    refreshBtn: document.getElementById('walletRefreshBtn'),
                    status: document.getElementById('walletStatus'),
                    tx: document.getElementById('walletTx'),
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

                const renderWalletTx = (items) => {
                    if (!walletEls.tx) return;
                    walletEls.tx.innerHTML = '';
                    if (!Array.isArray(items) || items.length === 0) {
                        return;
                    }
                    items.slice(0, 10).forEach((it) => {
                        const dir = String(it.direction || '');
                        const action = String(it.action || '');
                        const amtMinor = Number(it.amount_minor || 0);
                        const balMinor = Number(it.balance_after_minor || 0);
                        const when = String(it.created_at || '');
                        const sign = dir === 'debit' ? '-' : '+';
                        const amt = `$${(amtMinor / 100).toFixed(2)}`;
                        const bal = `$${(balMinor / 100).toFixed(2)}`;

                        const row = document.createElement('div');
                        row.className = 'wallet-tx-item';
                        row.innerHTML = `
                            <div class="wallet-tx-left">
                                <div class="wallet-tx-action">${esc(action.replaceAll('_', ' '))}</div>
                                <div class="wallet-tx-meta">${when ? esc(new Date(when).toLocaleString()) : ''} · Balance: ${esc(bal)}</div>
                            </div>
                            <div class="wallet-tx-amt">${esc(sign + amt)}</div>
                        `;
                        walletEls.tx.appendChild(row);
                    });
                };

                const refreshWallet = async () => {
                    setWalletStatus('Loading wallet…', 'neutral');
                    try {
                        const res = await fetch('/api/wallet/balance', { headers: { 'Accept': 'application/json' } });
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

                const refreshWalletTx = async () => {
                    try {
                        const res = await fetch('/api/wallet/transactions', { headers: { 'Accept': 'application/json' } });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok || !json.ok) {
                            return;
                        }
                        renderWalletTx(json.items || []);
                    } catch (e) {
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
                                    await refreshWallet();
                                    await refreshWalletTx();
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

                    if (type === 'virtual') {
                        const imgSrc = safeImgSrc(item.flag_url || '');
                        card.innerHTML = `
                            <div class="vnum-top">
                                <div class="vnum-info">
                                    <div class="flag">
                                        ${imgSrc ? `<img src="${imgSrc}" alt="${esc(item.name)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                                    </div>
                                    <div class="vnum-name">${esc(item.name)}</div>
                                </div>
                                <div class="vnum-price-box">
                                    <div class="vnum-price">${esc(item.price_formatted)}<span>/mo</span></div>
                                </div>
                            </div>
                            <div class="vnum-desc">${esc(item.description || 'Virtual phone number for calls and SMS.')}</div>
                            <button class="vnum-btn">Get Number</button>
                        `;
                    } else {
                        const kind = type === 'regions' ? 'region' : 'country';
                        const url = `/assets/${kind}/${encodeURIComponent(String(item.id || ''))}`;
                        const imgSrc = safeImgSrc(item.flag_url || '');
                        card.innerHTML = `
                            <div class="card-left">
                                <div class="flag">
                                    ${imgSrc ? `<img src="${imgSrc}" alt="${esc(item.name)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                                </div>
                                <div class="meta">
                                    <div class="name">${esc(item.name)}</div>
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
                    if (activeTab === 'virtual') {
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
                    const canRenew = item.esim ? item.esim.can_renew : null;
                    const installIos = item.esim ? (item.esim.direct_installation_link_ios || '') : '';
                    const installAndroid = item.esim ? (item.esim.direct_installation_link_android || '') : '';
                    const qrDataUrl = item.esim ? (item.esim.qr_code_data_url || '') : '';
                    const expiresAt = item.expires_at ? new Date(item.expires_at) : null;
                    const expiresLabel = expiresAt ? expiresAt.toLocaleString() : '';
                    const showExpires = !!expiresLabel;
                    const showPuk = !!puk;
                    const showNumber = !!number;
                    const showProviderStatus = !!providerStatus;
                    const showCanRenew = canRenew === true || canRenew === false;

                    const kv = (label, value) => `<div class="kv"><div class="k">${esc(label)}</div><div class="v">${value ? esc(value) : '-'}</div></div>`;
                    const metaParts = [
                        kv('eSIM ID', esimId),
                        kv('ICCID', iccid),
                        kv('Activation Code', activation),
                        showExpires ? kv('Expires', expiresLabel) : '',
                        kv('SM-DP+', smdp),
                        kv('LPA', lpa),
                        showCanRenew ? kv('Renewable', canRenew ? 'Yes' : 'No') : '',
                        showPuk ? kv('PUK', puk) : '',
                        showNumber ? kv('Number', number) : '',
                        showProviderStatus ? kv('Provider Status', providerStatus) : '',
                    ].filter(Boolean).join('');

                    const qrHref = safeHref(qr);
                    const iosHref = safeHref(installIos);
                    const androidHref = safeHref(installAndroid);
                    const qrImg = safeImgSrc(qrDataUrl);
                    const assetType = String(item.asset_type || '');
                    const assetId = String(item.asset_id || '');
                    const topupUrl = (canRenew === true && assetType && assetId && esimId)
                        ? `/assets/${encodeURIComponent(assetType)}/${encodeURIComponent(assetId)}?topup_esim_id=${encodeURIComponent(esimId)}`
                        : '';

                    card.innerHTML = `
                        <div class="esim-top">
                            <div>
                                <div class="esim-title">${esc(title)}</div>
                                <div class="esim-sub">${esc([data, validity].filter(Boolean).join(' • '))}</div>
                            </div>
                            <span class="pill ${status === 'expired' ? 'expired' : ''}">${status === 'expired' ? 'Expired' : (status === 'processing' ? 'Processing' : 'Valid')}</span>
                        </div>
                        <div class="esim-meta">${metaParts}</div>
                        ${qrImg ? `<div class="qr-box"><img alt="eSIM QR code" src="${qrImg}"></div>` : ''}
                        <div class="esim-actions">
                            ${qrHref ? `<a class="mini-link" href="${qrHref}" target="_blank" rel="noopener noreferrer">Open QR</a>` : (qrImg ? `<span class="mini-link secondary">QR Ready</span>` : `<span class="mini-link secondary">No QR</span>`)}
                            ${iosHref ? `<a class="mini-link" href="${iosHref}" target="_blank" rel="noopener noreferrer">Install iOS</a>` : ``}
                            ${androidHref ? `<a class="mini-link" href="${androidHref}" target="_blank" rel="noopener noreferrer">Install Android</a>` : ``}
                            ${topupUrl ? `<a class="mini-link" href="${topupUrl}">Top up</a>` : ``}
                            <a class="mini-link secondary" href="/dashboard">Dashboard</a>
                        </div>
                    `;
                    return card;
                };

                const setEsimsSkeleton = () => {
                    myEsimsGrid.innerHTML = '';
                    for (let i = 0; i < 6; i++) {
                        const sk = document.createElement('div');
                        sk.className = 'esim-card';
                        sk.innerHTML = `
                            <div class="esim-top">
                                <div>
                                    <div class="esim-skel-title skeleton"></div>
                                    <div class="esim-sub"><span class="skeleton" style="display:inline-block;width:160px;height:12px;border-radius:8px"></span></div>
                                </div>
                                <div class="esim-skel-pill skeleton"></div>
                            </div>
                            <div class="esim-meta">
                                <div class="kv esim-skel-kv">
                                    <div class="k skeleton"></div>
                                    <div class="v skeleton"></div>
                                </div>
                                <div class="kv esim-skel-kv">
                                    <div class="k skeleton"></div>
                                    <div class="v skeleton"></div>
                                </div>
                            </div>
                            <div class="esim-skel-actions">
                                <div class="esim-skel-action skeleton"></div>
                                <div class="esim-skel-action skeleton"></div>
                            </div>
                        `;
                        myEsimsGrid.appendChild(sk);
                    }
                };

                const appendEsimsLoadMoreSkeleton = (count = 3) => {
                    myEsimsGrid.querySelectorAll('[data-esim-skeleton="loadmore"]').forEach((el) => el.remove());
                    for (let i = 0; i < count; i++) {
                        const sk = document.createElement('div');
                        sk.className = 'esim-card';
                        sk.setAttribute('data-esim-skeleton', 'loadmore');
                        sk.innerHTML = `
                            <div class="esim-top">
                                <div>
                                    <div class="esim-skel-title skeleton"></div>
                                    <div class="esim-sub"><span class="skeleton" style="display:inline-block;width:160px;height:12px;border-radius:8px"></span></div>
                                </div>
                                <div class="esim-skel-pill skeleton"></div>
                            </div>
                            <div class="esim-meta">
                                <div class="kv esim-skel-kv">
                                    <div class="k skeleton"></div>
                                    <div class="v skeleton"></div>
                                </div>
                                <div class="kv esim-skel-kv">
                                    <div class="k skeleton"></div>
                                    <div class="v skeleton"></div>
                                </div>
                            </div>
                            <div class="esim-skel-actions">
                                <div class="esim-skel-action skeleton"></div>
                                <div class="esim-skel-action skeleton"></div>
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
                        if (nextPage === 1) {
                            myEsimsGrid.innerHTML = '';
                        }
                        myEsimsGrid.querySelectorAll('[data-esim-skeleton="loadmore"]').forEach((el) => el.remove());
                        items.forEach((it) => myEsimsGrid.appendChild(createEsimCard(it)));
                        esimsState.page = nextPage;
                        esimsState.hasMore = !!json.has_more;
                        noEsims.classList.toggle('hidden', (items.length > 0) || nextPage > 1);
                    } catch (e) {
                        myEsimsGrid.innerHTML = '<div class="no-results"><p>Failed to load eSIMs.</p></div>';
                        esimsState.hasMore = false;
                    } finally {
                        esimsState.loading = false;
                        myEsimsGrid.querySelectorAll('[data-esim-skeleton="loadmore"]').forEach((el) => el.remove());
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
                    if (tab === 'virtual') {
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
                    assetToggles.forEach(btn => btn.classList.toggle('active', btn.getAttribute('data-asset-toggle') === mode));
                    assetSections.forEach(sec => sec.classList.toggle('hidden', sec.getAttribute('data-asset-section') !== mode));
                    activeTab = mode === 'virtual' ? 'virtual' : mode;

                    if (activeTab === 'virtual') {
                        noResultsSearch.classList.add('hidden');
                        loadMoreWrap.classList.remove('show');
                        vnShowView(vnState.view);
                        if (vnState.countries.page === 0 && vnEls.countriesGrid.children.length === 0) {
                            vnLoadCountries({ reset: true });
                        }
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
                    const res = await fetch('/api/my-esims/sync', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({})
                    });
                    if (!res.ok) {
                        return;
                    }
                    await res.json().catch(() => ({}));
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
                        setEsimsSkeleton();
                        updateEsimsLoadMoreUi();
                        syncMyEsims().finally(() => fetchMyEsims({ reset: false }));
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
                        if (activeTab === 'virtual') {
                            const q = (searchInput.value || '').trim();
                            if (vnState.view === 'numbers') {
                                vnEls.numbersSearch.value = q;
                                vnLoadNumbers({ reset: true });
                            } else if (vnState.view === 'countries') {
                                vnEls.countriesSearch.value = q;
                                vnLoadCountries({ reset: true });
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
                    refreshWalletTx();
                });
                refreshWallet();
                refreshWalletTx();

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
