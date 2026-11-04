{{-- Horizontal filter bar; submit on change. --}}
<form method="GET" {{ $attributes->merge(['class' => 'ad-toolbar']) }} onchange="this.requestSubmit()">
    {{ $slot }}
</form>
