@php
    $user = Auth::user();
    $fullName = trim((string) ($user->name ?? ''));
    $initials = collect(preg_split('/\s+/', $fullName) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->implode('');
    $initials = mb_strtoupper($initials ?: 'U');
    $firstName = \Illuminate\Support\Str::title(mb_strtolower(\Illuminate\Support\Str::before($fullName.' ', ' '))) ?: 'Account';

    // Styled with namespaced .scn-* classes (public/css/spacechip-ui.css) so
    // page-level CSS such as the dashboard's `.hidden` rule cannot leak in.
    $items = [
        [
            'label' => 'eSIMs',
            'href' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => '<path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><rect x="8.5" y="11" width="7" height="6" rx="1.2"/>',
        ],
        [
            'label' => 'Numbers',
            'href' => route('dashboard.virtual.index'),
            'active' => request()->routeIs('dashboard.virtual.*'),
            'icon' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        ],
        [
            'label' => 'Account',
            'href' => route('profile.edit'),
            'active' => request()->routeIs('profile.*'),
            'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        ],
    ];

    if ($user?->isAdmin()) {
        $items[] = [
            'label' => 'Admin',
            'href' => route('admin.dashboard'),
            'active' => request()->routeIs('admin.*'),
            'icon' => '<path d="M12 3l7 3.5v5.5c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6.5L12 3Z"/><path d="m9 12 2 2 4-4"/>',
        ];
    }

    $svg = fn (string $paths) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$paths.'</svg>';
@endphp

<nav class="scn-nav" aria-label="Main">
    <div class="scn-bar">
        <a href="{{ route('dashboard') }}" class="scn-brand" aria-label="spacechip home">
            @include('partials.brand-wordmark', ['class' => 'scn-wordmark'])
        </a>

        <div class="scn-tabs">
            @foreach ($items as $item)
                @continue($item['label'] === 'Account')
                <a href="{{ $item['href'] }}" class="scn-tab {{ $item['active'] ? 'is-active' : '' }}" @if ($item['active']) aria-current="page" @endif>
                    {!! $svg($item['icon']) !!}
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <x-theme-flip class="scn-flip" />

        <div class="scn-account" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
            <button type="button" class="scn-account-btn" @click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Account menu">
                <span class="scn-avatar">{{ $initials }}</span>
                <span class="scn-account-name">{{ $firstName }}</span>
                <svg class="scn-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </button>

            <div class="scn-scrim" x-cloak x-show="open" x-transition.opacity @click="open = false"></div>
            <div class="scn-menu" role="menu" x-cloak x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                <div class="scn-menu-head">
                    <span class="scn-avatar">{{ $initials }}</span>
                    <div style="min-width:0">
                        <div class="scn-menu-name">{{ $fullName }}</div>
                        <div class="scn-menu-email">{{ $user->email }}</div>
                    </div>
                </div>

                @foreach ($items as $item)
                    <a href="{{ $item['href'] }}" role="menuitem" class="{{ $item['active'] ? 'is-active' : '' }}">
                        {!! $svg($item['icon']) !!}
                        {{ $item['label'] === 'Account' ? 'Account & security' : $item['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('dashboard.virtual.my') }}" role="menuitem">
                    {!! $svg('<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>') !!}
                    My numbers &amp; inbox
                </a>
                <a href="{{ route('help') }}" role="menuitem">
                    {!! $svg('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7M12 17h.01"/>') !!}
                    Help center
                </a>

                <div class="scn-sep"></div>

                <div class="scn-theme">
                    <span>Theme</span>
                    <x-theme-toggle compact />
                </div>

                <div class="scn-sep"></div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" role="menuitem" class="scn-signout">
                        {!! $svg('<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H4"/>') !!}
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<nav class="scn-tabbar" aria-label="Sections">
    @foreach ($items as $item)
        <a href="{{ $item['href'] }}" class="{{ $item['active'] ? 'is-active' : '' }}" @if ($item['active']) aria-current="page" @endif>
            {!! $svg($item['icon']) !!}
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
