<x-app-layout>
    <style>
        :root{--primary:#f27457;--secondary:#145454}
        .wrap{max-width:980px;margin:0 auto;padding:34px 16px 64px}
        .head{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:16px}
        .title{font-weight:950;letter-spacing:-.02em;color:#0b1a1a;font-size:28px}
        .sub{color:rgba(15,31,31,.62);font-weight:650;margin-top:6px}
        .glass{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);border-radius:22px}
        .grid{display:grid;grid-template-columns:1fr;gap:14px}
        @media(min-width:900px){.grid{grid-template-columns:1.2fr .8fr}}
        .panel{padding:18px}
        .btn{padding:12px 16px;border-radius:16px;font-weight:900;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.65);color:rgba(15,31,31,.72);text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%}
        .btn-primary{background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;border:none;box-shadow:0 10px 28px rgba(20,84,84,.14)}
        .btn:disabled{opacity:.6;cursor:not-allowed}
        .kv{display:flex;justify-content:space-between;gap:12px;margin-top:10px}
        .k{font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:rgba(15,31,31,.55)}
        .v{font-size:14px;font-weight:900;color:#0b1a1a}
        .msg{margin-top:10px;font-size:13px;color:rgba(15,31,31,.62);font-weight:700}
    </style>

    <div class="wrap">
        <div class="head">
            <div>
                <div class="title">Virtual Number Checkout</div>
                <div class="sub">Monthly subscription. Cancel anytime.</div>
            </div>
            <div style="display:flex;gap:10px;align-items:center">
                <a class="btn" style="width:auto" href="{{ $indexUrl ?? route('dashboard.virtual.index') }}">Back</a>
                <a class="btn" style="width:auto" href="{{ $myUrl ?? route('dashboard.virtual.my') }}">My Numbers</a>
            </div>
        </div>

        <div class="grid">
            <div class="glass panel">
                <div style="font-weight:950;color:#0b1a1a">Selected number</div>
                <div class="kv"><div class="k">Number</div><div class="v">{{ $phoneNumber }}</div></div>
                <div class="kv"><div class="k">Country</div><div class="v">{{ strtoupper($product->country_iso) }}</div></div>
                <div class="kv"><div class="k">Type</div><div class="v">{{ $numberType ?: '—' }}</div></div>
                <div class="kv"><div class="k">Plan</div><div class="v">{{ $product->label }}</div></div>
                <div class="kv"><div class="k">Price</div><div class="v">{{ $priceFormatted }}/mo</div></div>
                <div class="msg">The first payment activates the number immediately.</div>
            </div>

            <div class="glass panel">
                <div style="font-weight:950;color:#0b1a1a">Pay with Card</div>
                <div class="msg">We’ll securely store the Paystack authorization for monthly renewals.</div>
                <div style="margin-top:14px">
                    <button id="subscribeBtn" class="btn btn-primary" type="button">Subscribe</button>
                    <button id="walletPayBtn" class="btn" type="button" style="margin-top:10px">Pay with Wallet</button>
                    <div id="walletInfo" class="msg"></div>
                    <div id="status" class="msg"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        (() => {
            const btn = document.getElementById('subscribeBtn');
            const walletBtn = document.getElementById('walletPayBtn');
            const walletInfoEl = document.getElementById('walletInfo');
            const statusEl = document.getElementById('status');
            const csrfToken = @json(csrf_token());
            const paystackKey = @json((string) (config('services.paystack.public_key') ?: env('PAYSTACK_PUBLIC_KEY')));
            const walletAmountMinor = @json((int) ($priceMinor ?? 0));

            const ctx = {
                product_id: @json((int) $product->id),
                phone_number: @json((string) $phoneNumber),
                number_type: @json((string) ($numberType ?? ''))
            };

            const setStatus = (t) => {
                if (!statusEl) return;
                statusEl.textContent = t || '';
            };

            const setWalletInfo = (t) => {
                if (!walletInfoEl) return;
                walletInfoEl.textContent = t || '';
            };

            const refreshWallet = async () => {
                setWalletInfo('Balance: Loading…');
                try {
                    const res = await fetch('/api/wallet/balance', { headers: { 'Accept': 'application/json' } });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.ok) {
                        setWalletInfo('Balance: Unavailable');
                        return null;
                    }
                    setWalletInfo(`Balance: ${String(json.balance_formatted || '$0.00')}`);
                    return json;
                } catch (e) {
                    setWalletInfo('Balance: Unavailable');
                    return null;
                }
            };

            const start = async () => {
                if (!paystackKey || !String(paystackKey).trim().startsWith('pk_')) {
                    setStatus('Paystack public key is not configured.');
                    return;
                }
                if (typeof window.PaystackPop === 'undefined') {
                    setStatus('Paystack did not load. Refresh and try again.');
                    return;
                }

                btn.disabled = true;
                setStatus('Initializing payment…');
                try {
                    const initRes = await fetch('/api/virtual-numbers/paystack/initialize', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(ctx)
                    });
                    const initJson = await initRes.json().catch(() => ({}));
                    if (!initRes.ok) {
                        setStatus(initJson.message || 'Initialization failed.');
                        btn.disabled = false;
                        return;
                    }

                    const reference = String(initJson.reference || '');
                    const accessCode = String(initJson.access_code || '');
                    const amount = Number(initJson.amount || 0);
                    const currency = String(initJson.currency || '').toUpperCase();
                    const email = String(initJson.email || '');
                    if (!reference || !accessCode || !email) {
                        setStatus('Initialization failed.');
                        btn.disabled = false;
                        return;
                    }

                    setStatus('Opening Paystack…');
                    let handler;
                    try {
                        handler = window.PaystackPop.setup({
                            key: paystackKey,
                            access_code: accessCode,
                            ref: reference,
                            email,
                            amount,
                            currency,
                            metadata: {
                                purpose: 'virtual_number_subscription',
                                product_id: ctx.product_id,
                                phone_number: ctx.phone_number,
                                number_type: ctx.number_type
                            },
                            callback: function (response) {
                                (async () => {
                                    setStatus('Verifying payment…');
                                    const verifyRes = await fetch('/api/virtual-numbers/paystack/verify', {
                                        method: 'POST',
                                        headers: {
                                            'Accept': 'application/json',
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken
                                        },
                                        body: JSON.stringify({ reference: response.reference })
                                    });
                                    const verifyJson = await verifyRes.json().catch(() => ({}));
                                    if (!verifyRes.ok) {
                                        setStatus(verifyJson.message || 'Verification failed.');
                                        btn.disabled = false;
                                        return;
                                    }

                                    setStatus('Subscription active. Redirecting…');
                                    window.location.href = @json(route('dashboard.virtual.my'));
                                })().catch((err) => {
                                    const msg = err && typeof err === 'object' && 'message' in err ? String(err.message || '') : '';
                                    setStatus(msg ? msg : 'Verification failed.');
                                    btn.disabled = false;
                                });
                            },
                            onClose: function () {
                                btn.disabled = false;
                                setStatus('');
                            }
                        });
                    } catch (err) {
                        const msg = err && typeof err === 'object' && 'message' in err ? String(err.message || '') : '';
                        setStatus(msg ? msg : 'Paystack could not open. Please try again.');
                        btn.disabled = false;
                        return;
                    }

                    try {
                        handler.openIframe();
                    } catch (err) {
                        const msg = err && typeof err === 'object' && 'message' in err ? String(err.message || '') : '';
                        setStatus(msg ? msg : 'Paystack could not open. Please try again.');
                        btn.disabled = false;
                        return;
                    }
                } catch (e) {
                    btn.disabled = false;
                    const msg = e && typeof e === 'object' && 'message' in e ? String(e.message || '') : '';
                    setStatus(msg ? msg : 'Payment failed. Try again.');
                }
            };

            if (btn) btn.addEventListener('click', start);

            if (walletBtn) {
                walletBtn.addEventListener('click', async () => {
                    walletBtn.disabled = true;
                    btn.disabled = true;
                    setStatus('');
                    const bal = await refreshWallet();
                    if (!bal) {
                        walletBtn.disabled = false;
                        btn.disabled = false;
                        return;
                    }
                    const balMinor = Number(bal.balance_minor || 0);
                    if (!walletAmountMinor || walletAmountMinor <= 0) {
                        setStatus('Invalid wallet amount.');
                        walletBtn.disabled = false;
                        btn.disabled = false;
                        return;
                    }
                    if (balMinor < walletAmountMinor) {
                        setStatus(`Insufficient wallet balance. You have ${String(bal.balance_formatted || '')}.`);
                        walletBtn.disabled = false;
                        btn.disabled = false;
                        return;
                    }
                    setStatus('Paying with wallet…');
                    try {
                        const res = await fetch('/api/wallet/pay/virtual-number', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(ctx)
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok || !json.ok) {
                            setStatus(json.message || 'Wallet payment failed.');
                            walletBtn.disabled = false;
                            btn.disabled = false;
                            await refreshWallet();
                            return;
                        }
                        setStatus('Subscription active. Redirecting…');
                        window.location.href = @json(route('dashboard.virtual.my'));
                    } catch (e) {
                        setStatus('Wallet payment failed. Try again.');
                        walletBtn.disabled = false;
                        btn.disabled = false;
                        await refreshWallet();
                    }
                });
            }

            refreshWallet();
        })();
    </script>
</x-app-layout>
