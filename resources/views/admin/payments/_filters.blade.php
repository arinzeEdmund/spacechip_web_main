@php use App\Support\Money; @endphp
<div class="mb-5 grid gap-4 sm:grid-cols-3">
    <x-admin.stat label="Settled in view" :value="Money::usd($totals['settled'])" icon="badge-dollar-sign" tone="emerald" hint="USD, paid + fulfilled" />
    <x-admin.stat label="Needs attention" :value="$totals['attention']" icon="siren" :tone="$totals['attention'] ? 'red' : 'slate'" hint="Paid but not delivered" href="{{ request()->fullUrlWithQuery(['status' => 'attention', 'page' => null]) }}" />
    <x-admin.stat label="Records" :value="number_format($payments->total())" icon="list" tone="slate" hint="Matching current filters" />
</div>

<div class="mb-4">
    @php $chips = ['' => 'All', 'attention' => 'Needs attention', 'failed' => 'Failed'] + $statusCounts->keys()->mapWithKeys(fn ($s) => [$s => ucfirst(str_replace('_', ' ', $s))])->all(); @endphp
    <nav class="ad-seg" aria-label="Filter by status">
        @foreach ($chips as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['status' => $key ?: null, 'page' => null]) }}" @class(['is-active' => $filters['status'] === (string) $key])>
                {{ $label }}@isset($statusCounts[$key])<em>{{ $statusCounts[$key] }}</em>@endisset
            </a>
        @endforeach
    </nav>
</div>

<x-admin.filters>
    <input type="hidden" name="status" value="{{ $filters['status'] }}">
    <x-admin.input name="q" icon="search" value="{{ $filters['q'] }}" placeholder="Reference, ID, bundle or customer" />
    <x-admin.select name="provider" :selected="$filters['provider']" :options="['' => 'All providers'] + $providers->mapWithKeys(fn ($p) => [$p => ucfirst($p)])->all()" />
    @unless (request()->routeIs('admin.esims'))
        <x-admin.select name="asset" :selected="$filters['asset']" :options="['' => 'All products', 'esim' => 'eSIMs'] + $assets->mapWithKeys(fn ($a) => [$a => ucfirst(str_replace('_', ' ', $a))])->all()" />
    @endunless
    <x-admin.input type="date" name="from" value="{{ $filters['from'] }}" aria-label="From date" style="width:160px" />
    <x-admin.input type="date" name="to" value="{{ $filters['to'] }}" aria-label="To date" style="width:160px" />
    @if (array_filter($filters))<x-admin.button href="{{ url()->current() }}" variant="ghost" icon="x">Reset</x-admin.button>@endif
</x-admin.filters>
