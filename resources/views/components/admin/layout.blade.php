@props(['title' => 'Admin', 'subtitle' => null, 'actions' => null])
@php
    $nav = [
        'Overview' => [
            ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
        ],
        'Customers' => [
            ['route' => 'admin.users.index', 'match' => 'admin.users.*', 'icon' => 'users', 'label' => 'Users'],
            ['route' => 'admin.logins', 'match' => 'admin.logins', 'icon' => 'key-round', 'label' => 'Login activity', 'badge' => $navCounts['failed_logins'] ?? 0, 'badgeTone' => 'warning'],
        ],
        'Commerce' => [
            ['route' => 'admin.payments.index', 'match' => 'admin.payments.*', 'icon' => 'receipt', 'label' => 'Payments', 'badge' => $navCounts['attention'] ?? 0, 'badgeTone' => 'danger'],
            ['route' => 'admin.wallet', 'match' => 'admin.wallet', 'icon' => 'wallet', 'label' => 'Wallet ledger'],
            ['route' => 'admin.reports', 'match' => 'admin.reports*', 'icon' => 'file-spreadsheet', 'label' => 'Sales export'],
        ],
        'Products' => [
            ['route' => 'admin.esims', 'match' => 'admin.esims', 'icon' => 'cpu', 'label' => 'eSIM orders'],
            ['route' => 'admin.numbers.virtual', 'match' => 'admin.numbers.virtual*', 'icon' => 'phone', 'label' => 'Virtual numbers', 'badge' => $navCounts['past_due'] ?? 0, 'badgeTone' => 'warning'],
            ['route' => 'admin.numbers.social', 'match' => 'admin.numbers.social*', 'icon' => 'message-square-text', 'label' => 'Social numbers'],
        ],
        'Platform' => [
            ['route' => 'admin.providers', 'match' => 'admin.providers', 'icon' => 'server', 'label' => 'Providers'],
            ['route' => 'admin.settings', 'match' => 'admin.settings', 'icon' => 'sliders-horizontal', 'label' => 'Settings'],
            ['route' => 'admin.system', 'match' => 'admin.system', 'icon' => 'activity', 'label' => 'System & logs'],
            ['route' => 'admin.audit', 'match' => 'admin.audit', 'icon' => 'scroll-text', 'label' => 'Audit trail'],
            ['route' => 'admin.privacy-requests', 'match' => 'admin.privacy-requests*', 'icon' => 'shield-check', 'label' => 'Privacy requests', 'badge' => $navCounts['privacy_open'] ?? 0, 'badgeTone' => 'warning'],
        ],
    ];
    $section = collect($nav)->filter(fn ($items) => collect($items)->contains(fn ($i) => request()->routeIs($i['match'])))->keys()->first() ?? 'Admin';
    $me = auth()->user();
    $current = collect($nav)->flatten(1)->first(fn ($i) => request()->routeIs($i['match']));
    $live = app()->isProduction();
    $icon = fn (string $name) => '<i data-lucide="'.$name.'"></i>';
    $initials = collect(explode(' ', (string) $me?->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name', 'spacechip') }} Admin</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    @include('partials.theme-script')
    @foreach (['palette', 'theme', 'utilities', 'admin'] as $sheet)
        <link rel="stylesheet" href="{{ asset("css/spacechip-$sheet.css") }}?v={{ @filemtime(public_path("css/spacechip-$sheet.css")) }}">
    @endforeach
    @include('partials.tailwind')
    <script src="https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="ad font-sans">
    @include('partials.skip-link')
    {{-- Sidebar --}}
    <aside id="sidebar" class="ad-side" aria-label="Admin navigation">
        <a href="{{ route('admin.dashboard') }}" class="ad-brand">
            <span>@include('partials.brand-wordmark', ['class' => 'ad-wordmark'])<small>Admin console</small></span>
        </a>
        <nav class="ad-nav">
            @foreach ($nav as $group => $items)
                <div class="ad-nav-group">{{ $group }}</div>
                @foreach ($items as $item)
                    @php $active = request()->routeIs($item['match']); @endphp
                    <a href="{{ route($item['route']) }}" @class(['is-active' => $active]) @if ($active) aria-current="page" @endif>
                        {!! $icon($item['icon']) !!}
                        <span>{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <em class="ad-count ad-count--{{ $item['badgeTone'] ?? 'warning' }}" style="font-style:normal">{{ $item['badge'] }}</em>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>
        <div class="ad-side-foot">
            <x-theme-toggle />
            <div class="ad-me">
                <span class="ad-avatar ad-avatar--md ad-avatar--brand">{{ $initials ?: 'A' }}</span>
                <div><b>{{ $me?->name }}</b><small>{{ $me?->email }}</small></div>
            </div>
            <a href="{{ route('admin.security') }}" class="ad-back">{!! $icon('shield-check') !!} My security</a>
            <form method="POST" action="{{ route('admin.logout') }}" style="margin:0">@csrf<button type="submit" class="ad-back" style="background:none;border:0;cursor:pointer;width:100%;text-align:left">{!! $icon('log-out') !!} Sign out</button></form>
            <a href="{{ route('dashboard') }}" class="ad-back">{!! $icon('arrow-up-left') !!} Back to app</a>
        </div>
    </aside>
    <div id="sidebarScrim" class="ad-scrim" onclick="toggleSidebar(false)"></div>

    <div class="ad-main">
        <header class="ad-top">
            <button type="button" class="ad-burger" onclick="toggleSidebar(true)" aria-label="Open navigation">{!! $icon('menu') !!}</button>
            <form action="{{ route('admin.users.index') }}" class="ad-search" role="search">
                {!! $icon('search') !!}
                <input name="q" value="{{ request()->routeIs('admin.users.index') ? request('q') : '' }}" placeholder="Search customers by name, email, phone or ID" aria-label="Search customers">
                <kbd>⌘K</kbd>
            </form>
            <div class="ad-top-end">
                <span class="ad-env {{ $live ? 'ad-env--live' : 'ad-env--dev' }}"><i></i>{{ ucfirst(app()->environment()) }}</span>
                <x-theme-flip />
            </div>
        </header>

        <main id="main" tabindex="-1" class="ad-page">
            <div class="ad-head">
                <div>
                    <div class="ad-crumbs"><span>Admin</span>{!! $icon('chevron-right') !!}<span>{{ $section }}</span>@if ($current && $current['label'] !== $title){!! $icon('chevron-right') !!}<span>{{ $current['label'] }}</span>@endif</div>
                    <h1 class="ad-title">{{ $title }}</h1>
                    @if ($subtitle)<p class="ad-sub">{{ $subtitle }}</p>@endif
                </div>
                @if ($actions)<div class="ad-actions">{{ $actions }}</div>@endif
            </div>

            @foreach (['status' => 'ok', 'error' => 'err'] as $key => $tone)
                @if (session($key))
                    <div data-flash class="ad-flash ad-flash--{{ $tone }}" role="status">
                        {!! $icon($tone === 'ok' ? 'circle-check' : 'circle-alert') !!}
                        <span>{{ session($key) }}</span>
                        <button type="button" onclick="this.parentElement.remove()" aria-label="Dismiss">{!! $icon('x') !!}</button>
                    </div>
                @endif
            @endforeach
            @if ($errors->any())
                <div class="ad-flash ad-flash--err" role="alert">{!! $icon('circle-alert') !!}<span>{{ $errors->first() }}</span></div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Shared confirm dialog: any form with data-confirm="message" asks first. --}}
    <dialog id="confirmDialog" style="width:min(92vw,420px)">
        <div style="padding:24px">
            <span class="ad-tile ad-tile--danger" style="width:40px;height:40px;border-radius:12px;margin-bottom:14px">{!! $icon('shield-alert') !!}</span>
            <h3 style="margin:0;font-size:16px;font-weight:650">Please confirm</h3>
            <p id="confirmText" style="margin:6px 0 0;font-size:13.5px;line-height:1.55;color:var(--muted)"></p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:14px 24px;border-top:1px solid var(--border);background:var(--surface-2);border-radius:0 0 18px 18px">
            <button type="button" class="ad-btn ad-btn--ghost" onclick="confirmDialog.close()">Cancel</button>
            <button type="button" id="confirmOk" class="ad-btn ad-btn--primary">Confirm</button>
        </div>
    </dialog>

<script>
    lucide.createIcons();
    function toggleSidebar(open) {
        document.getElementById('sidebar').classList.toggle('is-open', open);
        document.getElementById('sidebarScrim').classList.toggle('is-open', open);
    }
    document.addEventListener('keydown', (e) => {
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);
        if ((e.key === '/' && !typing) || ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k')) {
            e.preventDefault(); document.querySelector('.ad-search input').focus();
        }
        if (e.key === 'Escape') toggleSidebar(false);
    });
    const confirmDialog = document.getElementById('confirmDialog');
    let pendingForm = null;
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!form.dataset.confirm || form.dataset.confirmed) return;
        e.preventDefault(); pendingForm = form;
        document.getElementById('confirmText').textContent = form.dataset.confirm;
        confirmDialog.showModal();
    });
    document.getElementById('confirmOk').addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1'; confirmDialog.close(); pendingForm.requestSubmit();
    });
    document.querySelectorAll('[data-open-dialog]').forEach((b) => b.addEventListener('click', () => document.getElementById(b.dataset.openDialog).showModal()));
    document.querySelectorAll('[data-close-dialog]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));
    setTimeout(() => document.querySelectorAll('[data-flash]').forEach((el) => { el.style.opacity = '0'; }), 6000);
</script>
@stack('scripts')
</body>
</html>
