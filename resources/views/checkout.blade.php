<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Checkout - {{ config('app.name', 'spacechip') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif;--primary:#f27457;--secondary:#145454}
            *{box-sizing:border-box}
            body{margin:0;color:#0f1f1f;font-family:var(--font-sans);min-height:100vh;background:
                radial-gradient(900px 520px at 12% 14%, rgba(242,116,87,.32) 0%, rgba(242,116,87,0) 60%),
                radial-gradient(980px 560px at 88% 18%, rgba(20,84,84,.26) 0%, rgba(20,84,84,0) 62%),
                radial-gradient(1100px 700px at 50% 92%, rgba(242,116,87,.18) 0%, rgba(242,116,87,0) 65%),
                linear-gradient(180deg, #F7F7F8 0%, #F5F6F8 60%, #F7F7F8 100%)}
            body::before{content:"";position:fixed;inset:-20%;background:
                radial-gradient(520px 420px at 18% 32%, rgba(242,116,87,.35) 0%, rgba(242,116,87,0) 70%),
                radial-gradient(560px 460px at 82% 38%, rgba(20,84,84,.28) 0%, rgba(20,84,84,0) 72%),
                radial-gradient(700px 520px at 58% 66%, rgba(242,116,87,.22) 0%, rgba(242,116,87,0) 74%);filter:blur(26px);opacity:.9;z-index:-1}
            .container{max-width:1120px;margin:0 auto;padding:24px}
            .header{display:flex;align-items:center;justify-content:space-between}
            .brand{display:flex;align-items:center;gap:10px}
            .logo{height:36px;width:36px;border-radius:12px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
            .card{margin-top:20px;display:grid;grid-template-columns:1fr;gap:20px}
            @media(min-width:900px){.card{grid-template-columns:1.3fr .7fr}}
            .panel{padding:22px;border-radius:22px;background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 16px 40px rgba(15,31,31,.08)}
            .title{font-size:20px;font-weight:800;color:#0b1a1a}
            .summary-item{display:flex;justify-content:space-between;align-items:center;margin:10px 0}
            .pill{display:inline-flex;gap:8px;align-items:center;padding:6px 10px;border-radius:9999px;background:rgba(20,84,84,.08);color:rgba(20,84,84,.92);font-weight:700;font-size:12px}
            .btn-primary{width:100%;padding:14px;border-radius:16px;background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;font-weight:800;border:none;cursor:pointer;box-shadow:0 12px 28px rgba(242,116,87,.18)}
            .btn-secondary{padding:10px 14px;border-radius:12px;background:rgba(255,255,255,.8);border:1px solid rgba(20,84,84,.14);font-weight:700}
            .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
            .field{display:flex;flex-direction:column;gap:6px}
            .label{font-size:12px;font-weight:700;color:rgba(15,31,31,.6);text-transform:uppercase;letter-spacing:.05em}
            .input{padding:12px 14px;border-radius:12px;border:1px solid rgba(15,31,31,.12);background:rgba(255,255,255,.85);outline:none}
            .price{font-size:22px;font-weight:900;color:#145454}
            .modal-hidden{display:none!important}
            .modal-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(10,10,15,.55);backdrop-filter:blur(10px);z-index:9999;overflow:auto}
            .modal-overlay::before{content:"";position:absolute;inset:0;background:
                radial-gradient(ellipse 60% 40% at 20% 80%, rgba(242,116,87,.16) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at 80% 20%, rgba(20,84,84,.14) 0%, transparent 60%);pointer-events:none}
            @media(min-width:720px){.modal-overlay{padding:24px}}
            .pm-noise{position:absolute;inset:0;opacity:.035;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");background-size:200px;pointer-events:none}
            .modal{position:relative;width:100%;max-width:560px;background:rgba(16,16,24,.92);border:1px solid rgba(255,255,255,.08);border-radius:24px;backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);box-shadow:0 0 0 1px rgba(255,255,255,.04) inset,0 32px 80px rgba(0,0,0,.6),0 0 120px rgba(242,116,87,.05);overflow:hidden;animation:modalIn .4s cubic-bezier(.16,1,.3,1) both}
            @keyframes modalIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
            @keyframes modalOut{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(12px) scale(.98)}}
            .modal-head{display:flex;align-items:center;justify-content:space-between;padding:24px 28px 0}
            .modal-eyebrow{font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(242,116,87,.72);margin-bottom:4px}
            .modal-title{font-size:22px;font-weight:850;color:#f5f4f0;letter-spacing:-.02em}
            .close-btn{width:36px;height:36px;border-radius:10px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);color:rgba(255,255,255,.45);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;flex-shrink:0}
            .close-btn:hover{background:rgba(255,255,255,.08);color:rgba(255,255,255,.85);border-color:rgba(255,255,255,.14)}
            .modal-body{padding:24px 28px 28px;display:flex;flex-direction:column;gap:14px}
            .pay-option{width:100%;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:22px;cursor:pointer;text-align:left;transition:all .25s cubic-bezier(.16,1,.3,1);position:relative;overflow:hidden}
            .pay-option[disabled]{cursor:not-allowed;opacity:.55;transform:none;box-shadow:none}
            .pay-option[disabled]:hover{border-color:rgba(255,255,255,.07);background:rgba(255,255,255,.03);transform:none;box-shadow:none}
            .pay-option[disabled]::before{opacity:0}
            .pay-option.is-loading{opacity:.75}
            .pay-option::before{content:"";position:absolute;inset:0;opacity:0;transition:opacity .3s;border-radius:18px;pointer-events:none}
            .pay-option.card-opt::before{background:radial-gradient(ellipse 80% 60% at 10% 50%, rgba(242,116,87,.12) 0%, transparent 70%)}
            .pay-option.crypto-opt::before{background:radial-gradient(ellipse 80% 60% at 10% 50%, rgba(20,84,84,.14) 0%, transparent 70%)}
            .pay-option:hover{border-color:rgba(255,255,255,.14);background:rgba(255,255,255,.05);transform:translateY(-2px);box-shadow:0 12px 40px rgba(0,0,0,.3)}
            .pay-option:hover::before{opacity:1}
            .pay-option:active{transform:translateY(0)}
            .pay-option.card-opt:hover{border-color:rgba(242,116,87,.28);box-shadow:0 12px 40px rgba(0,0,0,.3),0 0 0 1px rgba(242,116,87,.10) inset}
            .pay-option.crypto-opt:hover{border-color:rgba(20,84,84,.30);box-shadow:0 12px 40px rgba(0,0,0,.3),0 0 0 1px rgba(20,84,84,.12) inset}
            .pay-option .shimmer{position:absolute;top:0;left:-100%;width:60%;height:100%;background:linear-gradient(90deg, transparent, rgba(255,255,255,.03), transparent);transition:left .6s ease;pointer-events:none}
            .pay-option:hover .shimmer{left:160%}
            .pay-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;gap:12px}
            .pay-kicker{font-size:11px;font-weight:500;color:rgba(255,255,255,.35);letter-spacing:.04em;margin-bottom:2px}
            .pay-name{font-size:20px;font-weight:850;color:#f5f4f0;letter-spacing:-.02em;margin-bottom:4px}
            .pay-sub{font-size:12.5px;color:rgba(255,255,255,.40);font-weight:400;line-height:1.5}
            .badge{font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border-radius:20px;flex-shrink:0;margin-top:2px}
            .badge-warm{background:rgba(242,116,87,.14);border:1px solid rgba(242,116,87,.24);color:#f27457}
            .badge-cool{background:rgba(20,84,84,.14);border:1px solid rgba(20,84,84,.24);color:rgba(180,255,243,.92)}
            .badge-slate{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:rgba(245,244,240,.86)}
            .logos{display:flex;gap:8px;flex-wrap:wrap}
            .logo-pill{display:flex;align-items:center;gap:7px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:7px 11px;transition:all .2s}
            .logo-pill:hover{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.14)}
            .logo-pill span{font-size:12px;font-weight:600;color:rgba(255,255,255,.7);letter-spacing:.01em}
            .pay-loading{margin-top:10px;display:none;align-items:center;gap:10px;font-size:12px;font-weight:650;color:rgba(255,255,255,.65)}
            .pay-loading.show{display:flex}
            .spin{height:14px;width:14px;border-radius:9999px;border:2px solid rgba(255,255,255,.18);border-top-color:rgba(242,116,87,.9);animation:spin 1s linear infinite}
            @keyframes spin{to{transform:rotate(360deg)}}
            .currency-row{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap}
            .currency-pill{display:inline-flex;align-items:center;justify-content:center;padding:8px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);color:rgba(255,255,255,.65);font-size:12px;font-weight:750;letter-spacing:.02em;cursor:pointer;user-select:none}
            .currency-pill.active{background:rgba(242,116,87,.14);border-color:rgba(242,116,87,.26);color:#f5f4f0}
            .currency-pill[aria-disabled="true"]{opacity:.6;cursor:not-allowed}
            .divider{display:flex;align-items:center;gap:12px;padding:0 2px}
            .divider-line{flex:1;height:1px;background:rgba(255,255,255,.06)}
            .divider-text{font-size:11px;color:rgba(255,255,255,.22);letter-spacing:.06em;font-weight:500}
            .secure-row{display:flex;align-items:center;justify-content:center;gap:6px;padding-top:4px}
            .secure-row svg{color:rgba(255,255,255,.22);flex-shrink:0}
            .secure-row span{font-size:11.5px;color:rgba(255,255,255,.24);letter-spacing:.02em}
            .modal-status{display:none;margin-top:10px;font-size:12.5px;line-height:1.5;color:rgba(255,255,255,.55)}
            .modal-status.show{display:block}
            .pm-grid{display:grid;grid-template-columns:1fr;gap:14px}
            @media(min-width:720px){.pm-grid{grid-template-columns:1fr 1fr}}

            @media(max-width:640px){
                .container{padding:16px}
                .header{flex-wrap:wrap;gap:12px}
                .panel{padding:18px;border-radius:18px}
                .title{font-size:18px}
                .row{grid-template-columns:1fr}
                .modal-head{padding:18px 18px 0}
                .modal-body{padding:18px 18px 20px}
                .pay-option{padding:18px}
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <a href="/" class="brand">
                    <div class="logo">SC</div>
                    <div style="font-weight:900;color:rgba(20,84,84,.92)">spacechip</div>
                </a>
                <a href="javascript:history.back()" class="btn-secondary">Back</a>
            </div>

            <div class="card">
                <div class="panel">
                    <div class="title">Checkout</div>
                    <div style="margin-top:12px;display:flex;align-items:center;gap:12px">
                        <div class="pill">
                            <span>{{ $asset['type'] }}</span>
                        </div>
                        <div class="pill">
                            <span>{{ $asset['name'] }}</span>
                        </div>
                    </div>

                    <div style="margin-top:16px;border-top:1px solid rgba(15,31,31,.08);padding-top:16px">
                        <div class="summary-item">
                            <div><strong>Data</strong></div>
                            <div>{{ $bundle['data'] }}</div>
                        </div>
                        <div class="summary-item">
                            <div><strong>Validity</strong></div>
                            <div>{{ $bundle['validity'] }}</div>
                        </div>
                        <div class="summary-item">
                            <div><strong>Plan Type</strong></div>
                            <div>{{ $bundle['package_type'] === 'DATA-ONLY' ? 'Data Only' : 'Data + Calls' }}</div>
                        </div>
                        <div class="summary-item" style="border-top:1px dashed rgba(15,31,31,.1);padding-top:12px">
                            <div class="price">{{ $bundle['price_formatted'] }}</div>
                            <div style="font-size:12px;color:rgba(15,31,31,.6);font-weight:700">One-time payment</div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="title">Your Details</div>
                    <div style="margin-top:12px" class="row">
                        <div class="field">
                            <div class="label">Name</div>
                            <input class="input" value="{{ auth()->user()->name ?? (trim((auth()->user()->first_name ?? '').' '.(auth()->user()->last_name ?? ''))) }}" readonly>
                        </div>
                        <div class="field">
                            <div class="label">Email</div>
                            <input class="input" value="{{ auth()->user()->email }}" readonly>
                        </div>
                    </div>
                    <div style="margin-top:16px">
                        <button class="btn-primary" id="proceedToPaymentBtn" type="button">Proceed to Payment</button>
                    </div>
                    <div id="paymentStatus" style="margin-top:8px;font-size:12px;color:rgba(15,31,31,.6)"></div>
                </div>
            </div>
        </div>

        <div id="paymentModalOverlay" class="modal-overlay modal-hidden" role="dialog" aria-modal="true" aria-labelledby="paymentModalTitle">
            <div class="pm-noise"></div>
            <div class="modal">
                <div class="modal-head">
                    <div>
                        <div class="modal-eyebrow">Secure checkout</div>
                        <div class="modal-title" id="paymentModalTitle">Choose payment method</div>
                    </div>
                    <button class="close-btn" type="button" id="closePaymentModalBtn" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/>
                            <path d="M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <button class="pay-option card-opt" type="button" id="payWithCardBtn">
                        <div class="shimmer"></div>
                        <div class="pay-top">
                            <div>
                                <div class="pay-kicker">Pay with</div>
                                <div class="pay-name">Card</div>
                                <div class="pay-sub">Visa, Mastercard &amp; Verve accepted.</div>
                            </div>
                            <span class="badge badge-warm">Fast</span>
                        </div>
                        <div class="logos">
                            <div class="logo-pill">
                                <svg width="22" height="22" viewBox="0 0 38 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="38" height="24" rx="4" fill="#1A1F71"/>
                                    <path d="M14.5 16.8H12.1L13.6 7.2H16L14.5 16.8Z" fill="white"/>
                                    <path d="M22.3 7.4C21.8 7.2 21 7 20 7C17.6 7 15.9 8.2 15.9 9.9C15.9 11.2 17.1 11.9 18 12.4C18.9 12.9 19.2 13.2 19.2 13.6C19.2 14.2 18.4 14.5 17.7 14.5C16.7 14.5 16.1 14.3 15.3 14L15 13.9L14.7 16.1C15.3 16.4 16.4 16.6 17.6 16.6C20.2 16.6 21.8 15.4 21.8 13.6C21.8 12.6 21.2 11.8 19.8 11.1C19 10.7 18.5 10.4 18.5 9.9C18.5 9.5 18.9 9.1 19.8 9.1C20.6 9.1 21.2 9.2 21.6 9.4L21.8 9.5L22.3 7.4Z" fill="white"/>
                                    <path d="M25.6 13.5C25.8 13 26.7 10.6 26.7 10.6C26.7 10.6 26.9 10.1 27 9.7L27.2 10.5C27.2 10.5 27.8 13.1 27.9 13.5H25.6ZM30.2 7.2H28.3C27.7 7.2 27.3 7.4 27 8L23.4 16.8H26L26.5 15.4H29.6L29.9 16.8H32.2L30.2 7.2Z" fill="white"/>
                                    <path d="M11.3 7.2L8.9 13.3L8.7 12.3C8.2 10.7 6.7 9 5 8.1L7.2 16.8H9.9L14 7.2H11.3Z" fill="white"/>
                                    <path d="M6.5 7.2H2.4L2.4 7.4C5.6 8.2 7.8 10 8.7 12.3L7.8 8.1C7.6 7.4 7.1 7.2 6.5 7.2Z" fill="#FAA61A"/>
                                </svg>
                                <span>Visa</span>
                            </div>
                            <div class="logo-pill">
                                <svg width="22" height="22" viewBox="0 0 38 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="38" height="24" rx="4" fill="#252525"/>
                                    <circle cx="15" cy="12" r="6.5" fill="#EB001B"/>
                                    <circle cx="23" cy="12" r="6.5" fill="#F79E1B"/>
                                    <path d="M19 6.8C20.4 7.8 21.3 9.3 21.3 12C21.3 14.7 20.4 16.2 19 17.2C17.6 16.2 16.7 14.7 16.7 12C16.7 9.3 17.6 7.8 19 6.8Z" fill="#FF5F00"/>
                                </svg>
                                <span>Mastercard</span>
                            </div>
                            <div class="logo-pill">
                                <svg width="22" height="22" viewBox="0 0 38 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="38" height="24" rx="4" fill="#1E7A3A"/>
                                    <path d="M8 8H14L19 16H13L8 8Z" fill="white" opacity="0.9"/>
                                    <path d="M14 8H20L25 16H19L14 8Z" fill="white" opacity="0.6"/>
                                    <path d="M20 8H26L30 16H24L20 8Z" fill="white" opacity="0.3"/>
                                </svg>
                                <span>Verve</span>
                            </div>
                        </div>
                        <div class="currency-row" id="currencyRow">
                            <div class="currency-pill active" role="button" tabindex="0" data-pay-currency="NGN">Pay in NGN</div>
                            <div class="currency-pill" role="button" tabindex="0" data-pay-currency="USD">Pay in USD</div>
                        </div>
                        <div class="pay-loading" data-paystack-loading>
                            <span class="spin"></span>
                            <span>Loading Paystack…</span>
                        </div>
                    </button>

                    <div class="divider">
                        <div class="divider-line"></div>
                        <span class="divider-text">or</span>
                        <div class="divider-line"></div>
                    </div>

                    <div class="pm-grid">
                        <button class="pay-option crypto-opt" type="button" id="payWithCryptoBtn">
                            <div class="shimmer"></div>
                            <div class="pay-top">
                                <div>
                                    <div class="pay-kicker">Pay with</div>
                                    <div class="pay-name">Crypto</div>
                                    <div class="pay-sub">BTC, ETH, USDT, USDC &amp; more.</div>
                                </div>
                                <span class="badge badge-cool">Global</span>
                            </div>
                            <div class="logos">
                                <div class="logo-pill">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="11" cy="11" r="11" fill="#F7931A"/>
                                        <path d="M14.6 9.6C14.8 8.3 13.8 7.6 12.4 7.1L12.9 5.1L11.7 4.8L11.2 6.7C10.9 6.6 10.5 6.6 10.2 6.5L10.7 4.6L9.5 4.3L9 6.3C8.8 6.2 8.5 6.2 8.3 6.1L8.3 6.1L6.7 5.7L6.4 7C6.4 7 7.3 7.2 7.3 7.2C7.8 7.3 7.9 7.7 7.9 7.9L6.9 12C6.9 12.1 6.8 12.4 6.4 12.3L5.5 12.1L4.9 13.5L6.5 13.9C6.8 13.97 7.1 14.04 7.4 14.11L6.9 16.1L8.1 16.4L8.6 14.4C8.9 14.5 9.3 14.6 9.6 14.7L9.1 16.6L10.3 16.9L10.8 14.9C13.1 15.3 14.8 15.1 15.5 13C16.1 11.3 15.5 10.4 14.3 9.8C15.1 9.6 15.7 9.1 14.6 9.6ZM13.1 12.1C12.7 13.8 10 13 9.1 12.8L9.8 10.1C10.7 10.3 13.5 10.3 13.1 12.1ZM13.5 9.5C13.2 11 11 10.3 10.3 10.1L10.9 7.6C11.6 7.8 13.9 7.9 13.5 9.5Z" fill="white"/>
                                    </svg>
                                    <span>Bitcoin</span>
                                </div>
                                <div class="logo-pill">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="11" cy="11" r="11" fill="#627EEA"/>
                                        <path d="M11 3.5V9.2L15.5 11.2L11 3.5Z" fill="white" fill-opacity="0.6"/>
                                        <path d="M11 3.5L6.5 11.2L11 9.2V3.5Z" fill="white"/>
                                        <path d="M11 14.4V18.5L15.5 12L11 14.4Z" fill="white" fill-opacity="0.6"/>
                                        <path d="M11 18.5V14.4L6.5 12L11 18.5Z" fill="white"/>
                                        <path d="M11 13.5L15.5 11.2L11 9.2V13.5Z" fill="white" fill-opacity="0.2"/>
                                        <path d="M6.5 11.2L11 13.5V9.2L6.5 11.2Z" fill="white" fill-opacity="0.6"/>
                                    </svg>
                                    <span>Ethereum</span>
                                </div>
                                <div class="logo-pill">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="11" cy="11" r="11" fill="#26A17B"/>
                                        <path d="M12.1 11.5C12 11.5 11.4 11.6 11 11.6C10.6 11.6 10 11.5 9.9 11.5C8.1 11.4 6.8 11.1 6.8 10.7C6.8 10.3 8.1 10 9.9 9.9V11.1C10 11.2 10.5 11.2 11 11.2C11.5 11.2 12 11.2 12.1 11.1V9.9C13.9 10 15.2 10.3 15.2 10.7C15.2 11.1 13.9 11.4 12.1 11.5ZM12.1 9.7V8.7H14.6V7H7.4V8.7H9.9V9.7C7.9 9.8 6.4 10.2 6.4 10.7C6.4 11.2 7.9 11.6 9.9 11.7V15H12.1V11.7C14.1 11.6 15.6 11.2 15.6 10.7C15.6 10.2 14.1 9.8 12.1 9.7Z" fill="white"/>
                                    </svg>
                                    <span>USDT</span>
                                </div>
                                <div class="logo-pill">
                                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="11" cy="11" r="11" fill="#2775CA"/>
                                        <path d="M13.5 12.8C13.5 11.5 12.7 11.1 11.1 10.9C9.9 10.7 9.7 10.4 9.7 9.9C9.7 9.4 10.1 9.1 10.9 9.1C11.6 9.1 12 9.3 12.2 9.9C12.2 10 12.3 10.1 12.5 10.1H13.2C13.4 10.1 13.5 10 13.5 9.8C13.3 8.9 12.6 8.2 11.6 8V7.1C11.6 6.9 11.5 6.8 11.2 6.8H10.6C10.3 6.8 10.2 6.9 10.2 7.1V8C9 8.2 8.3 9 8.3 10C8.3 11.3 9.1 11.7 10.7 11.9C11.8 12.1 12.1 12.3 12.1 12.9C12.1 13.5 11.6 13.9 10.8 13.9C9.8 13.9 9.4 13.5 9.3 12.9C9.3 12.7 9.1 12.6 8.9 12.6H8.2C8 12.6 7.9 12.7 7.9 12.9C8.1 14 8.8 14.7 10.2 14.9V15.9C10.2 16.1 10.3 16.2 10.6 16.2H11.2C11.5 16.2 11.6 16.1 11.6 15.9V15C12.8 14.8 13.5 14 13.5 12.8Z" fill="white"/>
                                    </svg>
                                    <span>USDC</span>
                                </div>
                            </div>
                            <div class="pay-loading" data-cryptomus-loading="crypto">
                                <span class="spin"></span>
                                <span>Redirecting to Cryptomus…</span>
                            </div>
                        </button>

                        <button class="pay-option crypto-opt" type="button" id="payWithCryptomusCardBtn" disabled aria-disabled="true" data-always-disabled="true">
                            <div class="shimmer"></div>
                            <div class="pay-top">
                                <div>
                                    <div class="pay-kicker">Pay with</div>
                                    <div class="pay-name">Card</div>
                                    <div class="pay-sub">Temporarily unavailable.</div>
                                </div>
                                <span class="badge badge-slate">Disabled</span>
                            </div>
                            <div class="logos">
                                <div class="logo-pill">
                                    <span>Visa</span>
                                </div>
                                <div class="logo-pill">
                                    <span>Mastercard</span>
                                </div>
                            </div>
                            <div class="pay-loading" data-cryptomus-loading="card">
                                <span class="spin"></span>
                                <span>Redirecting to Cryptomus…</span>
                            </div>
                        </button>
                    </div>

                    <div class="secure-row">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <span>256-bit SSL encrypted · Payments secured</span>
                    </div>
                    <div class="modal-status" id="paymentModalStatus"></div>
                </div>
            </div>
        </div>

        <script src="https://js.paystack.co/v1/inline.js"></script>
        <script>
            (() => {
                const overlay = document.getElementById('paymentModalOverlay');
                const modal = overlay.querySelector('.modal');
                const openBtn = document.getElementById('proceedToPaymentBtn');
                const closeBtn = document.getElementById('closePaymentModalBtn');
                const payWithCardBtn = document.getElementById('payWithCardBtn');
                const payWithCryptoBtn = document.getElementById('payWithCryptoBtn');
                const payWithCryptomusCardBtn = document.getElementById('payWithCryptomusCardBtn');
                const statusEl = document.getElementById('paymentStatus');
                const modalStatusEl = document.getElementById('paymentModalStatus');
                const paystackLoadingEl = payWithCardBtn.querySelector('[data-paystack-loading]');
                const cryptomusCryptoLoadingEl = payWithCryptoBtn.querySelector('[data-cryptomus-loading="crypto"]');
                const cryptomusCardLoadingEl = payWithCryptomusCardBtn.querySelector('[data-cryptomus-loading="card"]');

                const normalizeEnvValue = (v) => {
                    if (v === null || v === undefined) return '';
                    const s = String(v).trim();
                    return s.replace(/^"+|"+$/g, '').replace(/^'+|'+$/g, '').trim();
                };

                const paystackKey = normalizeEnvValue(@json((string) (config('services.paystack.public_key') ?: env('PAYSTACK_PUBLIC_KEY'))));
                const csrfToken = @json(csrf_token());
                const checkoutContext = {
                    type: @json((string) ($type ?? '')),
                    id: @json((string) ($id ?? '')),
                    bundle: @json((string) ($bundle['id'] ?? '')),
                    package_type: @json((string) ($bundle['package_type'] ?? 'DATA-ONLY')),
                };
                const paystackAmounts = {
                    NGN: @json((int) ($paystackAmountMinorNgn ?? 0)),
                    USD: @json((int) ($paystackAmountMinorUsd ?? 0)),
                };
                const conversionInfo = {
                    usd_price: @json((float) ($usdPrice ?? 0)),
                    usd_to_ngn_rate: @json((float) ($usdToNgnRate ?? 0)),
                    rate_source: @json((string) ($usdToNgnRateSource ?? '')),
                    ngn_amount_raw: @json((float) ($paystackAmountNgnRaw ?? 0)),
                    ngn_amount_adjusted: @json((float) ($paystackAmountNgnAdjusted ?? 0)),
                    ngn_amount_minor: @json((int) ($paystackAmountMinorNgn ?? 0)),
                };
                const userEmail = @json((string) auth()->user()->email);
                let selectedPayCurrency = 'NGN';
                let cryptomusInvoiceController = null;
                let paystackVerifyController = null;
                let paystackStatusController = null;
                let paystackStatusTimer = null;

                const hardCloseModal = () => {
                    overlay.classList.add('modal-hidden');
                    document.body.style.overflow = '';
                    modal.style.animation = '';
                };

                const resetPaymentUi = () => {
                    if (cryptomusInvoiceController) {
                        cryptomusInvoiceController.abort();
                        cryptomusInvoiceController = null;
                    }
                    if (paystackVerifyController) {
                        paystackVerifyController.abort();
                        paystackVerifyController = null;
                    }
                    if (paystackStatusController) {
                        paystackStatusController.abort();
                        paystackStatusController = null;
                    }
                    if (paystackStatusTimer) {
                        window.clearTimeout(paystackStatusTimer);
                        paystackStatusTimer = null;
                    }
                    setPaystackLoading(false);
                    setCryptomusLoading(false, 'crypto');
                    setCryptomusLoading(false, 'card');
                    setStatus('', 'neutral');
                };

                const open = () => {
                    overlay.classList.remove('modal-hidden');
                    document.body.style.overflow = 'hidden';
                    modal.style.animation = 'none';
                    void modal.offsetHeight;
                    modal.style.animation = '';
                    resetPaymentUi();
                    closeBtn.focus();
                };

                const close = () => {
                    resetPaymentUi();
                    modal.style.animation = 'modalOut .22s ease both';
                    window.setTimeout(() => {
                        overlay.classList.add('modal-hidden');
                        document.body.style.overflow = '';
                        modal.style.animation = '';
                        openBtn.focus();
                    }, 220);
                };

                openBtn.addEventListener('click', open);
                closeBtn.addEventListener('click', close);

                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) close();
                });

                window.addEventListener('keydown', (e) => {
                    if (overlay.classList.contains('modal-hidden')) return;
                    if (e.key === 'Escape') close();
                });

                window.addEventListener('pageshow', () => {
                    resetPaymentUi();
                    hardCloseModal();
                });

                window.addEventListener('pagehide', () => {
                    resetPaymentUi();
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') {
                        resetPaymentUi();
                    }
                });

                const setStatus = (text, tone = 'neutral') => {
                    const safe = text || '';
                    if (statusEl) {
                        statusEl.textContent = safe;
                        if (tone === 'success') statusEl.style.color = 'rgba(20,84,84,.92)';
                        else if (tone === 'error') statusEl.style.color = 'rgba(242,116,87,.92)';
                        else statusEl.style.color = 'rgba(15,31,31,.6)';
                    }
                    if (modalStatusEl) {
                        modalStatusEl.textContent = safe;
                        modalStatusEl.classList.toggle('show', safe !== '');
                        if (tone === 'success') modalStatusEl.style.color = 'rgba(180,255,243,.92)';
                        else if (tone === 'error') modalStatusEl.style.color = 'rgba(242,116,87,.92)';
                        else modalStatusEl.style.color = 'rgba(255,255,255,.55)';
                    }
                };

                const setDisabled = (isDisabled) => {
                    const disabled = !!isDisabled;
                    payWithCardBtn.disabled = disabled;
                    payWithCryptoBtn.disabled = disabled;
                    payWithCryptomusCardBtn.disabled = disabled || payWithCryptomusCardBtn.getAttribute('data-always-disabled') === 'true';
                    const pills = document.querySelectorAll('[data-pay-currency]');
                    pills.forEach((p) => p.setAttribute('aria-disabled', disabled ? 'true' : 'false'));
                };

                const setPaystackLoading = (isLoading) => {
                    const disabled = !!isLoading;
                    if (paystackLoadingEl) paystackLoadingEl.classList.toggle('show', disabled);
                    if (cryptomusCryptoLoadingEl) cryptomusCryptoLoadingEl.classList.remove('show');
                    if (cryptomusCardLoadingEl) cryptomusCardLoadingEl.classList.remove('show');
                    setDisabled(disabled);
                };

                const setCryptomusLoading = (isLoading, kind) => {
                    const disabled = !!isLoading;
                    if (cryptomusCryptoLoadingEl) cryptomusCryptoLoadingEl.classList.toggle('show', disabled && kind === 'crypto');
                    if (cryptomusCardLoadingEl) cryptomusCardLoadingEl.classList.toggle('show', disabled && kind === 'card');
                    if (paystackLoadingEl) paystackLoadingEl.classList.remove('show');
                    setDisabled(disabled);
                };

                const currencyRow = document.getElementById('currencyRow');
                if (currencyRow) {
                    currencyRow.addEventListener('click', (e) => {
                        const el = e.target.closest('[data-pay-currency]');
                        if (!el) return;
                        if (el.getAttribute('aria-disabled') === 'true') return;
                        e.preventDefault();
                        e.stopPropagation();
                        const cur = String(el.getAttribute('data-pay-currency') || '').toUpperCase();
                        if (!['NGN', 'USD'].includes(cur)) return;
                        selectedPayCurrency = cur;
                        currencyRow.querySelectorAll('[data-pay-currency]').forEach((pill) => {
                            pill.classList.toggle('active', String(pill.getAttribute('data-pay-currency')).toUpperCase() === cur);
                        });
                    });
                }

                const startPaystack = async () => {
                    if (!paystackKey || !String(paystackKey).startsWith('pk_')) {
                        setStatus('Paystack public key is not configured.', 'error');
                        return;
                    }

                    if (typeof window.PaystackPop === 'undefined') {
                        setStatus('Paystack did not load. Disable adblockers for this site or refresh and try again.', 'error');
                        return;
                    }

                    if (!userEmail) {
                        setStatus('Missing user email.', 'error');
                        return;
                    }

                    const amountMinor = paystackAmounts[selectedPayCurrency] || 0;
                    if (!amountMinor || amountMinor <= 0) {
                        setStatus('Invalid amount for this bundle.', 'error');
                        return;
                    }

                    setPaystackLoading(true);
                    setStatus('Loading Paystack…', 'neutral');

                    try {
                        const reference = `sc_${Date.now()}_${Math.random().toString(16).slice(2)}`;
                        let handler;
                        try {
                            console.groupCollapsed('Spacechip FX conversion → Paystack');
                            if (selectedPayCurrency === 'USD') {
                                console.log('Selected currency: USD');
                                console.log('USD price:', conversionInfo.usd_price);
                                console.log('USD (minor):', paystackAmounts.USD);
                            } else {
                                console.log('Selected currency: NGN');
                                console.log('USD price:', conversionInfo.usd_price);
                                console.log('USD/NGN black market rate:', conversionInfo.usd_to_ngn_rate, `(${conversionInfo.rate_source || 'parallel'})`);
                                console.log('NGN amount (raw):', conversionInfo.ngn_amount_raw);
                                console.log('NGN amount (/10):', conversionInfo.ngn_amount_adjusted);
                                console.log('NGN (minor):', paystackAmounts.NGN);
                            }
                            console.groupEnd();

                            handler = window.PaystackPop.setup({
                            key: paystackKey,
                            email: userEmail,
                            amount: 100 * 100,
                            currency: 'NGN',
                            ref: reference,
                            metadata: checkoutContext,
                            callback: function (response) {
                                setStatus('Verifying payment...', 'neutral');

                                if (paystackVerifyController) {
                                    paystackVerifyController.abort();
                                }
                                paystackVerifyController = new AbortController();

                                fetch('/api/paystack/verify', {
                                    method: 'POST',
                                    signal: paystackVerifyController.signal,
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({
                                        reference: response.reference,
                                        type: checkoutContext.type,
                                        id: checkoutContext.id,
                                        bundle: checkoutContext.bundle,
                                        package_type: checkoutContext.package_type
                                    })
                                })
                                    .then(async (res) => {
                                        const contentType = (res.headers.get('content-type') || '').toLowerCase();
                                        let body = null;
                                        if (contentType.includes('application/json')) {
                                            body = await res.json().catch(() => null);
                                        } else {
                                            body = await res.text().catch(() => null);
                                        }
                                        return { res, body };
                                    })
                                    .then(({ res, body }) => {
                                        if (!res.ok) {
                                            const msg = (body && typeof body === 'object' && body.message) ? body.message : '';
                                            setStatus(`Payment verification failed (HTTP ${res.status}). ${msg || 'Please contact support if you were charged.'}`, 'error');
                                            return;
                                        }

                                        const json = (body && typeof body === 'object') ? body : {};
                                        if (json.ok) {
                                            if (json.fulfillment && json.fulfillment.ok) {
                                                setStatus('Payment successful. Your eSIM has been fulfilled and sent to your email.', 'success');
                                                close();
                                                return;
                                            } else if (json.fulfillment && json.fulfillment.ok === false) {
                                                const pending = !!json.fulfillment.pending;
                                                setStatus(json.fulfillment.message || 'Payment confirmed. Preparing your eSIM…', pending ? 'neutral' : 'success');
                                                if (pending) {
                                                    const ref = json.reference || response.reference;

                                                    const poll = async () => {
                                                        if (paystackStatusController) {
                                                            paystackStatusController.abort();
                                                        }
                                                        paystackStatusController = new AbortController();

                                                        try {
                                                            const res2 = await fetch(`/api/paystack/status?reference=${encodeURIComponent(ref)}`, {
                                                                method: 'GET',
                                                                signal: paystackStatusController.signal,
                                                                headers: { 'Accept': 'application/json' }
                                                            });
                                                            const json2 = await res2.json().catch(() => ({}));
                                                            if (!res2.ok) {
                                                                setStatus(json2.message || 'Payment confirmed. Preparing your eSIM…', 'neutral');
                                                                paystackStatusTimer = window.setTimeout(poll, 5000);
                                                                return;
                                                            }

                                                            if (json2.fulfillment && json2.fulfillment.ok) {
                                                                setStatus('Payment successful. Your eSIM has been fulfilled and sent to your email.', 'success');
                                                                paystackStatusController = null;
                                                                paystackStatusTimer = null;
                                                                setPaystackLoading(false);
                                                                close();
                                                                return;
                                                            }

                                                            if (json2.fulfillment && json2.fulfillment.message) {
                                                                setStatus(json2.fulfillment.message, 'neutral');
                                                            } else {
                                                                setStatus('Payment confirmed. Preparing your eSIM…', 'neutral');
                                                            }

                                                            paystackStatusTimer = window.setTimeout(poll, 5000);
                                                        } catch (e) {
                                                            if (e && e.name === 'AbortError') {
                                                                return;
                                                            }
                                                            setStatus('Payment confirmed. Preparing your eSIM…', 'neutral');
                                                            paystackStatusTimer = window.setTimeout(poll, 5000);
                                                        }
                                                    };

                                                    poll();
                                                } else {
                                                    close();
                                                }
                                            } else {
                                                setStatus('Payment confirmed. Preparing your eSIM…', 'success');
                                                close();
                                            }
                                            return;
                                        }

                                        setStatus(`Payment not confirmed (status: ${json.status || 'unknown'}). Please contact support if you were charged.`, 'error');
                                    })
                                    .catch((e) => {
                                        if (e && e.name === 'AbortError') {
                                            return;
                                        }
                                        setStatus(`Payment verification failed. ${e && e.message ? e.message : 'Please contact support if you were charged.'}`, 'error');
                                    })
                                    .finally(() => {
                                        paystackVerifyController = null;
                                        setPaystackLoading(false);
                                    });
                            },
                            onClose: function () {
                                setPaystackLoading(false);
                                setStatus('', 'neutral');
                                close();
                            }
                            });
                        } catch (e) {
                            const msg = e && e.message ? String(e.message) : '';
                            setStatus(msg ? `Paystack could not initialize: ${msg}` : 'Paystack could not initialize. Check your Paystack key and currency.', 'error');
                            setPaystackLoading(false);
                            return;
                        }

                        try {
                            handler.openIframe();
                        } catch (e) {
                            const msg = e && e.message ? String(e.message) : '';
                            setStatus(msg ? `Paystack could not open: ${msg}` : 'Paystack could not open. Disable adblockers and try again.', 'error');
                            setPaystackLoading(false);
                        }
                    } catch (e) {
                        setStatus('Payment initialization failed. Please try again.', 'error');
                        setPaystackLoading(false);
                    } finally {
                        // loading state is cleared on close / callback
                    }
                };

                const startCryptomus = async (kind) => {
                    const mode = kind === 'card' ? 'card' : 'crypto';
                    if (cryptomusInvoiceController) {
                        cryptomusInvoiceController.abort();
                    }
                    cryptomusInvoiceController = new AbortController();

                    setCryptomusLoading(true, mode);
                    setStatus(mode === 'card' ? 'Creating card invoice…' : 'Creating crypto invoice…', 'neutral');

                    try {
                        const res = await fetch('/api/cryptomus/invoice', {
                            method: 'POST',
                            signal: cryptomusInvoiceController.signal,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                type: checkoutContext.type,
                                id: checkoutContext.id,
                                bundle: checkoutContext.bundle,
                                package_type: checkoutContext.package_type,
                                kind: mode
                            })
                        });

                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            setStatus(json.message || 'Failed to create crypto invoice.', 'error');
                            setCryptomusLoading(false, mode);
                            cryptomusInvoiceController = null;
                            return;
                        }

                        if (!json.url) {
                            setStatus('Cryptomus did not return a payment URL.', 'error');
                            setCryptomusLoading(false, mode);
                            cryptomusInvoiceController = null;
                            return;
                        }

                        setStatus('Redirecting to Cryptomus…', 'neutral');
                        window.location.href = json.url;
                    } catch (e) {
                        if (e && e.name === 'AbortError') {
                            resetPaymentUi();
                            return;
                        }
                        setStatus(`Failed to start crypto payment. ${e && e.message ? e.message : ''}`.trim(), 'error');
                        setCryptomusLoading(false, mode);
                    } finally {
                        cryptomusInvoiceController = null;
                    }
                };

                payWithCardBtn.addEventListener('click', startPaystack);
                payWithCryptoBtn.addEventListener('click', () => startCryptomus('crypto'));
            })();
        </script>
    </body>
 </html>
