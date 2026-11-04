@props(['label', 'value', 'icon' => null, 'tone' => 'coral', 'hint' => null, 'href' => null, 'spark' => null])
@php
    $tones = ['coral' => '', 'teal' => '', 'emerald' => 'success', 'amber' => 'warning', 'red' => 'danger', 'slate' => 'neutral', 'blue' => 'info'];
    $toneClass = ($t = $tones[$tone] ?? '') ? 'ad-tile--'.$t : '';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'ad-stat']) }}>
    <div class="ad-stat-top">
        <span class="ad-stat-label">{{ $label }}</span>
        @if ($icon)<span class="ad-tile {{ $toneClass }}"><i data-lucide="{{ $icon }}"></i></span>@endif
    </div>
    <div class="ad-stat-value">{{ $value }}</div>
    @if ($hint)<div class="ad-stat-hint">{{ $hint }}</div>@endif
    @if ($spark && count($spark) > 1)
        @php
            $max = max(1, max($spark)); $n = count($spark); $w = 200; $h = 36;
            $pts = collect($spark)->map(fn ($v, $i) => round($i * $w / ($n - 1), 1).','.round($h - ($v / $max) * ($h - 4) - 2, 1))->implode(' ');
            $gid = 'sg-'.md5($label);
        @endphp
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="ad-spark" preserveAspectRatio="none" aria-hidden="true">
            <defs><linearGradient id="{{ $gid }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#2f9e2f" stop-opacity=".32"/><stop offset="1" stop-color="#2f9e2f" stop-opacity="0"/></linearGradient></defs>
            <polygon points="0,{{ $h }} {{ $pts }} {{ $w }},{{ $h }}" fill="url(#{{ $gid }})"/>
            <polyline points="{{ $pts }}" fill="none" stroke="#3fae3f" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        </svg>
    @endif
</{{ $tag }}>
