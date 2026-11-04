@php use App\Support\Money; @endphp
<x-admin.layout title="Payments" subtitle="Every checkout across card, wallet and crypto, with fulfilment status.">
    @include('admin.payments._filters')
    <x-admin.card :padding="false"><div class="p-5">
        <x-admin.table>
            <thead><tr><th>#</th><th>Customer</th><th>Product</th><th>Provider</th><th>Status</th><th class="text-right">Amount</th><th class="text-right">Date</th></tr></thead>
            <tbody>
                @forelse ($payments as $p)
                    <tr class="cursor-pointer" onclick="location.href='{{ route('admin.payments.show', $p) }}'">
                        <td class="text-muted tabular">{{ $p->id }}</td>
                        <td><x-admin.user-cell :user="$p->user" /></td>
                        <td><div class="font-medium">{{ $p->displayTitle() }}</div><div class="text-[12px] text-muted">{{ $p->displaySubtitle() }}</div></td>
                        <td class="text-muted">{{ ucfirst($p->provider) }}</td>
                        <td><x-admin.status :value="$p->status" /></td>
                        <td class="text-right font-medium tabular">{{ Money::format($p->amount_minor, $p->currency) }}</td>
                        <td class="whitespace-nowrap text-right text-muted">{{ $p->created_at?->format('M j, H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty icon="receipt" title="No payments match" body="Adjust the filters to see more." /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        {{ $payments->links('admin.partials.pagination') }}
    </div></x-admin.card>
</x-admin.layout>
