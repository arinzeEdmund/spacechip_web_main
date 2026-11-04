@props(['icon' => 'inbox', 'title' => 'Nothing here yet', 'body' => null])
<div {{ $attributes->merge(['class' => 'ad-empty']) }}>
    <span class="ad-tile"><i data-lucide="{{ $icon }}"></i></span>
    <b>{{ $title }}</b>
    @if ($body)<p>{{ $body }}</p>@endif
    {{ $slot }}
</div>
