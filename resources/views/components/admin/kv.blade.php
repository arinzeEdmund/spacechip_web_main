@props(['label'])
<div class="ad-kv">
    <dt>{{ $label }}</dt>
    <dd {{ $attributes }}>{{ $slot }}</dd>
</div>
