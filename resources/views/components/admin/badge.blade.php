@props(['tone' => 'slate', 'dot' => false, 'pulse' => false])
@php
    // Legacy tone names map onto the semantic pill tones.
    $map = ['slate' => '', 'emerald' => 'success', 'amber' => 'warning', 'red' => 'danger', 'sky' => 'info', 'coral' => 'accent', 'teal' => 'accent', 'violet' => 'violet'];
    $t = $map[$tone] ?? '';
@endphp
<span {{ $attributes->class(['ad-badge', 'ad-badge--'.$t => $t, 'ad-badge--pulse' => $pulse]) }}>@if ($dot)<i></i>@endif{{ $slot }}</span>
