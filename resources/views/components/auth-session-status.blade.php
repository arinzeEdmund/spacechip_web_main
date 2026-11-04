@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-xl bg-success-soft px-4 py-3 text-sm font-medium text-success']) }}>
        {{ $status }}
    </div>
@endif
