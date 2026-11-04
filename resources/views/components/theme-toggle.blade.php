{{-- Segmented Light / Dark / System switch (new_theme/theme/theme-toggle.tsx). `compact` hides the labels. --}}
@props(['compact' => false])
@php
    $options = [
        'light' => ['Light', '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>'],
        'dark' => ['Dark', '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'],
        'system' => ['System', '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'],
    ];
@endphp
<div role="radiogroup" aria-label="Theme" {{ $attributes->class(['theme-toggle', 'theme-toggle--compact' => $compact]) }}>
    @foreach ($options as $value => [$label, $icon])
        <button type="button" role="radio" aria-checked="false" data-theme-value="{{ $value }}" title="{{ $label }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
            <span>{{ $label }}</span>
        </button>
    @endforeach
</div>
