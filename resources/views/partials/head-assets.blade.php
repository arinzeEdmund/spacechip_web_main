{{-- Shared <head> assets for every page: theme, icons, PWA manifest, type, design system, UI runtime. --}}
@include('partials.theme-script')
<meta name="format-detection" content="telephone=no">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="alternate icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">

<script>
    // Hold the body for one tick so entrance animations start from a clean frame.
    (function (d) {
        d.classList.add('sc-preload');
        setTimeout(function () { d.classList.remove('sc-preload'); }, 1500);
    })(document.documentElement);
</script>

{{-- Apple platforms render SF Pro via the system stack; Instrument Sans is the brand fallback elsewhere. --}}
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="{{ asset('css/spacechip-palette.css') }}?v={{ @filemtime(public_path('css/spacechip-palette.css')) }}">
<link rel="stylesheet" href="{{ asset('css/spacechip-theme.css') }}?v={{ @filemtime(public_path('css/spacechip-theme.css')) }}">
<link rel="stylesheet" href="{{ asset('css/spacechip-utilities.css') }}?v={{ @filemtime(public_path('css/spacechip-utilities.css')) }}">
<link rel="stylesheet" href="{{ asset('css/spacechip-ui.css') }}?v={{ @filemtime(public_path('css/spacechip-ui.css')) }}">
<script src="{{ asset('js/spacechip-ui.js') }}?v={{ @filemtime(public_path('js/spacechip-ui.js')) }}" defer></script>
