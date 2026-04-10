<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'spacechip') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

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
            .nav-bg{background:rgba(255,255,255,.78);backdrop-filter:blur(12px)}
            .logo{height:40px;width:40px;border-radius:9999px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;letter-spacing:.02em}
            .page-card{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);border-radius:24px}
            footer{border-top:1px solid rgba(15,31,31,.10);background:rgba(255,255,255,.35);backdrop-filter:blur(10px)}
            .links{color:rgba(15,31,31,.64);font-size:14px;line-height:22px;text-decoration:none}
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col">
            <header class="sticky top-0 z-50 border-b border-gray-100 nav-bg">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between gap-4 py-3">
                        <a href="{{ url('/') }}" class="flex items-center gap-3">
                            <div class="logo">SC</div>
                            <div style="font-weight:800;color:rgba(20,84,84,.92);letter-spacing:.02em">spacechip</div>
                        </a>
                        <div class="flex items-center gap-3 text-sm font-semibold">
                            <a href="{{ url('/allassets?tab=countries') }}" class="text-[#145454] hover:text-[#f27457] transition-colors">All Assets</a>
                            @auth
                                <a href="{{ route('dashboard') }}" class="text-[#145454] hover:text-[#f27457] transition-colors">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="text-[#145454] hover:text-[#f27457] transition-colors">Sign In</a>
                            @endauth
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1">
                <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                    <div class="page-card px-5 py-8 sm:px-10 sm:py-10">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <footer>
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="py-10 grid gap-8 md:grid-cols-3">
                        <div>
                            <div class="flex items-center gap-3">
                                <div class="logo" style="height:34px;width:34px;border-radius:14px">SC</div>
                                <div style="font-weight:800;color:rgba(20,84,84,.92)">spacechip</div>
                            </div>
                            <p style="margin-top:10px;color:rgba(15,31,31,.64);font-size:14px;line-height:1.6">Be local anywhere with flexible plans for data, calls, and privacy-first numbers.</p>
                        </div>
                        <div>
                            <div style="font-weight:800;color:#0b1a1a">Company</div>
                            <div style="margin-top:10px;display:grid;gap:8px">
                                <a class="links" href="{{ route('contact') }}">Contact Us</a>
                                <a class="links" href="{{ route('terms') }}">Terms &amp; Conditions</a>
                                <a class="links" href="{{ route('privacy') }}">Privacy Policy</a>
                            </div>
                        </div>
                        <div>
                            <div style="font-weight:800;color:#0b1a1a">Support</div>
                            <div style="margin-top:10px;display:grid;gap:8px">
                                <a class="links" href="{{ route('help') }}">Help Center</a>
                                <a class="links" href="{{ route('esim.guide') }}">eSIM Guide</a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
