{{-- Wrapper giving every data table the same chrome. Use <thead> / <tbody> inside. --}}
<div class="ad-table-wrap">
    <table {{ $attributes->merge(['class' => 'ad-table']) }}>
        {{ $slot }}
    </table>
</div>
