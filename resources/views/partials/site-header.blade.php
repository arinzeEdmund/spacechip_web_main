{{--
    Public site header (landing, catalogue, plan details, checkout, help pages, auth).
    Desktop: brand, links, theme switch, auth actions. Below 768px the links and
    auth actions move into a menu sheet. Styles: .sh-* in public/css/spacechip-ui.css.

    @include('partials.site-header', ['minimal' => true])  brand + theme only (auth screens)
--}}
@php
    $minimal = $minimal ?? false;
    $links = [
        ['label' => 'eSIM plans', 'href' => route('assets.index', ['tab' => 'countries']), 'active' => request()->routeIs('assets.*')],
        ['label' => 'eSIM guide', 'href' => route('esim.guide'), 'active' => request()->routeIs('esim.guide')],
        ['label' => 'Help center', 'href' => route('help'), 'active' => request()->routeIs('help')],
        ['label' => 'Contact', 'href' => route('contact'), 'active' => request()->routeIs('contact')],
    ];
@endphp
<header class="sh" data-sh>
    <div class="sh-bar">
        <a href="{{ route('home') }}" class="sh-brand" aria-label="spacechip home">
            @include('partials.brand-wordmark', ['class' => 'scn-wordmark'])
        </a>

        @unless ($minimal)
            <nav class="sh-links" aria-label="Site">
                @foreach ($links as $link)
                    <a href="{{ $link['href'] }}" @class(['is-active' => $link['active']]) @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endunless

        <div class="sh-actions">
            <x-theme-flip />
            @unless ($minimal)
                @auth
                    <a href="{{ route('dashboard') }}" class="ui-btn ui-btn--primary ui-btn--sm sh-cta">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="ui-btn ui-btn--ghost ui-btn--sm sh-signin">Sign in</a>
                    <a href="{{ route('register') }}" class="ui-btn ui-btn--primary ui-btn--sm sh-cta">Get started</a>
                @endauth

                <details class="sh-menu">
                    <summary class="ui-icon-btn" aria-label="Open menu">
                        <svg class="sh-menu__open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <svg class="sh-menu__close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </summary>
                    <div class="sh-sheet">
                        <nav aria-label="Site">
                            @foreach ($links as $link)
                                <a href="{{ $link['href'] }}" @class(['is-active' => $link['active']])>
                                    {{ $link['label'] }}
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            @endforeach
                        </nav>
                        <div class="sh-sheet__theme">
                            <span>Appearance</span>
                            <x-theme-toggle compact />
                        </div>
                        <div class="sh-sheet__auth">
                            @auth
                                <a href="{{ route('dashboard') }}" class="ui-btn ui-btn--primary ui-btn--block">Open dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="ui-btn ui-btn--secondary ui-btn--block">Sign in</a>
                                <a href="{{ route('register') }}" class="ui-btn ui-btn--primary ui-btn--block">Create account</a>
                            @endauth
                        </div>
                    </div>
                </details>
            @endunless
        </div>
    </div>
</header>
