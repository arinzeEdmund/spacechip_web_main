<x-app-layout>
    <style>
        :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif}
        *{box-sizing:border-box}
        body{margin:0;color:var(--text);font-family:var(--font-sans);min-height:100vh;position:relative;overflow-x:hidden;background:radial-gradient(900px 520px at 10% -10%, rgb(var(--accent-rgb) / .10) 0%, transparent 60%),
                var(--bg)}
        body::before{content:"";position:fixed;inset:-20%;background:radial-gradient(640px 460px at 90% 6%, rgb(var(--accent-rgb) / .07) 0%, transparent 70%);
            filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}
        a{color:inherit;text-decoration:none}
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
        
        .controls-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px}
        .search-bar{margin-bottom:0;position:relative;max-width:none;flex:1 1 320px;min-width:0}
        .search-bar input{width:100%;height:48px;padding:0 16px 0 46px;border-radius:14px;background:var(--surface);border:1px solid var(--border);box-shadow:none;outline:none;font-size:15px;color:var(--text);transition:border-color .15s,box-shadow .15s}
        .search-bar input::placeholder{color:var(--faint)}
        .search-bar input:hover{border-color:var(--border-strong)}
        .search-bar input:focus{border-color:var(--accent-strong);box-shadow:0 0 0 3px var(--accent-soft)}
        .search-bar svg{position:absolute;z-index:1;left:15px;top:50%;transform:translateY(-50%);height:19px;width:19px;color:var(--faint);pointer-events:none}

        .asset-toggles{display:flex;gap:2px;margin-bottom:0;background:var(--surface-2);padding:3px;border-radius:9999px;border:1px solid var(--border);width:fit-content;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
        .asset-toggles::-webkit-scrollbar{display:none}
        .asset-toggles button{padding:9px 16px;border-radius:9999px;border:none;background:transparent;font-size:13.5px;font-weight:600;color:var(--muted);cursor:pointer;transition:color .15s,background-color .15s,box-shadow .15s;white-space:nowrap;flex:0 0 auto}
        .asset-toggles button:hover{color:var(--text)}
        .asset-toggles button:focus-visible{outline:2px solid var(--accent-strong);outline-offset:-2px}
        .asset-toggles button:focus:not(:focus-visible){outline:none}
        .asset-toggles button.active{background:var(--surface);color:var(--text);box-shadow:var(--shadow-1),0 0 0 1px var(--border)}
        .nav-row{display:none}
        .esim-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
        .esim-owner{font-size:13px;color:var(--muted)}
        #myEsimsGrid{align-items:start}
        @media(max-width:640px){#myEsimsGrid{grid-template-columns:1fr;gap:12px}.esim-bar{flex-direction:column;align-items:flex-start}}
        .esim-card{display:flex;flex-direction:column;gap:14px;padding:16px;border-radius:18px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-1);transition:border-color .18s,box-shadow .18s}
        .esim-card:hover{border-color:var(--border-strong);box-shadow:var(--shadow-2)}
        .esim-card[data-status="expired"] .esim-chip{color:var(--muted);background:var(--surface-2)}
        .esim-head{display:flex;align-items:flex-start;gap:12px}
        .esim-chip{display:grid;flex:0 0 auto;place-items:center;width:40px;height:40px;border-radius:12px;color:var(--accent-text);background:var(--accent-soft)}
        .esim-chip svg{width:20px;height:20px}
        .esim-titles{flex:1;min-width:0}
        .esim-title{margin:0;font-size:15px;font-weight:650;line-height:1.3;color:var(--text);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .esim-sub{margin:3px 0 0;font-size:13px;color:var(--muted)}
        .esim-facts{display:grid;grid-template-columns:1fr 1fr;margin:0;border:1px solid var(--border);border-radius:12px;background:var(--surface-2)}
        .esim-facts > div{min-width:0;padding:10px 12px}
        .esim-facts > div + div{border-left:1px solid var(--border)}
        .esim-facts dt{font-size:11.5px;color:var(--muted)}
        .esim-facts dd{display:flex;align-items:center;gap:4px;min-height:28px;margin:2px 0 0;font-size:13.5px;font-weight:600;color:var(--text)}
        .esim-facts .ui-copy{width:24px;height:24px;margin-left:auto}
        .esim-more{margin:0 -16px -16px;padding:0 16px}
        .esim-more > summary{font-size:13.5px;color:var(--muted);padding:12px 0}
        .esim-more > summary:hover{color:var(--text)}
        .esim-more .ui-kv dd span{max-width:190px}
        .esim-qr{display:flex;justify-content:center;margin-bottom:10px;padding:12px;border-radius:14px;background:#fff;border:1px solid var(--border)}
        .esim-qr img{width:180px;max-width:100%;height:auto;filter:none}
        .esim-actions{display:flex;flex-wrap:wrap;gap:8px}
        
        .sub-toggles{display:flex;gap:8px;margin-bottom:32px;flex-wrap:wrap}
        .sub-toggles button{padding:10px 18px;border-radius:9999px;background:rgb(var(--surface-rgb) / .6);border:1px solid rgb(var(--ink-rgb) / .12);font-size:13px;font-weight:650;color:rgb(var(--ink-rgb) / .62);cursor:pointer;transition:all .2s}
        .sub-toggles button.active{background:var(--accent-soft);border-color:rgb(var(--accent-rgb) / .32);color:rgb(var(--accent-text-rgb) / .92)}

        .grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
        .grid > *{min-width:0}
        @media(min-width:640px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(min-width:1024px){.grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        .card-mob-footer{display:none}
        .card-avail{display:flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700;color:rgb(var(--ink-rgb) / .48)}
        .card-chev{color:rgb(var(--ink-rgb) / .28);flex-shrink:0}

        @media(max-width:640px){
            .py-12{padding-top:8px!important}
            .container{padding:0 16px}
            .section{padding:16px 0}
            .section-title{font-size:20px}
            .controls-row{margin-bottom:18px}
            .search-bar{min-width:0}
            .search-bar input{padding:14px 16px 14px 46px;border-radius:18px;font-size:15px}
            .search-bar svg{left:16px;height:20px;width:20px}
            .asset-toggles{width:100%;border-radius:24px}
            .asset-toggles button{padding:10px 12px;font-size:13px}
            .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
            .card{flex-direction:column;align-items:flex-start;position:relative;padding:14px;gap:0}
            .card-left{width:100%;flex-direction:column;align-items:flex-start;gap:0}
            .flag{width:48px;height:48px;border-radius:16px;margin-bottom:10px}
            .card .name{font-size:13.5px;white-space:normal;text-align:left;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
            .card .meta{width:100%}
            .card-right{position:absolute;inset:0;opacity:0;z-index:1;width:auto;display:flex}
            .card-right .mini-btn{flex:1;border-radius:inherit}
            .card-mob-footer{display:flex;justify-content:space-between;align-items:center;width:100%;margin-top:10px;position:relative;z-index:0}
            .no-results{padding:42px 12px}

            /* Wallet card — exact app match (mobile only) */
            .wallet-panel{
                padding:20px 18px 18px;
                border-radius:24px;
                background:
                    radial-gradient(ellipse 220px 160px at -5% -10%, rgb(var(--accent-rgb) / .14) 0%, transparent 65%),
                    var(--surface);
                border:1px solid transparent;
                box-shadow:0 8px 32px rgba(0,0,0,.08),0 2px 8px rgba(0,0,0,.05)
            }
            .wallet-inner{display:block}
            .wallet-top{display:flex;flex-direction:row;align-items:center;gap:8px;margin-bottom:0}
            .wallet-hdr-icon{
                display:flex;align-items:center;justify-content:center;
                width:36px;height:36px;flex-shrink:0;
                background:rgb(var(--surface-rgb) / .9);
                border:1px solid rgb(var(--ink-rgb) / .13);
                border-radius:12px;
                color:rgb(var(--accent-text-rgb) / .75);
                box-shadow:0 2px 8px rgba(0,0,0,.07)
            }
            .wallet-info-block{display:flex;flex-direction:column}
            .wallet-title{display:block;font-size:15px;font-weight:800;color:var(--text);letter-spacing:-.01em}
            .wallet-desc{display:block;font-size:13px;font-weight:600;color:rgb(var(--ink-rgb) / .55);margin-top:3px}
            .wallet-label-sm{display:block;font-size:12px;font-weight:600;color:rgb(var(--ink-rgb) / .52);margin-top:16px;margin-bottom:2px}
            .wallet-balance{font-size:36px;font-weight:900;letter-spacing:-.03em;background:none;border:none;padding:4px 0 4px;color:var(--text);text-shadow:none}
            .wallet-status{color:rgb(var(--ink-rgb) / .6)!important}
            .wallet-actions{display:flex;align-items:center;margin-top:14px;flex-wrap:nowrap;gap:10px}
            .wallet-input{
                flex:1;min-width:0;
                padding:15px 16px;
                background:var(--surface);
                border:1px solid rgb(var(--ink-rgb) / .09);
                border-radius:16px;
                color:var(--text);
                font-size:15px;
                font-weight:700;
                box-shadow:0 2px 8px rgba(0,0,0,.05)
            }
            .wallet-input::placeholder{color:rgb(var(--ink-rgb) / .35)}
            .wallet-ref-btn{display:none}
            .wallet-dep-btn{
                padding:15px 20px!important;
                border-radius:16px!important;
                white-space:nowrap;
                background:var(--accent-fill)!important;
                border:none!important;
                color:#fff!important;
                box-shadow:0 6px 20px rgba(0,0,0,.22),0 2px 6px rgba(0,0,0,.1)!important;
                font-size:14px!important;
                font-weight:800!important
            }
            .wallet-dep-btn:active{transform:translateY(1px);box-shadow:0 3px 10px rgba(0,0,0,.18)!important}
            .dep-icon{display:inline-block}
        }

        @media(max-width:480px){
                                }
        
        .card{position:relative;padding:14px 14px 14px 16px;border-radius:18px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-1);display:flex;justify-content:space-between;gap:14px;align-items:center;transition:border-color .18s,box-shadow .18s,transform .18s}
        .card.hidden-by-search{display:none!important}
        .card-left{display:flex;gap:14px;align-items:center;min-width:0}
        .flag{height:44px;width:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:var(--surface-2);border:1px solid var(--border);overflow:hidden;flex:0 0 auto;font-size:26px}
        .flag img{height:100%;width:100%;object-fit:cover;display:block}
        .meta{min-width:0}
        .name{font-weight:600;font-size:15px;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)}
        .subtext{margin-top:2px;font-size:12.5px;color:var(--muted)}
        .card-right{text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
        .price{font-weight:800;font-size:15px;color:rgb(var(--accent-text-rgb) / .92)}
        .price span{font-weight:650;color:rgb(var(--ink-rgb) / .64);font-size:12px}
        .mini-btn{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 14px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);font-size:13px;font-weight:600;color:var(--text);cursor:pointer;white-space:nowrap;transition:background-color .15s,color .15s,border-color .15s}
        /* A card with a single action is one big click target: the action's ::after covers the card. */
        .card:has(.card-right > .mini-btn:only-child){cursor:pointer}
        .card-right > .mini-btn:only-child::after{content:"";position:absolute;inset:0;border-radius:inherit}
        .card:has(.card-right > .mini-btn:only-child):hover{border-color:rgb(var(--accent-rgb) / .45);box-shadow:var(--shadow-3);transform:translateY(-2px)}
        .card:has(.card-right > .mini-btn:only-child:not(:disabled)):hover .mini-btn{color:var(--on-accent);background:var(--accent-fill);border-color:var(--accent-fill)}
        .card:has(.card-right > .mini-btn:only-child:disabled){cursor:default}
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

        .vn-panel{padding:18px;border-radius:20px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-1)}
        .vn-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:12px}
        .vn-title{font-weight:700;letter-spacing:-.02em;color:var(--text);font-size:19px}
        .vn-sub{color:rgb(var(--ink-rgb) / .62);font-weight:650;font-size:13px;margin-top:6px}
        .vn-controls{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-top:12px}
        .vn-controls input{padding:12px 14px;border-radius:16px;border:1px solid rgb(var(--ink-rgb) / .12);background:rgb(var(--surface-rgb) / .78);outline:none;min-width:280px}
        .vn-actions{display:flex;gap:10px;align-items:center;flex-wrap:nowrap;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
        .vn-actions::-webkit-scrollbar{display:none}
        .vn-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:38px;padding:0 14px;border-radius:10px;background:var(--surface-2);border:1px solid var(--border);font-size:13.5px;font-weight:600;color:var(--text);cursor:pointer;white-space:nowrap;transition:background-color .15s,border-color .15s,color .15s}
        .vn-btn:hover{background:var(--surface-3);border-color:var(--border-strong)}
        .vn-btn.primary{background:var(--accent-fill);color:var(--on-accent);border-color:var(--accent-fill);box-shadow:0 0 0 1px var(--accent-fill),0 6px 18px -8px rgb(var(--accent-rgb))}
        .vn-btn.primary:hover{background:var(--accent-fill-hover)}
        .vn-status{margin-top:10px;font-size:13px;color:var(--muted);font-weight:500}
        .vn-hidden{display:none!important}
        .sn-search{position:relative;width:100%;max-width:520px}
        .sn-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--faint);pointer-events:none}
        .sn-search input{width:100%;height:44px;padding:0 16px 0 42px;border-radius:12px;border:1px solid var(--border);background:var(--surface);box-shadow:none;outline:none;font-size:14.5px;font-weight:400;color:var(--text);transition:border-color .15s,box-shadow .15s}
        .sn-search input::placeholder{color:var(--faint);opacity:1;font-weight:400}
        .sn-search input:focus{border-color:var(--accent-strong);box-shadow:0 0 0 3px var(--accent-soft)}
        .sn-panel{padding:22px;display:flex;flex-direction:column;gap:18px}
        .sn-topbar{display:grid;grid-template-columns:minmax(260px,1fr) auto;gap:18px;align-items:start}
        .sn-heading{display:flex;flex-direction:column;gap:14px;min-width:0}
        .sn-title-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .sn-mode-switch{display:inline-flex;gap:2px;padding:3px;border-radius:10px;border:1px solid var(--border);background:var(--surface-2);width:max-content;max-width:100%;overflow-x:auto}
        .sn-mode-switch .vn-btn{height:32px;border:none;border-radius:8px;background:transparent;color:var(--muted);box-shadow:none}
        .sn-mode-switch .vn-btn:hover{color:var(--text);background:transparent}
        .sn-mode-switch .vn-btn.primary{background:var(--surface);color:var(--text);box-shadow:var(--shadow-1),0 0 0 1px var(--border)}
        .sn-toolbox{display:flex;align-items:center;justify-content:flex-end;gap:10px;flex-wrap:wrap}
        .sn-toolbox .vn-btn{height:40px}
        .sn-toolbox .vn-btn.primary{height:40px}
        .sn-status-line{min-height:20px;margin-top:-6px;padding:0 2px}
        /* Social numbers grid: allow tracks to shrink below content so long
           catalogue names never push cards outside the panel */
        #snGrid{grid-template-columns:repeat(2,minmax(0,1fr))}
        @media(min-width:1024px){#snGrid{grid-template-columns:repeat(3,minmax(0,1fr))}}
        /* "See more services" browse mode: bounded, scrollable inside the panel */
        .sn-browsing #snGrid{max-height:min(62vh,600px);overflow-y:auto;overflow-x:hidden;align-content:start;padding-right:4px;scrollbar-width:thin;scrollbar-color:rgb(var(--accent-strong-rgb) / .3) transparent}
        .sn-browsing #snGrid::-webkit-scrollbar{width:9px}
        .sn-browsing #snGrid::-webkit-scrollbar-thumb{background:rgb(var(--accent-fill-hover-rgb) / .22);border-radius:9px;border:2px solid transparent;background-clip:content-box}
        .sn-browse-bar{grid-column:1/-1;position:sticky;top:0;z-index:3;display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:2px 2px 10px;margin-bottom:2px;background:linear-gradient(rgb(var(--surface-rgb) / .99) 62%,rgb(var(--surface-rgb) / .7))}
        .sn-browse-bar .sn-browse-count{font-size:12px;font-weight:750;color:rgb(var(--ink-rgb) / .55)}
        .sn-browse-more{grid-column:1/-1;display:flex;justify-content:center;padding:10px 0 4px}
        .flag.sn-mono{background:var(--accent-soft);color:var(--accent-text);border-color:transparent;font-size:15px;font-weight:700;letter-spacing:.02em}
        .card.sn-catalogue{align-items:center}
        .sn-tile{position:relative;height:48px;width:48px;border-radius:14px;font-size:15px;font-weight:700;border:0}
        .sn-tile img{position:absolute;inset:11px;width:26px;height:26px;object-fit:contain}
        .sn-tile img + span{visibility:hidden}
        .sn-desc{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4;margin-top:3px}
        .sn-chips{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
        .card.sn-app{align-items:flex-start}
        .card.sn-app .card-right{align-self:center}
        .card.sn-catalogue .name{white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .sn-rental-detail{grid-column:1/-1;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:18px;align-items:stretch}
        .sn-rental-main,.sn-rental-side{border:1px solid rgb(var(--ink-rgb) / .12);background:rgb(var(--surface-rgb) / .62);border-radius:22px;padding:22px;box-shadow:0 14px 34px rgba(0,0,0,.05)}
        .sn-rental-main{display:flex;flex-direction:column;gap:14px}
        .sn-rental-side{display:flex;flex-direction:column;gap:14px;justify-content:space-between}
        .sn-kicker{font-size:12px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgb(var(--accent-text-rgb) / .66)}
        .sn-rental-title{font-size:28px;line-height:1.08;font-weight:1000;color:var(--text)}
        .sn-rental-copy{max-width:640px;font-size:15px;line-height:1.55;color:rgb(var(--ink-rgb) / .64);font-weight:700}
        .sn-metric-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:4px}
        .sn-metric{padding:12px 14px;border-radius:16px;background:rgb(var(--ink-rgb) / .07);border:1px solid rgb(var(--ink-rgb) / .10);min-width:150px}
        .sn-metric .k{font-size:11px;font-weight:950;letter-spacing:.07em;text-transform:uppercase;color:rgb(var(--ink-rgb) / .46)}
        .sn-metric .v{margin-top:6px;font-weight:1000;color:var(--text)}
        .sn-field-label{font-size:12px;font-weight:950;letter-spacing:.07em;text-transform:uppercase;color:rgb(var(--ink-rgb) / .48)}
        .sn-provider-select{width:100%;padding:14px 16px;border-radius:18px;border:1px solid rgb(var(--ink-rgb) / .12);background:rgb(var(--surface-rgb) / .88);outline:none;font-size:15px;font-weight:900;color:rgb(var(--ink-rgb) / .8)}
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
        .vn-number-card{padding:14px;border-radius:20px;border:1px solid rgb(var(--ink-rgb) / .08);background:rgb(var(--surface-rgb) / .6);display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        .vn-number-meta{display:flex;flex-direction:column;gap:6px;min-width:0}
        .vn-number-title{font-weight:950;color:var(--text)}
        .vn-tags{display:flex;gap:6px;flex-wrap:wrap}
        .vn-tag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:9999px;border:1px solid rgb(var(--ink-rgb) / .10);font-weight:900;font-size:11px;color:rgb(var(--ink-rgb) / .72);background:rgb(var(--surface-rgb) / .6)}
        .vn-buy{padding:10px 14px;border-radius:14px;background:var(--accent-fill);color:#fff;font-weight:900;border:none;cursor:pointer;box-shadow:0 8px 20px rgb(var(--accent-rgb) / .12);white-space:nowrap}
        .vn-chat{margin-top:10px;display:flex;flex-direction:column;gap:10px;max-height:520px;overflow:auto;padding:10px;border-radius:20px;border:1px solid rgb(var(--ink-rgb) / .08);background:rgb(var(--surface-rgb) / .55)}
        .vn-msg{display:flex;flex-direction:column;gap:6px;max-width:82%}
        .vn-msg.in{align-self:flex-start}
        .vn-msg.out{align-self:flex-end}
        .vn-bubble{padding:10px 12px;border-radius:16px;border:1px solid rgb(var(--ink-rgb) / .10);background:rgb(var(--surface-rgb) / .86);color:rgb(var(--ink-rgb) / .9);font-weight:750;white-space:pre-wrap;word-break:break-word}
        .vn-msg.out .vn-bubble{background:var(--accent-soft);border-color:rgb(var(--accent-rgb) / .28)}
        .vn-meta{font-size:11px;color:rgb(var(--ink-rgb) / .56);font-weight:700}
        .vn-media{display:flex;gap:8px;flex-wrap:wrap}
        .vn-media img{max-width:160px;border-radius:14px;border:1px solid rgb(var(--ink-rgb) / .10)}
        .vn-compose{margin-top:12px;display:grid;gap:10px}
        .vn-compose input,.vn-compose textarea{width:100%;padding:12px 14px;border-radius:16px;border:1px solid rgb(var(--ink-rgb) / .12);background:rgb(var(--surface-rgb) / .82);outline:none;font-weight:750;color:rgb(var(--ink-rgb) / .78)}
        .vn-compose textarea{min-height:88px;resize:vertical}
        .sn-otp-panel{grid-column:1/-1;border:1px solid rgb(var(--ink-rgb) / .12);background:linear-gradient(180deg,rgb(var(--surface-rgb) / .76),rgb(var(--surface-rgb) / .58));border-radius:24px;padding:22px;box-shadow:0 16px 38px rgba(0,0,0,.06);display:grid;gap:18px}
        .sn-otp-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap}
        .sn-otp-title{font-weight:1000;font-size:20px;color:var(--text)}
        .sn-otp-sub{margin-top:5px;color:rgb(var(--ink-rgb) / .58);font-size:13px;font-weight:800}
        .sn-countdown{min-width:132px;padding:12px 14px;border-radius:18px;background:rgb(var(--ink-rgb) / .08);border:1px solid rgb(var(--ink-rgb) / .12);text-align:right}
        .sn-countdown .k{font-size:11px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgb(var(--ink-rgb) / .45)}
        .sn-countdown .v{margin-top:4px;font-size:24px;font-weight:1000;color:var(--accent-text);font-variant-numeric:tabular-nums}
        .sn-otp-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,.72fr);gap:14px}
        .sn-otp-box{padding:16px;border-radius:20px;border:1px solid rgb(var(--ink-rgb) / .09);background:rgb(var(--surface-rgb) / .68);min-width:0}
        .sn-otp-label{font-size:11px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:rgb(var(--ink-rgb) / .46)}
        .sn-phone-value{margin-top:8px;font-size:28px;line-height:1.1;font-weight:1000;color:var(--text);word-break:break-word}
        .sn-code-slot{margin-top:10px;min-height:76px;border-radius:18px;border:1px dashed rgb(var(--accent-strong-rgb) / .24);background:rgb(var(--ink-rgb) / .04);display:flex;align-items:center;justify-content:center;text-align:center;padding:14px;color:rgb(var(--ink-rgb) / .58);font-weight:900}
        .sn-code-slot.has-code{border-style:solid;border-color:rgb(var(--accent-rgb) / .28);background:var(--accent-soft);color:var(--text)}
        .sn-code-value{font-size:34px;font-weight:1000;letter-spacing:.08em;color:var(--accent-text)}
        .sn-otp-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
        @media(max-width:640px){
            .sn-otp-panel{padding:18px}
            .sn-otp-head{flex-direction:column}
            .sn-countdown{text-align:left;width:100%}
            .sn-otp-grid{grid-template-columns:1fr}
            .sn-phone-value{font-size:23px}
            .sn-otp-actions{justify-content:flex-start}
        }
        .vn-skel-card{padding:14px;border-radius:18px;background:var(--surface);border:1px solid var(--border);display:flex;justify-content:space-between;gap:12px;align-items:center}
        .vn-skel-left{display:flex;align-items:center;gap:12px;min-width:0}
        .vn-skel-flag{width:40px;height:40px;border-radius:14px}
        .vn-skel-lines{display:flex;flex-direction:column;gap:8px;min-width:0}
        .vn-skel-line-lg{width:140px;height:14px;border-radius:8px}
        .vn-skel-line-sm{width:100px;height:12px;border-radius:8px}
        .vn-skel-btn{width:90px;height:34px;border-radius:9999px}
        .wallet-panel{margin-top:14px;margin-bottom:18px;padding:18px;border-radius:26px;position:relative;overflow:hidden;background:
            radial-gradient(900px 220px at 12% 0%, rgb(var(--accent-rgb) / .14), transparent 60%),
            var(--surface);
            border:1px solid transparent;box-shadow:var(--shadow-2)}
        .wallet-panel::before{content:"";position:absolute;inset:0;border-radius:inherit;padding:1px;background:linear-gradient(120deg, rgb(var(--accent-rgb) / .55), var(--border) 45%, rgb(var(--accent-rgb) / .3));-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
        .wallet-info-block{display:flex;flex-direction:column}
        .wallet-sub{color:rgb(var(--ink-rgb) / .62);font-weight:700;font-size:13px;margin-top:6px}
        .wallet-status.error{color:rgb(var(--accent-text-rgb) / .92)}
        .wallet-status.success{color:rgb(var(--accent-text-rgb) / .92)}
        @media(min-width:641px){
        .wallet-inner{display:grid;grid-template-columns:minmax(0,1fr) auto auto;grid-template-areas:"top label actions" "top balance actions";column-gap:28px;align-items:center;position:relative;z-index:1}
        .wallet-top{grid-area:top;min-width:180px;display:flex;align-items:center;gap:14px}
        .wallet-label-sm{grid-area:label;align-self:end;font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:rgb(var(--ink-rgb) / .5)}
        .wallet-hdr-icon{display:flex;align-items:center;justify-content:center;width:46px;height:46px;padding:11px;border-radius:15px;flex-shrink:0;color:#fff;background:var(--accent-fill);box-shadow:inset 0 1px 0 rgba(255,255,255,.3),0 8px 20px rgb(var(--accent-rgb) / .25)}
        .wallet-title{font-weight:700;font-size:17px;color:var(--text);letter-spacing:-.02em}
        .wallet-desc{color:rgb(var(--ink-rgb) / .58);font-weight:500;font-size:13.5px;margin-top:3px}
        .wallet-actions{grid-area:actions;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
        .wallet-balance{grid-area:balance;font-weight:700;color:var(--text);font-size:32px;line-height:1.1;letter-spacing:-.035em;font-variant-numeric:tabular-nums;display:inline-block;transform-origin:left center}
        .wallet-input{padding:12px 16px;border-radius:16px;border:1px solid rgb(var(--ink-rgb) / .1);background:rgb(var(--surface-rgb) / .88);outline:none;min-width:170px;font-weight:600;color:var(--text);transition:border-color .2s,box-shadow .2s}
        .wallet-input:focus{border-color:rgb(var(--accent-rgb) / .55);box-shadow:0 0 0 4px rgb(var(--accent-rgb) / .14)}
        .wallet-panel .tiny-btn{padding:12px 16px;border-radius:16px;border:1px solid rgb(var(--ink-rgb) / .14);background:rgb(var(--surface-rgb) / .8);font-weight:650;color:rgb(var(--accent-text-rgb) / .92)}
        }
        .wallet-panel .tiny-btn:hover{background:rgb(var(--surface-rgb) / .9)}
        .wallet-panel .tiny-btn:active{transform:translateY(1px)}
        .wallet-dep-btn{background:var(--accent-fill)!important;border:none!important;color:#fff!important;box-shadow:0 10px 28px rgba(0,0,0,.14)!important;display:inline-flex!important;align-items:center!important;gap:6px!important}
        .wallet-dep-btn:hover{filter:saturate(1.05) brightness(1.02)!important}
        .dep-icon{display:none}

        .no-results{grid-column: 1/-1; text-align: center; padding: 60px; color: rgb(var(--ink-rgb) / .5);}
        .no-results svg{margin-bottom: 12px; opacity: .5;}
        .load-more-wrap{margin-top:18px;display:none;justify-content:center}
        .load-more-wrap.show{display:flex}
        .load-more-btn{padding:12px 16px;border-radius:14px;background:rgb(var(--surface-rgb) / .8);border:1px solid rgb(var(--ink-rgb) / .14);font-weight:800;color:rgb(var(--accent-text-rgb) / .92);cursor:pointer}
        .tiny-btn{padding:10px 12px;border-radius:14px;background:rgb(var(--surface-rgb) / .75);border:1px solid rgb(var(--ink-rgb) / .10);font-weight:850;color:rgb(var(--ink-rgb) / .78);cursor:pointer}



        .skeleton-card{padding:14px 14px 14px 16px;border-radius:18px;background:var(--surface);border:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:14px}

        .skeleton-flag { width: 44px; height: 44px; border-radius: 12px; }
        @media(max-width:640px){
            .skeleton-card{flex-direction:column;align-items:flex-start;padding:14px;gap:0}
            .skeleton-card .card-left{flex-direction:column;align-items:flex-start;gap:0;width:100%}
            .skeleton-card .card-right{display:none}
            .skeleton-flag{width:48px;height:48px;border-radius:16px;margin-bottom:10px}
            .skeleton-text-lg{width:80px;height:14px}
            .skeleton-text-sm{width:60px;height:10px;margin-top:6px}
        }
        .skeleton-text-lg { width: 120px; height: 18px; margin-bottom: 8px; }
        .skeleton-text-sm { width: 80px; height: 12px; }
        .skeleton-btn { width: 70px; height: 32px; border-radius: 9999px; }

        .hidden{display:none!important}
        
        .py-12{flex:1;display:flex;flex-direction:column}
        .py-12 > main{flex:1}
        footer{margin-top:auto;border-top:1px solid rgb(var(--ink-rgb) / .10);background:rgb(var(--surface-rgb) / .35);backdrop-filter:blur(10px)}
        .footer{padding:40px 0;display:grid;grid-template-columns:1fr;gap:24px}
        .links{color:rgb(var(--ink-rgb) / .64);font-size:14px;line-height:22px;text-decoration:none}
        @media(min-width:860px){.footer{grid-template-columns:repeat(3,1fr)}}
        .footer-wordmark{height:26px;width:auto;display:block}
        </style>

    <div class="py-12">
        <main class="container">
            <section class="section">
                @php
                    $firstName = \Illuminate\Support\Str::of((string) Auth::user()->name)->trim()->explode(' ')->first() ?: 'there';
                @endphp
                <header class="scd-hero">
                    <p class="scd-date" id="scdDate">{{ now()->format('l, j F') }}</p>
                    <h1 class="scd-greeting"><span id="scdGreeting">Welcome back</span>, {{ $firstName }}.</h1>
                    <p class="scd-sub">Data eSIMs for 190+ destinations, private virtual numbers and verification numbers, all paid from one wallet.</p>
                    <div class="scd-quick">
                        <button type="button" data-scd-action="topup">
                            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></i>
                            Top up wallet
                        </button>
                        <button type="button" data-scd-action="my-esims">
                            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/></svg></i>
                            My eSIMs
                        </button>
                        <a href="{{ route('dashboard.virtual.index') }}">
                            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg></i>
                            Virtual numbers
                        </a>
                        <button type="button" data-scd-action="social">
                            <i><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg></i>
                            Verification numbers
                        </button>
                    </div>
                </header>


                <div class="wallet-panel">
                    <div class="wallet-inner">
                        <div class="wallet-top">
                            <svg class="wallet-hdr-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="6" width="20" height="14" rx="3" stroke="currentColor" stroke-width="2"/>
                                <path d="M2 10h20" stroke="currentColor" stroke-width="2"/>
                                <rect x="15" y="13" width="4" height="4" rx="1.5" fill="currentColor"/>
                            </svg>
                            <div class="wallet-info-block">
                                <div class="wallet-title"
                                
                                
                                
                                
                                
                                
                                >Wallet</div>
                                <div class="wallet-desc">Balance in USD. Deposit with card, or pay instantly with wallet.</div>
                            </div>
                        </div>
                        <div class="wallet-label-sm">Available balance</div>
                        <div class="wallet-balance" id="walletBalance" data-sc-countup>$0.00</div>
                        <div class="wallet-actions">
                            <input class="wallet-input" id="walletDepositAmount" type="number" min="1" step="1" placeholder="Deposit (USD)">
                            <button class="tiny-btn wallet-dep-btn" type="button" id="walletDepositBtn">
                                <svg class="dep-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="2" y="6" width="20" height="14" rx="3" stroke="currentColor" stroke-width="2"/>
                                    <path d="M2 10h20" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                Deposit
                            </button>
                            <button class="tiny-btn wallet-ref-btn" type="button" id="walletRefreshBtn">Refresh</button>
                        </div>
                    </div>
                    <div id="walletStatus" class="wallet-sub wallet-status"></div>
                </div>

                <div class="section-head" style="margin-bottom:14px">
                    <div>
                        <h2 class="section-title" style="font-size:20px">Browse</h2>
                        <p class="section-sub">Countries, regional plans and verification numbers.</p>
                    </div>
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
                    <div class="esim-bar">
                        <div class="asset-toggles esim-filter">
                            <button class="active" type="button" data-esim-filter="valid">Active</button>
                            <button type="button" data-esim-filter="expired">Expired</button>
                        </div>
                        <span class="esim-owner">Signed in as {{ auth()->user()->email }}</span>
                    </div>

                    @php
                        $initialEsimItems = is_array($initialMyEsims ?? null) ? ($initialMyEsims['items'] ?? []) : [];
                    @endphp
                    <div class="grid" id="myEsimsGrid">
                        @foreach($initialEsimItems as $item)
                            @include('partials.esim-card', ['item' => $item])
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

        <script src="https://js.paystack.co/v1/inline.js"></script>
        <script>window.SPACECHIP_INITIAL_ESIMS = @json($initialMyEsims);</script>
        <script src="{{ asset('js/dashboard.js') }}?v={{ @filemtime(public_path('js/dashboard.js')) }}"></script>
        <script>
            // Welcome header: local-time greeting and quick actions.
            (function () {
                var h = new Date().getHours();
                var g = h < 5 ? 'Good evening' : h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
                var greet = document.getElementById('scdGreeting');
                if (greet) greet.textContent = g;
                var date = document.getElementById('scdDate');
                if (date) date.textContent = new Date().toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });

                var scrollTo = function (el) {
                    if (!el) return;
                    var top = el.getBoundingClientRect().top + window.scrollY - 90;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                };

                document.querySelectorAll('[data-scd-action]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var action = btn.getAttribute('data-scd-action');
                        if (action === 'topup') {
                            scrollTo(document.querySelector('.wallet-panel'));
                            setTimeout(function () { document.getElementById('walletDepositAmount')?.focus({ preventScroll: true }); }, 450);
                        } else if (action === 'my-esims') {
                            document.getElementById('myEsimsNavBtn')?.click();
                            scrollTo(document.querySelector('.controls-row'));
                        } else if (action === 'social') {
                            document.querySelector('[data-asset-toggle="social"]')?.click();
                            scrollTo(document.querySelector('.controls-row'));
                        }
                    });
                });
            })();
        </script>
    </div>
</x-app-layout>
