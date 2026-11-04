{{--
    Tailwind + Alpine for the layout-based pages.

    Prefers the compiled Vite bundle (`npm run build`). Until the deploy
    pipeline builds front-end assets, falls back to the Tailwind Play CDN
    and the Alpine CDN build so the UI keeps working either way.
--}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
    <script src="https://cdn.tailwindcss.com"></script>
    {{-- Must come after the CDN script, which replaces window.tailwind on load. --}}
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: {
                colors: {!! file_get_contents(resource_path('theme/tailwind-colors.json')) !!},
                boxShadow: { card: 'var(--shadow-2)', lift: 'var(--shadow-3)' },
            } },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
@endif
