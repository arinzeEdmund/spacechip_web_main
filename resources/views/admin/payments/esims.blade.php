@php use App\Support\Money; @endphp
<x-admin.layout title="eSIM orders" subtitle="Plans sold, with provisioning details from the provider.">
    @include('admin.payments._filters')
    <x-admin.card :padding="false"><div class="p-5">
        <x-admin.table>
            <thead><tr><th>Customer</th><th>Destination & plan</th><th>ICCID</th><th>Provisioning</th><th class="text-right">Paid</th><th class="text-right">Date</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    @php $f = (array) ($p->fulfillment_payload ?? []); @endphp
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.payments.show', $p) }}'">
                        <td><x-admin.user-cell :user="$p->user" /></td>
                        <td><div class="font-medium">{{ data_get($p->provider_payload, 'asset.name') ?: $p->asset_id }}</div><div class="text-[12px] text-muted">{{ collect([data_get($p->provider_payload, 'bundle.data'), data_get($p->provider_payload, 'bundle.validity')])->filter()->implode(' · ') ?: $p->bundle_id }}</div></td>
                        <td class="font-mono text-[12px] text-muted">{{ $f['iccid'] ?? '—' }}</td>
                        <td>
                            <x-admin.status :value="$p->status" />
                            @if (! empty($f['error']))<div class="mt-1 max-w-[220px] truncate text-[12px] text-danger" title="{{ $f['error'] }}">{{ $f['error'] }}</div>@endif
                        </td>
                        <td class="text-right font-medium tabular">{{ Money::format($p->amount_minor, $p->currency) }}</td>
                        <td class="whitespace-nowrap text-right text-muted">{{ $p->created_at?->format('M j, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty icon="cpu" title="No eSIM orders yet" /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        {{ $payments->links('admin.partials.pagination') }}
    </div></x-admin.card>
</x-admin.layout>
