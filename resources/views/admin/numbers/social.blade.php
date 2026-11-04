@php use App\Support\Money; @endphp
<x-admin.layout title="Social numbers" subtitle="One-time verification numbers and monthly rentals (SMSPool).">
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Delivered revenue" :value="Money::usd($economics['revenue'])" icon="badge-dollar-sign" tone="emerald" :hint="'Provider cost '.Money::usd($economics['cost'])" />
        <x-admin.stat label="Gross margin" :value="Money::usd($economics['revenue'] - $economics['cost'])" icon="trending-up" tone="coral" :hint="$economics['revenue'] > 0 ? round(($economics['revenue'] - $economics['cost']) / $economics['revenue'] * 100).'% margin' : null" />
        <x-admin.stat label="SMS success rate" :value="$economics['success_rate'] !== null ? $economics['success_rate'].'%' : '—'" icon="circle-check" :tone="($economics['success_rate'] ?? 100) < 50 ? 'amber' : 'teal'" hint="Orders that received a code" />
        <x-admin.stat label="Refunded" :value="Money::usd($economics['refunded'])" icon="undo-2" tone="amber" hint="Returned to wallets" />
    </div>

    <div class="grid gap-4 xl:grid-cols-4">
        <x-admin.card class="xl:col-span-3" title="Verification orders" :padding="false"><div class="p-5 pt-3">
            <x-admin.filters>
                <x-admin.input name="q" icon="search" value="{{ $filters['q'] }}" placeholder="Number, app or customer" class="w-64" />
                <x-admin.select name="status" :selected="$filters['status']" :options="['' => 'All statuses'] + $statusCounts->keys()->mapWithKeys(fn ($s) => [$s => ucfirst(strtolower($s)).' ('.$statusCounts[$s].')'])->all()" />
            </x-admin.filters>
            <x-admin.table>
                <thead><tr><th>Customer</th><th>App</th><th>Number</th><th>Code</th><th>Status</th><th class="text-right">Price / cost</th><th class="text-right">Ordered</th><th></th></tr></thead>
                <tbody>
                    @forelse ($orders as $o)
                        <tr>
                            <td><x-admin.user-cell :user="$o->user" /></td>
                            <td class="whitespace-nowrap"><div class="font-medium">{{ $o->product_name }}</div><div class="text-[12px] text-muted">{{ $o->country_name ?? $o->country }}</div></td>
                            <td class="whitespace-nowrap tabular text-muted">{{ $o->phone ? '+'.ltrim($o->phone, '+') : '—' }}</td>
                            <td class="font-mono text-[12.5px]">{{ $o->sms_code ?: '—' }}</td>
                            <td><x-admin.status :value="$o->status" />@if ($o->refunded_at)<div class="mt-1 text-[12px] text-violet-600">Refunded</div>@endif</td>
                            <td class="text-right tabular"><div class="font-medium">{{ Money::usd($o->sell_amount_minor) }}</div><div class="text-[12px] text-faint">{{ Money::usd($o->provider_cost_minor) }}</div></td>
                            <td class="whitespace-nowrap text-right text-muted">{{ $o->created_at?->format('M j, H:i') }}</td>
                            <td class="text-right">@unless (in_array($o->status, $closed, true))<form method="POST" action="{{ route('admin.numbers.social.cancel', $o) }}" data-confirm="Cancel this order with the provider and refund the customer’s wallet?">@csrf<x-admin.button type="submit" variant="ghost">Cancel</x-admin.button></form>@endunless</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-admin.empty icon="message-square-text" title="No orders match" /></td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
            {{ $orders->links('admin.partials.pagination') }}
        </div></x-admin.card>

        <x-admin.card title="Most requested apps">
            @php $maxP = max(1, $topProducts->max() ?? 1); @endphp
            @forelse ($topProducts as $name => $count)
                <div class="mb-3 last:mb-0">
                    <div class="mb-1 flex justify-between text-[13px]"><span>{{ $name }}</span><span class="tabular text-muted">{{ $count }}</span></div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full" style="width:{{ $count / $maxP * 100 }}%;background:var(--accent-fill)"></div></div>
                </div>
            @empty
                <p class="text-[13px] text-muted">No orders yet.</p>
            @endforelse
        </x-admin.card>
    </div>

    <x-admin.card class="mt-6" title="Monthly rentals" :padding="false"><div class="p-5 pt-3">
        <x-admin.table>
            <thead><tr><th>Customer</th><th>App</th><th>Number</th><th>Status</th><th>Period ends</th><th>Auto-renew</th><th class="text-right">Monthly</th><th></th></tr></thead>
            <tbody>
                @forelse ($rentals as $r)
                    <tr>
                        <td><x-admin.user-cell :user="$r->user" /></td>
                        <td class="font-medium">{{ $r->product_name }} <span class="font-normal text-muted">· {{ $r->country_name ?? $r->country }}</span></td>
                        <td class="tabular text-muted">{{ $r->phone ?: '—' }}</td>
                        <td><x-admin.status :value="$r->status" /></td>
                        <td class="text-muted">{{ $r->current_period_end?->format('M j, Y') ?? '—' }}</td>
                        <td>{{ $r->auto_renew ? 'On' : 'Off' }}</td>
                        <td class="text-right tabular">{{ Money::usd($r->monthly_amount_minor) }}</td>
                        <td class="text-right">@unless (in_array($r->status, ['canceled', 'expired'], true))<form method="POST" action="{{ route('admin.numbers.rentals.cancel', $r) }}" data-confirm="Cancel this rental? It will stop renewing.">@csrf<x-admin.button type="submit" variant="ghost">Cancel</x-admin.button></form>@endunless</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty icon="calendar-clock" title="No rentals yet" /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        {{ $rentals->links('admin.partials.pagination') }}
    </div></x-admin.card>
</x-admin.layout>
