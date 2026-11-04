@props(['id', 'title', 'description' => null])
<dialog id="{{ $id }}" style="width:min(92vw,460px)">
    <div class="ad-dialog-head">
        <div>
            <h3>{{ $title }}</h3>
            @if ($description)<p>{{ $description }}</p>@endif
        </div>
        <button type="button" data-close-dialog class="ad-btn ad-btn--ghost ad-btn--sm" style="width:30px;padding:0" aria-label="Close"><i data-lucide="x"></i></button>
    </div>
    {{ $slot }}
</dialog>
