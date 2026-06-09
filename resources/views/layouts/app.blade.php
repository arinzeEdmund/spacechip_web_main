<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="paystack-public-key" content="{{ (string) (config('services.paystack.public_key') ?: env('PAYSTACK_PUBLIC_KEY')) }}">
        <meta name="theme-color" content="#0b1a1a">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'spacechip') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="alternate icon" href="{{ asset('favicon.svg') }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.svg') }}">
        <link rel="manifest" href="{{ asset('manifest.json') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <script>
            tailwind = window.tailwind || {}
            tailwind.config = Object.assign({}, tailwind.config || {}, { darkMode: 'class' })
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            :root{--font-sans:"Instrument Sans",ui-sans-serif,system-ui,sans-serif;--primary:#f27457;--secondary:#145454}
            body{font-family:var(--font-sans);background:
                radial-gradient(900px 520px at 12% 14%, rgba(242,116,87,.32) 0%, rgba(242,116,87,0) 60%),
                radial-gradient(980px 560px at 88% 18%, rgba(20,84,84,.26) 0%, rgba(20,84,84,0) 62%),
                radial-gradient(1100px 700px at 50% 92%, rgba(242,116,87,.18) 0%, rgba(242,116,87,0) 65%),
                linear-gradient(180deg, #F7F7F8 0%, #F5F6F8 60%, #F7F7F8 100%)}
            body::before{content:"";position:fixed;inset:-20%;background:
                radial-gradient(520px 420px at 18% 32%, rgba(242,116,87,.35) 0%, rgba(242,116,87,0) 70%),
                radial-gradient(560px 460px at 82% 38%, rgba(20,84,84,.28) 0%, rgba(20,84,84,0) 72%),
                radial-gradient(700px 520px at 58% 66%, rgba(242,116,87,.22) 0%, rgba(242,116,87,0) 74%);
                filter:blur(26px);opacity:.9;z-index:-1;pointer-events:none}
        </style>
    </head>
    <body class="font-sans antialiased text-gray-900">
        <div class="min-h-screen flex flex-col">
            @if (request()->routeIs('dashboard') || request()->routeIs('admin.*') || request()->routeIs('virtual.*'))
                <div class="fixed inset-0 z-0 pointer-events-none opacity-50" style="background:
                    radial-gradient(900px 520px at 12% 14%, rgba(242,116,87,.32) 0%, rgba(242,116,87,0) 60%),
                    radial-gradient(980px 560px at 88% 18%, rgba(20,84,84,.26) 0%, rgba(20,84,84,0) 62%),
                    radial-gradient(1100px 700px at 50% 92%, rgba(242,116,87,.18) 0%, rgba(242,116,87,0) 65%),
                    conic-gradient(from 220deg at 8% 12%, rgba(242,116,87,.14), rgba(20,84,84,.12), rgba(242,116,87,.08))"></div>
            @endif
            
            <div class="relative z-10 flex-1 flex flex-col">
                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white/80 backdrop-blur-md shadow-sm border-b border-gray-100">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="flex-1 flex flex-col">
                    {{ $slot }}
                </main>
            </div>
        </div>
        <div id="pwa-install-banner" class="hidden fixed left-4 right-4 bottom-4 z-[9999]" aria-hidden="true">
            <div class="rounded-2xl border border-white/15 bg-slate-900/70 text-white/90 backdrop-blur-xl px-4 py-3 shadow-[0_14px_35px_rgba(0,0,0,.25)] flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-[200px] max-w-[560px]">
                    <div class="font-extrabold">Install {{ config('app.name', 'spacechip') }}</div>
                    <div id="pwa-install-text" class="mt-0.5 text-sm text-white/70">Get faster access and offline support.</div>
                </div>
                <div class="flex items-center gap-2">
                    <button id="pwa-install-later" type="button" class="px-4 py-2 rounded-full bg-white/10 border border-white/15 font-extrabold text-white/85">Not now</button>
                    <button id="pwa-install-action" type="button" class="px-4 py-2 rounded-full font-extrabold text-white bg-gradient-to-r from-[#f27457] to-[#145454]">Install</button>
                </div>
            </div>
        </div>
        <script src="{{ asset('pwa-install.js') }}" defer></script>
    </body>
</html>
