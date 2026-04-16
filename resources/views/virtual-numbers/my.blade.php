<x-app-layout>
    <style>
        :root{--primary:#f27457;--secondary:#145454}
        .wrap{max-width:1100px;margin:0 auto;padding:34px 16px 64px}
        .head{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:16px}
        .title{font-weight:950;letter-spacing:-.02em;color:#0b1a1a;font-size:28px}
        .sub{color:rgba(15,31,31,.62);font-weight:650;margin-top:6px}
        .glass{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);border-radius:22px}
        .panel{padding:16px}
        .row{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
        .btn{padding:10px 14px;border-radius:14px;font-weight:850;border:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.65);color:rgba(15,31,31,.72);text-decoration:none;display:inline-flex;align-items:center;gap:8px}
        .btn-danger{border-color:rgba(242,116,87,.28);color:rgba(242,116,87,.92);background:rgba(255,255,255,.6)}
        .grid{display:grid;grid-template-columns:1fr;gap:12px;margin-top:14px}
        .card{padding:14px;border-radius:20px;border:1px solid rgba(15,31,31,.08);background:rgba(255,255,255,.6);display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        .pn{font-weight:950;color:#0b1a1a}
        .meta{font-size:13px;color:rgba(15,31,31,.62);font-weight:700;margin-top:6px}
        .tag{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:9999px;border:1px solid rgba(15,31,31,.10);font-weight:900;font-size:11px;color:rgba(15,31,31,.72);background:rgba(255,255,255,.6)}
        .tag.good{border-color:rgba(20,84,84,.22);color:rgba(20,84,84,.92)}
        .tag.bad{border-color:rgba(242,116,87,.28);color:rgba(242,116,87,.92)}
    </style>

    <div class="wrap">
        <div class="head">
            <div>
                <div class="title">My Numbers</div>
                <div class="sub">Manage your active virtual numbers and billing.</div>
            </div>
            <div class="row">
                <a class="btn" href="{{ $indexUrl ?? route('dashboard.virtual.index') }}">Buy a Number</a>
            </div>
        </div>

        <div class="glass panel">
            <div style="font-weight:950;color:#0b1a1a">Subscriptions</div>
            <div class="grid" id="subsGrid">
                @forelse($subs as $s)
                    <div class="card" data-sub-id="{{ $s->id }}">
                        <div>
                            <div class="pn">{{ $s->phone_number ?: 'Pending provisioning' }}</div>
                            <div class="meta">
                                {{ strtoupper((string) ($s->country_iso ?? $s->product?->country_iso ?? '')) }}
                                · {{ $s->product?->label ?: 'Virtual Number' }}
                                · {{ strtoupper($s->currency) }} {{ number_format(((int) $s->monthly_amount_minor)/100, 2) }}/mo
                            </div>
                            <div class="meta">
                                Next renewal: {{ $s->current_period_end ? $s->current_period_end->toDateTimeString() : '—' }}
                            </div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-end;min-width:180px">
                            @php
                                $st = (string) $s->status;
                                $tag = $st === 'active' ? 'good' : ($st === 'past_due' ? 'bad' : '');
                            @endphp
                            <span class="tag {{ $tag }}">{{ $st }}</span>
                            <button class="btn btn-danger cancelBtn" type="button" {{ $st === 'canceled' ? 'disabled' : '' }}>Cancel</button>
                        </div>
                    </div>
                @empty
                    <div class="meta">No subscriptions yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            document.querySelectorAll('.cancelBtn').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const card = btn.closest('[data-sub-id]');
                    const id = card ? card.getAttribute('data-sub-id') : '';
                    if (!id) return;
                    btn.disabled = true;
                    try {
                        const res = await fetch(`/api/virtual-numbers/${encodeURIComponent(id)}/cancel`, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
                        });
                        if (!res.ok) {
                            btn.disabled = false;
                            return;
                        }
                        window.location.reload();
                    } catch (e) {
                        btn.disabled = false;
                    }
                });
            });
        })();
    </script>
</x-app-layout>
