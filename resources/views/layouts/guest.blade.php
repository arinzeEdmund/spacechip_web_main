<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        <!-- Scripts -->
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
            .auth-card{background:rgba(255,255,255,.75);backdrop-filter:blur(12px);border:1px solid rgba(20,84,84,.12);box-shadow:0 14px 35px rgba(15,31,31,.07);border-radius:24px}
            .btn-primary{background:linear-gradient(90deg,var(--primary),var(--secondary));color:#fff;font-weight:700;box-shadow:0 8px 20px rgba(242,116,87,.15);transition:all .2s}
            .btn-primary:hover{filter:brightness(1.05);box-shadow:0 12px 25px rgba(242,116,87,.25)}
            .logo-sc{height:48px;width:48px;border-radius:14px;background:linear-gradient(90deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:18px;margin-bottom:8px}
            .btn-disabled{opacity:.7;pointer-events:none;cursor:not-allowed;filter:grayscale(.5)}
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col">
            <header class="sticky top-0 z-50 border-b border-gray-100" style="background: rgba(255,255,255,.78); backdrop-filter: blur(12px);">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-center py-3">
                        <a href="/" class="flex items-center gap-3">
                            <div style="height:40px;width:40px;border-radius:9999px;background:linear-gradient(90deg,#f27457,#145454);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;letter-spacing:.02em">
                                SC
                            </div>
                            <div style="font-weight:800;color:rgba(20,84,84,.92);letter-spacing:.02em">
                                spacechip
                            </div>
                        </a>
                    </div>
                </div>
            </header>

            <div class="flex-1 flex flex-col sm:justify-center items-center px-4 sm:px-6 pt-8 sm:pt-0">
                <div class="w-full max-w-md mt-2 px-5 py-8 sm:px-8 sm:py-10 auth-card overflow-hidden">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            // Don't disable if form has invalid inputs (browser validation)
                            if (form.checkValidity()) {
                                submitBtn.classList.add('btn-disabled');
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
            });
        </script>
    </body>
</html>
