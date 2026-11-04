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

            .auth-card{position:relative;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-3);border-radius:28px;animation:auth-in .8s cubic-bezier(.16,1,.3,1) both}
            .auth-intro{text-align:center;margin-bottom:28px}
            .auth-emblem{margin:0 auto 20px;display:flex;justify-content:center;color:var(--text);animation:auth-float 6s ease-in-out infinite}.auth-wordmark{height:30px;width:auto}
            .auth-intro h1{margin:0;font-size:26px;font-weight:700;letter-spacing:-.03em;color:var(--text)}
            .auth-intro p{margin:8px auto 0;max-width:300px;font-size:15px;line-height:1.5;color:var(--muted)}
            .auth-foot{margin:22px 0 28px;font-size:13px;color:var(--muted);display:flex;gap:8px}
            .auth-foot a{color:var(--muted);text-decoration:none}
            .auth-foot a:hover{color:var(--accent-text)}
            @keyframes auth-in{from{opacity:0;transform:translateY(16px) scale(.985)}}
            @keyframes auth-float{50%{transform:translateY(-4px)}}
            @media (prefers-reduced-motion:reduce){.auth-card,.auth-emblem{animation:none}}

            .btn-primary{background:var(--accent-fill);color:#fff;font-weight:700;box-shadow:0 8px 20px rgb(var(--accent-rgb) / .15);transition:all .2s}
            .btn-primary:hover{filter:brightness(1.05);box-shadow:0 12px 25px rgb(var(--accent-rgb) / .25)}
            .btn-disabled{opacity:.7;pointer-events:none;cursor:not-allowed;filter:grayscale(.5)}
        </style>
    </head>
    <body class="font-sans text-text antialiased">
        @include('partials.skip-link')
        <div class="min-h-screen flex flex-col">
            @include('partials.site-header', ['minimal' => true])

            <main id="main" tabindex="-1" class="flex-1 flex flex-col sm:justify-center items-center px-4 sm:px-6 pt-8 sm:pt-0">
                @php
                    $authIntro = match (true) {
                        request()->routeIs('login') => ['Welcome back', 'Sign in to manage your eSIMs, numbers and wallet.'],
                        request()->routeIs('register') => ['Create your account', 'One account for travel eSIMs and private numbers.'],
                        request()->routeIs('password.request') => ['Reset your password', "Enter your email and we'll send you a secure reset link."],
                        request()->routeIs('password.reset') => ['Choose a new password', 'Make it long and unique to keep your account safe.'],
                        request()->routeIs('password.confirm') => ['Confirm it’s you', 'Please re-enter your password to continue.'],
                        request()->routeIs('verification.notice') => ['Verify your email', 'One quick step before you get started.'],
                        default => null,
                    };
                @endphp
                <div class="w-full max-w-md mt-2 px-6 py-8 sm:px-9 sm:py-10 auth-card">
                    @if ($authIntro)
                        <div class="auth-intro">
                            <div class="auth-emblem">@include('partials.brand-wordmark', ['class' => 'auth-wordmark'])</div>
                            <h1>{{ $authIntro[0] }}</h1>
                            <p>{{ $authIntro[1] }}</p>
                        </div>
                    @endif
                    {{ $slot }}
                </div>
                <p class="auth-foot">
                    <a href="{{ route('help') }}">Help center</a>
                    <span aria-hidden="true">·</span>
                    <a href="{{ route('privacy') }}">Privacy</a>
                    <span aria-hidden="true">·</span>
                    <a href="{{ route('terms') }}">Terms</a>
                </p>
            </main>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('form');

                const resetSubmitButton = function(submitBtn) {
                    if (!submitBtn) return;
                    submitBtn.classList.remove('btn-disabled');
                    submitBtn.disabled = false;
                    submitBtn.removeAttribute('aria-busy');

                    if (submitBtn.dataset.originalHtml) {
                        submitBtn.innerHTML = submitBtn.dataset.originalHtml;
                    }
                };

                const resetGuestForms = function() {
                    forms.forEach(form => {
                        resetSubmitButton(form.querySelector('button[type="submit"]'));
                    });
                };

                forms.forEach(form => {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.dataset.originalHtml) {
                        submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                    }

                    form.addEventListener('submit', function(e) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            // Don't disable if form has invalid inputs (browser validation)
                            if (form.checkValidity()) {
                                if (!submitBtn.dataset.originalHtml) {
                                    submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                                }
                                submitBtn.classList.add('btn-disabled');
                                submitBtn.disabled = true;
                                submitBtn.setAttribute('aria-busy', 'true');
                                submitBtn.innerHTML = `
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing...
                                `;
                            }
                        }
                    });
                });

                window.addEventListener('pageshow', function(event) {
                    resetGuestForms();

                    if (event.persisted) {
                        window.location.reload();
                    }
                });
            });
        </script>
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
