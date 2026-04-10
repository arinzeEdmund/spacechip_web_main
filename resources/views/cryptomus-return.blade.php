<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Crypto Payment - {{ config('app.name', 'spacechip') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif;--primary:#f27457;--secondary:#145454}
            *{box-sizing:border-box}
            body{margin:0;font-family:var(--font-sans);min-height:100vh;background:
                radial-gradient(900px 520px at 12% 14%, rgba(242,116,87,.32) 0%, rgba(242,116,87,0) 60%),
                radial-gradient(980px 560px at 88% 18%, rgba(20,84,84,.26) 0%, rgba(20,84,84,0) 62%),
                linear-gradient(180deg, #F7F7F8 0%, #F5F6F8 60%, #F7F7F8 100%);color:#0b1a1a}
            .container{max-width:760px;margin:0 auto;padding:24px}
            .card{margin-top:26px;padding:22px;border-radius:22px;background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 16px 40px rgba(15,31,31,.08)}
            .logo{height:36px;width:36px;border-radius:12px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900}
            .spin{height:16px;width:16px;border-radius:9999px;border:2px solid rgba(15,31,31,.12);border-top-color:rgba(242,116,87,.92);animation:spin 1s linear infinite}
            @keyframes spin{to{transform:rotate(360deg)}}
            .row{display:flex;gap:10px;align-items:center}
            .title{font-size:20px;font-weight:900}
            .sub{margin-top:8px;color:rgba(15,31,31,.62);line-height:1.5}
            .pill{margin-top:14px;padding:10px 12px;border-radius:12px;background:rgba(20,84,84,.06);border:1px solid rgba(20,84,84,.10);font-weight:750}
            .btn{margin-top:16px;display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;background:#fff;border:1px solid rgba(20,84,84,.14);font-weight:800;color:rgba(20,84,84,.92);text-decoration:none}
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
                <div class="logo">SC</div>
                <div style="font-weight:900;color:rgba(20,84,84,.92)">spacechip</div>
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
                        const res = await fetch(`/api/cryptomus/status?order_id=${encodeURIComponent(orderId)}`, { headers: { 'Accept': 'application/json' } });
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
    </body>
</html>
