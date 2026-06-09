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

        .asset-toggles{display:flex;gap:10px;margin-bottom:0;background:rgba(255,255,255,.5);padding:6px;border-radius:9999px;border:1px solid rgba(20,84,84,.1);width:fit-content;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
        .asset-toggles::-webkit-scrollbar{display:none}
        .asset-toggles button{padding:12px 18px;border-radius:9999px;border:none;background:transparent;font-size:14px;font-weight:700;color:rgba(15,31,31,.6);cursor:pointer;transition:all .2s;white-space:nowrap;flex:0 0 auto}
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
            .asset-toggles{width:100%;border-radius:24px}
            .asset-toggles button{padding:10px 12px;font-size:13px}
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
        .mini-btn{padding:8px 14px;border-radius:9999px;background:rgba(255,255,255,.85);border:1px solid rgba(20,84,84,.14);font-size:13px;font-weight:700;color:rgba(20,84,84,.92);cursor:pointer;display:inline-flex;align-items:center;gap:8px}
        .mini-btn.is-loading{pointer-events:none;opacity:.75}
        .btn-spinner{width:14px;height:14px;border-radius:9999px;border:2px solid rgba(20,84,84,.25);border-top-color:rgba(20,84,84,.92);animation:btn-spin .8s linear infinite}
        @keyframes btn-spin{to{transform:rotate(360deg)}}
        
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
        .vn-actions{display:flex;gap:10px;align-items:center;flex-wrap:nowrap;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
        .vn-actions::-webkit-scrollbar{display:none}
        .vn-btn{padding:10px 14px;border-radius:9999px;background:rgba(255,255,255,.85);border:1px solid rgba(20,84,84,.14);font-size:13px;font-weight:900;color:rgba(20,84,84,.92);cursor:pointer}
        .vn-btn.primary{background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;border:none}
        .vn-status{margin-top:10px;font-size:13px;color:rgba(15,31,31,.62);font-weight:700}
        .vn-hidden{display:none!important}
        .sn-search{position:relative;width:100%;max-width:520px}
        .sn-search svg{position:absolute;left:18px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:rgba(20,84,84,.7);pointer-events:none}
        .sn-search input{width:100%;padding:12px 16px 12px 50px;border-radius:9999px;border:1px solid rgba(15,31,31,.1);background:rgba(255,255,255,.72);box-shadow:inset 0 1px 0 rgba(255,255,255,.72),0 8px 20px rgba(15,31,31,.04);outline:none;font-weight:800;color:#0b1a1a}
        .sn-search input::placeholder{color:rgba(15,31,31,.42);opacity:1}
        .sn-search input:focus{border-color:rgba(20,84,84,.3);background:rgba(255,255,255,.9);box-shadow:0 10px 26px rgba(20,84,84,.08)}
        .sn-panel{padding:24px;display:flex;flex-direction:column;gap:20px}
        .sn-topbar{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:18px;align-items:start}
        .sn-heading{display:flex;flex-direction:column;gap:14px;min-width:0}
        .sn-title-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .sn-mode-switch{display:inline-flex;gap:6px;padding:6px;border-radius:9999px;border:1px solid rgba(20,84,84,.12);background:rgba(255,255,255,.64);box-shadow:inset 0 1px 0 rgba(255,255,255,.7);width:max-content;max-width:100%;overflow-x:auto}
        .sn-mode-switch .vn-btn{border:none;background:transparent;box-shadow:none}
        .sn-mode-switch .vn-btn.primary{background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;box-shadow:0 10px 24px rgba(20,84,84,.14)}
        .sn-toolbox{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}
        .sn-toolbox .vn-btn{min-height:44px}
        .sn-status-line{min-height:20px;margin-top:-6px;padding:0 2px}
        .sn-rental-detail{grid-column:1/-1;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:18px;align-items:stretch}
        .sn-rental-main,.sn-rental-side{border:1px solid rgba(20,84,84,.12);background:rgba(255,255,255,.62);border-radius:22px;padding:22px;box-shadow:0 14px 34px rgba(15,31,31,.05)}
        .sn-rental-main{display:flex;flex-direction:column;gap:14px}
        .sn-rental-side{display:flex;flex-direction:column;gap:14px;justify-content:space-between}
        .sn-kicker{font-size:12px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgba(20,84,84,.66)}
        .sn-rental-title{font-size:28px;line-height:1.08;font-weight:1000;color:#0b1a1a}
        .sn-rental-copy{max-width:640px;font-size:15px;line-height:1.55;color:rgba(15,31,31,.64);font-weight:700}
        .sn-metric-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
        .sn-metric{padding:12px 14px;border-radius:16px;background:rgba(20,84,84,.07);border:1px solid rgba(20,84,84,.10);min-width:150px}
        .sn-metric .k{font-size:11px;font-weight:950;letter-spacing:.07em;text-transform:uppercase;color:rgba(15,31,31,.46)}
        .sn-metric .v{margin-top:6px;font-weight:1000;color:#0b1a1a}
        .sn-field-label{font-size:12px;font-weight:950;letter-spacing:.07em;text-transform:uppercase;color:rgba(15,31,31,.48)}
        .sn-provider-select{width:100%;padding:14px 16px;border-radius:18px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.88);outline:none;font-size:15px;font-weight:900;color:rgba(15,31,31,.8)}
        .sn-rental-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
        .sn-rental-actions .vn-btn{min-height:46px;padding-left:18px;padding-right:18px}
        @media(max-width:640px){
            .sn-panel{padding:18px;gap:16px}
            .sn-topbar{grid-template-columns:1fr}
            .sn-mode-switch{width:100%}
            .sn-mode-switch .vn-btn{flex:1 0 auto}
            .sn-toolbox{justify-content:flex-start;flex-wrap:nowrap;overflow-x:auto;padding-bottom:2px}
            .sn-search{max-width:none}
            .sn-rental-detail{grid-template-columns:1fr}
            .sn-rental-main,.sn-rental-side{padding:18px}
            .sn-rental-title{font-size:22px}
            .sn-rental-actions{justify-content:flex-start}
        }
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
        .sn-otp-panel{grid-column:1/-1;border:1px solid rgba(20,84,84,.12);background:linear-gradient(180deg,rgba(255,255,255,.76),rgba(255,255,255,.58));border-radius:24px;padding:22px;box-shadow:0 16px 38px rgba(15,31,31,.06);display:grid;gap:18px}
        .sn-otp-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
        .sn-otp-title{font-weight:1000;font-size:20px;color:#0b1a1a}
        .sn-otp-sub{margin-top:5px;color:rgba(15,31,31,.58);font-size:13px;font-weight:800}
        .sn-countdown{min-width:132px;padding:12px 14px;border-radius:18px;background:rgba(20,84,84,.08);border:1px solid rgba(20,84,84,.12);text-align:right}
        .sn-countdown .k{font-size:11px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgba(15,31,31,.45)}
        .sn-countdown .v{margin-top:4px;font-size:24px;font-weight:1000;color:#145454;font-variant-numeric:tabular-nums}
        .sn-otp-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,.72fr);gap:14px}
        .sn-otp-box{padding:16px;border-radius:20px;border:1px solid rgba(15,31,31,.09);background:rgba(255,255,255,.68);min-width:0}
        .sn-otp-label{font-size:11px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgba(15,31,31,.46)}
        .sn-phone-value{margin-top:8px;font-size:28px;line-height:1.1;font-weight:1000;color:#0b1a1a;word-break:break-word}
        .sn-code-slot{margin-top:10px;min-height:76px;border-radius:18px;border:1px dashed rgba(20,84,84,.24);background:rgba(20,84,84,.04);display:flex;align-items:center;justify-content:center;text-align:center;padding:14px;color:rgba(15,31,31,.58);font-weight:900}
        .sn-code-slot.has-code{border-style:solid;border-color:rgba(242,116,87,.28);background:linear-gradient(90deg,rgba(242,116,87,.13),rgba(20,84,84,.08));color:#0b1a1a}
        .sn-code-value{font-size:34px;font-weight:1000;letter-spacing:.08em;color:#145454}
        .sn-otp-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
        @media(max-width:640px){
            .sn-otp-panel{padding:18px}
            .sn-otp-head{flex-direction:column}
            .sn-countdown{text-align:left;width:100%}
            .sn-otp-grid{grid-template-columns:1fr}
            .sn-phone-value{font-size:23px}
            .sn-otp-actions{justify-content:flex-start}
        }
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

        .no-results{grid-column: 1/-1; text-align: center; padding: 60px; color: rgba(15,31,31,.5);}
        .no-results svg{margin-bottom: 12px; opacity: .5;}
        .load-more-wrap{margin-top:18px;display:none;justify-content:center}
        .load-more-wrap.show{display:flex}
        .load-more-btn{padding:12px 16px;border-radius:14px;background:rgba(255,255,255,.8);border:1px solid rgba(20,84,84,.14);font-weight:800;color:rgba(20,84,84,.92);cursor:pointer}
        .tiny-btn{padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.75);border:1px solid rgba(15,31,31,.10);font-weight:850;color:rgba(15,31,31,.78);cursor:pointer}

        /* Skeleton Styles */
        .skeleton {
            background: linear-gradient(90deg, #f3f4f6 0%, #e5e7eb 35%, #f3f4f6 70%, #e5e7eb 100%);
            background-size: 300% 100%;
            animation: skeleton-loading 0.95s ease-in-out infinite;
            border-radius: 4px;
        }

        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .dark .skeleton{
            background: linear-gradient(90deg, rgba(31,41,55,.55) 0%, rgba(55,65,81,.95) 35%, rgba(242,116,87,.26) 50%, rgba(55,65,81,.95) 65%, rgba(31,41,55,.55) 100%);
            background-size: 400% 100%;
            animation: skeleton-loading 0.85s ease-in-out infinite;
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
                        <p class="section-sub">Browse all available eSIMs, Regional Plans, and Social Virtual Numbers.</p>
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
                </div>

                <div class="controls-row">
                    <div class="search-bar">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="2.5"/>
                            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                        <input type="text" id="assetSearch" placeholder="Search countries, regions, or numbers...">
                    </div>

                    <div class="asset-toggles">
                        <button class="active" data-asset-toggle="countries">Countries eSIMs</button>
                        <button data-asset-toggle="regions">Regional Plans</button>
                        <button data-asset-toggle="social">Social Virtual Numbers</button>
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

                <!-- Social Numbers Section -->
                <div class="hidden" data-asset-section="social" id="socialNumbersGrid">
                    <div class="vn-panel sn-panel">
                        <div class="sn-topbar">
                            <div class="sn-heading">
                                <div class="sn-title-row">
                                    <div class="vn-title">Social Media Numbers</div>
                                    <div class="sn-mode-switch">
                                        <button type="button" class="vn-btn" id="snOtpModeBtn">One-time OTP</button>
                                        <button type="button" class="vn-btn" id="snRentModeBtn">Monthly Rentals</button>
                                    </div>
                                </div>
                                <div class="sn-search">
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z" stroke="currentColor" stroke-width="2.5"/>
                                        <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    </svg>
                                    <input id="snInlineSearch" type="text" placeholder="Search social numbers...">
                                </div>
                            </div>
                            <div class="sn-toolbox">
                                <button type="button" class="vn-btn" id="snRentalsBtn">My Monthly Numbers</button>
                                <button type="button" class="vn-btn" id="snOrdersBtn">My Social Numbers</button>
                                <button type="button" class="vn-btn primary" id="snRefreshBtn">Refresh</button>
                            </div>
                        </div>
                        <div id="snStatus" class="vn-status sn-status-line"></div>
                        <div class="grid" id="snGrid">
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

                    @php
                        $initialEsimItems = is_array($initialMyEsims ?? null) ? ($initialMyEsims['items'] ?? []) : [];
                    @endphp
                    <div class="grid" id="myEsimsGrid">
                        @foreach($initialEsimItems as $item)
                            @php
                                $esim = $item['esim'] ?? [];
                                $bundle = $item['bundle'] ?? [];
                                $status = ($item['status'] ?? 'valid') === 'expired' ? 'expired' : 'valid';
                                $title = trim((string) ($bundle['name'] ?? '')) ?: 'eSIM';
                                $sub = trim(implode(' • ', array_filter([(string) ($bundle['data'] ?? ''), (string) ($bundle['validity'] ?? '')])));
                                $qrDataUrl = trim((string) ($esim['qr_code_data_url'] ?? ''));
                                $qrUrl = trim((string) ($esim['qr_code_url'] ?? ''));
                                $iosUrl = trim((string) ($esim['direct_installation_link_ios'] ?? ''));
                                $androidUrl = trim((string) ($esim['direct_installation_link_android'] ?? ''));
                                $canRenew = (bool) ($esim['can_renew'] ?? false);
                                $topupUrl = $canRenew && trim((string) ($item['asset_type'] ?? '')) !== '' && trim((string) ($item['asset_id'] ?? '')) !== '' && trim((string) ($esim['esim_id'] ?? '')) !== ''
                                    ? url('/assets/'.rawurlencode((string) $item['asset_type']).'/'.rawurlencode((string) $item['asset_id']).'?topup_esim_id='.rawurlencode((string) $esim['esim_id']))
                                    : '';
                            @endphp
                            <div class="esim-card">
                                <div class="esim-top">
                                    <div>
                                        <div class="esim-title">{{ $title }}</div>
                                        <div class="esim-sub">{{ $sub }}</div>
                                    </div>
                                    <span class="pill {{ $status === 'expired' ? 'expired' : '' }}">{{ $status === 'expired' ? 'Expired' : 'Valid' }}</span>
                                </div>
                                <div class="esim-meta">
                                    <div class="kv"><div class="k">eSIM ID</div><div class="v">{{ (string) ($esim['esim_id'] ?? '-') ?: '-' }}</div></div>
                                    <div class="kv"><div class="k">ICCID</div><div class="v">{{ (string) ($esim['iccid'] ?? '-') ?: '-' }}</div></div>
                                    <div class="kv"><div class="k">Activation Code</div><div class="v">{{ (string) ($esim['activation_code'] ?? '-') ?: '-' }}</div></div>
                                    <div class="kv"><div class="k">Expires</div><div class="v">{{ ! empty($item['expires_at']) ? \Illuminate\Support\Carbon::parse($item['expires_at'])->toDayDateTimeString() : '-' }}</div></div>
                                    <div class="kv"><div class="k">SM-DP+</div><div class="v">{{ (string) ($esim['smdp_address'] ?? '-') ?: '-' }}</div></div>
                                    <div class="kv"><div class="k">LPA</div><div class="v">{{ (string) ($esim['lpa'] ?? ($esim['qr_payload'] ?? '-')) ?: '-' }}</div></div>
                                    <div class="kv"><div class="k">Renewable</div><div class="v">{{ $canRenew ? 'Yes' : 'No' }}</div></div>
                                </div>
                                @if($qrDataUrl !== '')
                                    <div class="qr-box"><img alt="eSIM QR code" src="{{ $qrDataUrl }}"></div>
                                @endif
                                <div class="esim-actions">
                                    @if($qrUrl !== '')
                                        <a class="mini-link" href="{{ $qrUrl }}" target="_blank" rel="noopener noreferrer">Open QR</a>
                                    @elseif($qrDataUrl !== '')
                                        <span class="mini-link secondary">QR Ready</span>
                                    @else
                                        <span class="mini-link secondary">No QR</span>
                                    @endif
                                    @if($iosUrl !== '')
                                        <a class="mini-link" href="{{ $iosUrl }}" target="_blank" rel="noopener noreferrer">Install iOS</a>
                                    @endif
                                    @if($androidUrl !== '')
                                        <a class="mini-link" href="{{ $androidUrl }}" target="_blank" rel="noopener noreferrer">Install Android</a>
                                    @endif
                                    @if($topupUrl !== '')
                                        <a class="mini-link" href="{{ $topupUrl }}">Top up</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div id="noEsims" class="no-results {{ count($initialEsimItems) > 0 ? 'hidden' : '' }}">
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
                    countries: '/api/social-numbers/countries',
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
                    countries: [],
                    countriesPage: 0,
                    selectedApp: null,
                    selectedCountry: '',
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
                const initialMyEsims = @json($initialMyEsims);
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
                        const imgSrc = safeImgSrc(item.flag_url || '');
                        card.innerHTML = `
                            <div class="vnum-top">
                                <div class="vnum-info">
                                    <div class="flag">
                                        ${imgSrc ? `<img src="${imgSrc}" alt="${esc(rawName)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                                    </div>
                                    <div class="vnum-name">${esc(truncatedName)}</div>
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
                                    ${imgSrc ? `<img src="${imgSrc}" alt="${esc(rawName)}">` : `<span>${esc(item.flag || '🌐')}</span>`}
                                </div>
                                <div class="meta">
                                    <div class="name">${esc(truncatedName)}</div>
                                </div>
                            </div>
                            <div class="card-right">
                                <a href="${url}" class="mini-btn js-view-plans">View Plans</a>
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
                    };
                    const icon = map[k] || 'mdi:message-text-outline';
                    return `https://api.iconify.design/${icon}.svg?color=%23ffffff`;
                };
                const socialBrandBg = (key) => {
                    const k = String(key || '').toLowerCase();
                    if (k === 'whatsapp') return '#25D366';
                    if (k === 'telegram') return '#229ED9';
                    if (k === 'instagram') return '#E4405F';
                    if (k === 'facebook') return '#1877F2';
                    if (k === 'tiktok') return '#111827';
                    if (k === 'twitter') return '#111827';
                    return '#111827';
                };
                const renderSocialNumbersPlaceholder = () => {
                    if (!socialEls.grid || !socialEls.status) return;
                    const q = socialQuery();
                    socialEls.status.textContent = 'Loading social numbers…';
                    socialEls.grid.innerHTML = '';

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

                    if (filtered.length === 0) {
                        socialEls.grid.innerHTML = `<div class="no-results" style="grid-column: 1 / -1; padding: 44px 12px"><p>No matches found.</p></div>`;
                        return;
                    }

                    socialEls.status.textContent = '';

                    filtered.forEach((app) => {
                        const card = document.createElement('div');
                        const available = app.available === true;
                        const qty = Number(app.qty || 0);
                        const price = app.price !== null && app.price !== undefined ? String(app.price) : '';
                        const iconSrc = safeImgSrc(socialIconUrl(app.key || ''));
                        const bg = socialBrandBg(app.key || '');
                        card.className = 'card';
                        card.setAttribute('data-search-name', String(app.name || '').toLowerCase());
                        card.innerHTML = `
                            <div class="card-left">
                                <div class="flag" style="background:${esc(bg)};border-color:rgba(255,255,255,.16)">
                                    ${iconSrc ? `<img src="${iconSrc}" alt="${esc(String(app.name || ''))}">` : `<span>💬</span>`}
                                </div>
                                <div class="meta">
                                    <div class="name">${esc(String(app.name || 'App'))}</div>
                                    <div class="subtext">${esc(String(app.description || ''))}</div>
                                    <div class="subtext">${available ? `${qty} available${price ? ` • from ${price}` : ''}` : `Unavailable`}</div>
                                </div>
                            </div>
                            <div class="card-right">
                                <button class="mini-btn" type="button" data-sn-app="${esc(String(app.key || ''))}" ${available ? '' : 'disabled'} style="${available ? '' : 'opacity:.6;cursor:not-allowed'}">Select</button>
                            </div>
                        `;
                        socialEls.grid.appendChild(card);
                        const btn = card.querySelector('[data-sn-app]');
                        if (btn) {
                            btn.addEventListener('click', () => socialShowCountries(String(app.key || '')).catch(() => {}));
                        }
                    });
                };

                const socialSetStatus = (t) => {
                    if (!socialEls.status) return;
                    socialEls.status.textContent = t || '';
                };

                const socialSetMode = (mode) => {
                    socialState.mode = mode === 'rent' ? 'rent' : 'otp';
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
                            ${text ? `<div class="sn-otp-box"><div class="sn-otp-label">SMS message</div><div style="margin-top:8px;color:rgba(15,31,31,.78);font-weight:800;line-height:1.5">${esc(text)}</div></div>` : ''}
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
                    socialState.selectedApp = null;
                    socialState.selectedCountry = '';
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
                    const app = socialState.apps.find((a) => String(a.key || '') === String(appKey || ''));
                    if (!app) return;
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
                        socialRenderCountries();
                    });
                    document.getElementById('snGenerateBtn')?.addEventListener('click', () => {
                        if (socialState.isBuying) return;
                        socialBuy(countryKey, 'any');
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

                const socialShowCountry = async (countryKey) => {
                    if (!socialState.selectedApp) return;
                    socialStopPolling();
                    socialStopCountdown();
                    socialState.view = 'country';
                    socialState.selectedCountry = String(countryKey || '');
                    socialState.countrySummary = 'Loading…';
                    socialState.order = null;
                    socialRenderCountry();
                    await socialLoadCountrySummary();
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
                    const poll = async () => {
                        if (socialState.pollingOrderId !== id) return;
                        const r = await socialFetchJson(socialApi.checkBase + encodeURIComponent(String(id)));
                        if (socialState.pollingOrderId !== id) return;
                        if (!r.ok) return;
                        const order = r.json && r.json.order && typeof r.json.order === 'object' ? r.json.order : null;
                        if (!order) return;
                        socialState.order = order;
                        if (socialState.view === 'country') {
                            socialRenderCountry();
                        }
                        const status = String(order.status || '');
                        if (['RECEIVED', 'FINISHED', 'CANCELED', 'BANNED', 'TIMEOUT'].includes(status)) {
                            shouldContinue = false;
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
                                <div class="flag" style="background:${esc(bg)};border-color:rgba(255,255,255,.16)">
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
                                <div class="flag" style="background:${esc(bg)};border-color:rgba(255,255,255,.16)">
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
                            <div class="flag" style="background:${esc(socialBrandBg(rental.product || ''))};border-color:rgba(255,255,255,.16)">
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
                    const hasRenderedCards = myEsimsGrid.querySelectorAll('.esim-card:not([data-esim-skeleton])').length > 0;
                    if (reset) {
                        esimsState.page = 0;
                        esimsState.hasMore = true;
                        if (!hasRenderedCards) {
                            setEsimsSkeleton();
                        }
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
                        if (nextPage === 1 && items.length === 0 && hasRenderedCards) {
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
        </script>
    </div>
</x-app-layout>
