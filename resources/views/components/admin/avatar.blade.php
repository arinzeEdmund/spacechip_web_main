@props(['user' => null, 'size' => 'md'])
@php
    $name = (string) ($user?->name ?? '?');
    $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
    $palette = collect(['accent', 'sky', 'violet', 'amber', 'neutral', 'emerald'])->map(fn ($c) => ["rgb(var(--tw-$c-100))", "rgb(var(--tw-$c-700))"])->all();
    [$bg, $fg] = $palette[crc32($name) % count($palette)];
    $dim = 'ad-avatar--'.(in_array($size, ['sm', 'lg'], true) ? $size : 'md');
@endphp
<span {{ $attributes->merge(['class' => "ad-avatar $dim"]) }} style="background:{{ $bg }};color:{{ $fg }}">{{ $initials }}</span>
