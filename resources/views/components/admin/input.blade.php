@props(['icon' => null])
<label class="ad-field">
    @if ($icon)<i data-lucide="{{ $icon }}"></i>@endif
    <input {{ $attributes->merge(['class' => 'ad-input']) }}>
</label>
