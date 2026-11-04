<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'spacechip') }}">

        <title>{{ config('app.name', 'spacechip') }}</title>
        @include('partials.head-assets')

        @include('partials.tailwind')
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif}
            body{font-family:var(--font-sans);background:radial-gradient(900px 520px at 10% -10%, rgb(var(--accent-rgb) / .10) 0%, transparent 60%),
                var(--bg)}

            body::before{content:"";position:fixed;inset:-20%;background:radial-gradient(640px 460px at 90% 6%, rgb(var(--accent-rgb) / .07) 0%, transparent 70%);
                filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}


            .page-card{background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-2);border-radius:24px}


        </style>
    </head>
    <body class="font-sans text-text antialiased">
        @include('partials.skip-link')
        <div class="min-h-screen flex flex-col">
            @include('partials.site-header')

            <main id="main" class="flex-1" tabindex="-1">
                @if ($bare)
                    {{ $slot }}
                @else
                    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                        <div class="page-card px-5 py-8 sm:px-10 sm:py-10">
                            {{ $slot }}
                        </div>
                    </div>
                @endif
            </main>

            <footer class="site-foot">
                <div class="site-foot__inner">
                    <div class="site-foot__brand">
                        <a href="{{ route('home') }}" class="sh-brand" aria-label="spacechip home">@include('partials.brand-wordmark', ['class' => 'scn-wordmark'])</a>
                        <p>Be local anywhere with flexible plans for data, calls and privacy-first numbers.</p>
                    </div>
                    <nav aria-label="Company">
                        <h2>Company</h2>
                        <a href="{{ route('contact') }}">Contact us</a>
                        <a href="{{ route('terms') }}">Terms &amp; conditions</a>
                        <a href="{{ route('privacy') }}">Privacy policy</a>
                        <a href="{{ route('refund') }}">Refund policy</a>
                        <a href="{{ route('acceptable.use') }}">Acceptable use</a>
                        <a href="{{ route('cookies') }}">Cookie policy</a>
                        <a href="{{ route('privacy.request') }}">Privacy request</a>
                        <a href="{{ route('accessibility') }}">Accessibility</a>
                    </nav>
                    <nav aria-label="Support">
                        <h2>Support</h2>
                        <a href="{{ route('help') }}">Help center</a>
                        <a href="{{ route('esim.guide') }}">eSIM guide</a>
                        <a href="{{ route('assets.index', ['tab' => 'countries']) }}">eSIM plans</a>
                    </nav>
                </div>
                <div class="site-foot__legal">© {{ date('Y') }} {{ config('app.name', 'spacechip') }}. All rights reserved.</div>
            </footer>
        </div>
        <div id="pwa-install-banner" class="hidden fixed left-4 right-4 bottom-4 z-[9999]" aria-hidden="true">
            <div class="rounded-2xl border border-white/15 bg-black/80 text-white/90 backdrop-blur-xl px-4 py-3 shadow-[0_14px_35px_rgba(0,0,0,.25)] flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-[200px] max-w-[560px]">
                    <div class="font-extrabold">Install {{ config('app.name', 'spacechip') }}</div>
                    <div id="pwa-install-text" class="mt-0.5 text-sm text-white/70">Get faster access and offline support.</div>
                </div>
                <div class="flex items-center gap-2">
                    <button id="pwa-install-later" type="button" class="px-4 py-2 rounded-full bg-white/10 border border-white/15 font-extrabold text-white/85">Not now</button>
                    <button id="pwa-install-action" type="button" class="px-4 py-2 rounded-full font-extrabold text-white bg-accent-fill hover:bg-accent-fill-hover">Install</button>
                </div>
            </div>
        </div>
        <script src="{{ asset('pwa-install.js') }}" defer></script>
    </body>
</html>
