@php
    $user = Auth::user();
    $initials = mb_strtoupper(collect(preg_split('/\s+/', trim((string) $user->name)) ?: [])->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('') ?: 'U');
    $walletMinor = (int) ($user->wallet_balance_minor ?? 0);
    $activeNumbers = \App\Models\VirtualNumberSubscription::where('user_id', $user->id)->where('status', 'active')->count();
    $svg = fn ($p) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    $sections = [
        'profile' => ['Profile', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
        'security' => ['Password & security', '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'],
        'appearance' => ['Appearance', '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18Z" fill="currentColor"/>'],
        'danger' => ['Delete account', '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>'],
    ];
@endphp

<x-app-layout>
    <style>
        .ac-hero{position:relative;overflow:hidden;display:flex;flex-wrap:wrap;align-items:center;gap:24px;padding:28px;margin-bottom:28px;background:radial-gradient(520px 220px at 0% 0%,rgb(var(--accent-rgb) / .14),transparent 65%),var(--surface)}
        .ac-avatar{display:grid;flex:0 0 auto;place-items:center;width:80px;height:80px;border-radius:24px;font-size:28px;font-weight:700;letter-spacing:.01em;color:#fff;background:var(--accent-fill);box-shadow:0 0 0 4px var(--surface),0 0 0 5px var(--border),0 18px 40px -14px rgb(var(--accent-rgb))}
        .ac-id{flex:1 1 260px;min-width:0}
        .ac-id h1{margin:0;font-size:clamp(22px,3vw,28px);font-weight:700;letter-spacing:-.025em;color:var(--text);overflow:hidden;text-overflow:ellipsis}
        .ac-id p{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:6px 0 0;font-size:14px;color:var(--muted)}
        .ac-facts{display:grid;grid-template-columns:repeat(3,auto);gap:0;margin:0;border:1px solid var(--border);border-radius:16px;background:var(--surface-2)}
        .ac-facts > div{padding:12px 18px;border-left:1px solid var(--border)}
        .ac-facts > div:first-child{border-left:0}
        .ac-facts dt{font-size:12px;color:var(--muted)}
        .ac-facts dd{margin:4px 0 0;font-size:16px;font-weight:650;color:var(--text);font-variant-numeric:tabular-nums;white-space:nowrap}
        .ac-layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:28px;align-items:start}
        .ac-nav{position:sticky;top:88px;display:grid;gap:2px}
        .ac-nav a{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:10px;font-size:14px;font-weight:500;color:var(--muted);text-decoration:none;transition:color .15s,background-color .15s}
        .ac-nav a svg{width:17px;height:17px;flex:0 0 auto}
        .ac-nav a:hover{color:var(--text);background:var(--surface-2)}
        .ac-nav a.is-active{color:var(--text);background:var(--surface);box-shadow:0 0 0 1px var(--border)}
        .ac-nav a.is-active svg{color:var(--accent-text)}
        .ac-nav a[href="#danger"]:hover,.ac-nav a[href="#danger"].is-active{color:var(--danger)}
        .ac-nav a[href="#danger"].is-active svg{color:var(--danger)}
        .ac-stack{display:grid;gap:20px}
        .ac-card{scroll-margin-top:84px}
        .ac-card .ui-card__head{align-items:flex-start;justify-content:flex-start;gap:14px}
        .ac-card .ui-card__head h2{margin:0;font-size:16px;font-weight:650;color:var(--text)}
        .ac-card .ui-card__head p{margin:3px 0 0;font-size:13.5px;line-height:1.5;color:var(--muted)}
        .ac-card .ui-card__body{padding:22px 20px}
        .ac-card .ui-card__body form > div:first-of-type{margin-top:0}
        .ac-card--danger{border-color:rgb(var(--danger-rgb) / .3)}
        .ac-card--danger .ui-icon-tile{color:var(--danger);background:var(--danger-soft)}
        .ac-theme{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
        .ac-theme button{display:flex;flex-direction:column;gap:10px;padding:10px;border:1px solid var(--border);border-radius:14px;background:var(--surface);color:var(--text);font:inherit;font-size:13.5px;font-weight:600;text-align:left;cursor:pointer;transition:border-color .15s,box-shadow .15s}
        .ac-theme button:hover{border-color:var(--border-strong)}
        .ac-theme button[aria-checked="true"]{border-color:var(--accent-strong);box-shadow:0 0 0 3px var(--accent-soft)}
        .ac-swatch{height:64px;border-radius:9px;border:1px solid var(--border);overflow:hidden;display:grid;grid-template-rows:14px 1fr;gap:6px;padding:6px}
        .ac-swatch i{display:block;border-radius:4px}
        .ac-swatch--light{background:#f7f8f7}.ac-swatch--light i{background:#e2e5e2}.ac-swatch--light i:last-child{background:#fff;box-shadow:inset 0 0 0 1px #e2e5e2}
        .ac-swatch--dark{background:#000}.ac-swatch--dark i{background:#262626}.ac-swatch--dark i:last-child{background:#121212;box-shadow:inset 0 0 0 1px #282828}
        .ac-swatch--system{background:linear-gradient(90deg,#f7f8f7 50%,#000 50%)}.ac-swatch--system i{background:linear-gradient(90deg,#e2e5e2 50%,#262626 50%)}.ac-swatch--system i:last-child{background:linear-gradient(90deg,#fff 50%,#121212 50%)}
        .ac-theme span{display:flex;align-items:center;justify-content:space-between}
        .ac-theme span::after{content:"";width:16px;height:16px;border-radius:50%;border:1.5px solid var(--border-strong)}
        .ac-theme button[aria-checked="true"] span::after{border:5px solid var(--accent-strong)}
        @media(max-width:900px){
            .ac-layout{grid-template-columns:1fr}
            .ac-nav{position:sticky;top:56px;z-index:5;display:flex;overflow-x:auto;margin:0 -20px;padding:8px 20px;background:rgb(var(--bg-rgb) / .9);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);scrollbar-width:none}
            .ac-nav a{flex:0 0 auto}
            .ac-facts{width:100%;grid-template-columns:repeat(3,minmax(0,1fr))}
        }
        @media(max-width:520px){
            .ac-hero{padding:20px;gap:16px}
            .ac-avatar{width:64px;height:64px;border-radius:20px;font-size:22px}
            .ac-facts > div{padding:10px 12px}
            .ac-facts dd{font-size:14.5px}
            .ac-theme{grid-template-columns:1fr 1fr 1fr;gap:8px}
        }
    </style>

    <div class="pg">
        <section class="ui-card ac-hero">
            <div class="ac-avatar" aria-hidden="true">{{ $initials }}</div>
            <div class="ac-id">
                <p class="pg-eyebrow" style="margin:0 0 4px">Account</p>
                <h1>{{ $user->name }}</h1>
                <p>
                    <span>{{ $user->email }}</span>
                    @if ($user->hasVerifiedEmail())
                        <span class="ui-pill ui-pill--success">{!! $svg('<path d="m5 12 5 5L20 7"/>') !!}Verified</span>
                    @else
                        <span class="ui-pill ui-pill--warning">Unverified</span>
                    @endif
                </p>
            </div>
            <dl class="ac-facts">
                <div><dt>Wallet</dt><dd>${{ number_format($walletMinor / 100, 2) }}</dd></div>
                <div><dt>Active numbers</dt><dd>{{ $activeNumbers }}</dd></div>
                <div><dt>Member since</dt><dd>{{ $user->created_at?->format('M Y') ?? '—' }}</dd></div>
            </dl>
        </section>

        <div class="ac-layout">
            <nav class="ac-nav" aria-label="Account sections" id="acNav">
                @foreach ($sections as $key => [$label, $icon])
                    <a href="#{{ $key }}" @class(['is-active' => $loop->first])>{!! $svg($icon) !!}{{ $label }}</a>
                @endforeach
            </nav>

            <div class="ac-stack">
                <section class="ui-card ac-card" id="profile">
                    <div class="ui-card__head">
                        <span class="ui-icon-tile">{!! $svg($sections['profile'][1]) !!}</span>
                        <div><h2>Profile</h2><p>Your name and the email we use for receipts, eSIM QR codes and sign-in.</p></div>
                    </div>
                    <div class="ui-card__body">@include('profile.partials.update-profile-information-form')</div>
                </section>

                <section class="ui-card ac-card" id="security">
                    <div class="ui-card__head">
                        <span class="ui-icon-tile">{!! $svg($sections['security'][1]) !!}</span>
                        <div><h2>Password &amp; security</h2><p>Use a long, unique password. You’ll stay signed in on this device.</p></div>
                    </div>
                    <div class="ui-card__body">@include('profile.partials.update-password-form')</div>
                </section>

                <section class="ui-card ac-card" id="appearance">
                    <div class="ui-card__head">
                        <span class="ui-icon-tile">{!! $svg($sections['appearance'][1]) !!}</span>
                        <div><h2>Appearance</h2><p>Choose light or dark, or follow your device setting. Saved on this browser.</p></div>
                    </div>
                    <div class="ui-card__body">
                        <div class="ac-theme" role="radiogroup" aria-label="Theme">
                            @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'System'] as $value => $label)
                                <button type="button" role="radio" aria-checked="false" data-theme-value="{{ $value }}">
                                    <div class="ac-swatch ac-swatch--{{ $value }}" aria-hidden="true"><i></i><i></i></div>
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="ui-card ac-card ac-card--danger" id="danger">
                    <div class="ui-card__head">
                        <span class="ui-icon-tile">{!! $svg($sections['danger'][1]) !!}</span>
                        <div><h2>Delete account</h2><p>Permanently remove your account, wallet history and purchases. This can’t be undone.</p></div>
                    </div>
                    <div class="ui-card__body">@include('profile.partials.delete-user-form')</div>
                </section>
            </div>
        </div>
    </div>

    <script>
        (() => {
            // Highlight the section in view.
            const links = Array.from(document.querySelectorAll('#acNav a'));
            const byId = Object.fromEntries(links.map((a) => [a.getAttribute('href').slice(1), a]));
            const io = new IntersectionObserver((entries) => {
                entries.forEach((e) => {
                    if (!e.isIntersecting) return;
                    links.forEach((a) => a.classList.toggle('is-active', a === byId[e.target.id]));
                });
            }, { rootMargin: '-35% 0px -60% 0px' });
            Object.keys(byId).forEach((id) => { const el = document.getElementById(id); if (el) io.observe(el); });
        })();
    </script>
</x-app-layout>
