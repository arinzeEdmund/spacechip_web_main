@props(['variant' => 'secondary', 'icon' => null, 'href' => null, 'type' => 'button', 'size' => null])
@php
    $class = 'ad-btn ad-btn--'.(in_array($variant, ['primary', 'secondary', 'ghost', 'danger', 'dark'], true) ? $variant : 'secondary').($size === 'sm' ? ' ad-btn--sm' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>@if($icon)<i data-lucide="{{ $icon }}"></i>@endif{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>@if($icon)<i data-lucide="{{ $icon }}"></i>@endif{{ $slot }}</button>
@endif
