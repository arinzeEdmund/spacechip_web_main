@props(['title' => null, 'subtitle' => null, 'padding' => true, 'action' => null])
<section {{ $attributes->merge(['class' => 'ad-card']) }}>
    @if ($title || $action)
        <div class="ad-card-head">
            <div>
                @if ($title)<h2 class="ad-card-title">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="ad-card-sub">{{ $subtitle }}</p>@endif
            </div>
            {{ $action }}
        </div>
    @endif
    <div @class(['ad-card-body', 'is-flush' => ! $padding])>{{ $slot }}</div>
</section>
