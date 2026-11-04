<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'spacechip') }}">
        <title>Crypto Payment - {{ config('app.name', 'spacechip') }}</title>
        @include('partials.head-assets')
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif}
            *{box-sizing:border-box}
            body{margin:0;font-family:var(--font-sans);min-height:100vh;background:radial-gradient(900px 520px at 10% -10%, rgb(var(--accent-rgb) / .10) 0%, transparent 60%),
                var(--bg);color:var(--text)}
            .container{max-width:760px;margin:0 auto;padding:24px}
            .card{margin-top:26px;padding:22px;border-radius:22px;background:rgb(var(--surface-rgb) / .75);backdrop-filter:blur(12px);border:1px solid rgb(var(--ink-rgb) / .12);box-shadow:0 16px 40px rgba(0,0,0,.08)}
            .footer-wordmark{height:26px;width:auto;display:block}
            .spin{height:16px;width:16px;border-radius:9999px;border:2px solid rgb(var(--ink-rgb) / .12);border-top-color:rgb(var(--accent-rgb) / .92);animation:spin 1s linear infinite}
            @keyframes spin{to{transform:rotate(360deg)}}
            .row{display:flex;gap:10px;align-items:center}
            .title{font-size:20px;font-weight:900}
            .sub{margin-top:8px;color:rgb(var(--ink-rgb) / .62);line-height:1.5}
            .pill{margin-top:14px;padding:10px 12px;border-radius:12px;background:rgb(var(--ink-rgb) / .06);border:1px solid rgb(var(--ink-rgb) / .10);font-weight:750}
            .btn{margin-top:16px;display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;background:var(--surface);border:1px solid rgb(var(--ink-rgb) / .14);font-weight:800;color:rgb(var(--accent-text-rgb) / .92);text-decoration:none}
            @media(max-width:640px){
                .container{padding:16px}
                .card{padding:18px;border-radius:18px}
                .title{font-size:18px}
                .row{flex-wrap:wrap}
                .pill{word-break:break-word}
                .btn{width:100%}
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row">
                <div style="color:var(--text)">@include('partials.brand-wordmark', ['class' => 'footer-wordmark'])</div>
            </div>

            <div class="card">
                <div class="row">
                    <div class="spin"></div>
                    <div class="title">Confirming your crypto payment…</div>
                </div>
                <div class="sub" id="statusText">We’re checking the payment status and preparing your eSIM.</div>
                <div class="pill">Order ID: {{ $orderId }}</div>
                <a class="btn" href="{{ route('dashboard') }}">Back to Dashboard</a>
            </div>
        </div>

        <script>
            (() => {
                const orderId = @json((string) $orderId);
                const el = document.getElementById('statusText');
                const dashUrl = @json(route('dashboard'));

                const poll = async () => {
                    if (!orderId) return;
                    try {
                        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        const res = await fetch(`/api/cryptomus/status`, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf
                            },
                            body: JSON.stringify({ order_id: orderId })
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            el.textContent = json.message || 'Unable to check payment status.';
                            return;
                        }

                        if (json.status === 'paid' || json.status === 'paid_over') {
                            if (json.fulfilled && json.synced) {
                                el.textContent = 'Payment confirmed. Your eSIM has been fulfilled and sent to your email.';
                                window.setTimeout(() => {
                                    window.location.href = dashUrl;
                                }, 1500);
                                return;
                            }
                            if (json.fulfilled && !json.synced) {
                                el.textContent = 'Payment confirmed. Syncing eSIM details…';
                                window.setTimeout(poll, 5000);
                                return;
                            }
                            if (json.fulfillment_error) {
                                el.textContent = `Payment confirmed. Fulfillment retrying… (${json.fulfillment_error})`;
                            } else {
                                el.textContent = 'Payment confirmed. Preparing your eSIM…';
                            }
                            window.setTimeout(poll, 5000);
                            return;
                        }

                        if (json.is_final && !json.fulfilled) {
                            el.textContent = `Payment finished with status: ${json.status || 'unknown'}.`;
                            return;
                        }

                        el.textContent = `Payment status: ${json.status || 'processing'}…`;
                        window.setTimeout(poll, 3000);
                    } catch (e) {
                        el.textContent = 'Unable to check payment status.';
                    }
                };

                poll();
            })();
        </script>
        <script src="{{ asset('pwa-install.js') }}" defer></script>
    </body>
</html>
