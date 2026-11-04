@php use App\Support\Money; @endphp
<x-admin.layout title="Virtual numbers" subtitle="Monthly Twilio numbers: subscriptions, catalogue and message traffic.">
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Active" :value="$statusCounts['active'] ?? 0" icon="phone" tone="emerald" />
        <x-admin.stat label="Monthly recurring" :value="Money::usd($mrr)" icon="repeat" tone="coral" hint="From active USD subscriptions" />
        <x-admin.stat label="Past due" :value="$statusCounts['past_due'] ?? 0" icon="calendar-x" :tone="($statusCounts['past_due'] ?? 0) ? 'amber' : 'slate'" />
        <x-admin.stat label="Catalogue" :value="$products->count()" icon="globe" tone="teal" :hint="$products->where('active', true)->count().' active products'" />
    </div>

    <x-admin.card :padding="false"><div class="p-5">
        <x-admin.filters>
            <x-admin.input name="q" icon="search" value="{{ $filters['q'] }}" placeholder="Number or customer email" class="w-64" />
            <x-admin.select name="status" :selected="$filters['status']" :options="['' => 'All statuses'] + $statusCounts->keys()->mapWithKeys(fn ($s) => [$s => ucfirst(str_replace('_', ' ', $s))])->all()" />
        </x-admin.filters>
        <x-admin.table>
            <thead><tr><th>Number</th><th>Customer</th><th>Plan</th><th>Status</th><th>Renews</th><th class="text-right">Messages</th><th></th></tr></thead>
            <tbody>
                @forelse ($subscriptions as $s)
                    <tr>
                        <td class="font-medium tabular">{{ $s->phone_number ?: 'Pending' }}</td>
                        <td><x-admin.user-cell :user="$s->user" /></td>
                        <td class="text-muted">{{ $s->product?->label ?? $s->country_iso }} · {{ Money::format($s->monthly_amount_minor, $s->currency) }}/mo <span class="text-faint">· {{ $s->billing_method }}</span></td>
                        <td><x-admin.status :value="$s->status" />@if ($s->last_renewal_error)<div class="mt-1 max-w-[200px] truncate text-[12px] text-danger" title="{{ $s->last_renewal_error }}">{{ $s->last_renewal_error }}</div>@endif</td>
                        <td class="text-muted">{{ $s->current_period_end?->format('M j, Y') ?? '—' }}</td>
                        <td class="text-right tabular">{{ $s->messages_count }}</td>
                        <td class="text-right">@if ($s->status !== 'canceled')<form method="POST" action="{{ route('admin.numbers.virtual.cancel', $s) }}" data-confirm="Cancel and release {{ $s->phone_number ?: 'this number' }}? The customer loses the number immediately.">@csrf<x-admin.button type="submit" variant="ghost">Cancel</x-admin.button></form>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty icon="phone" title="No virtual number subscriptions" body="Subscriptions appear here as soon as a customer buys a number." /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        {{ $subscriptions->links('admin.partials.pagination') }}
    </div></x-admin.card>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <x-admin.card title="Catalogue" subtitle="Countries offered for monthly numbers">
            <x-admin.table>
                <thead><tr><th>Country</th><th>Capabilities</th><th class="text-right">Price</th><th class="text-right">Subscribers</th></tr></thead>
                <tbody>
                    @forelse ($products as $prod)
                        <tr>
                            <td class="font-medium">{{ $prod->label }} <span class="text-faint">{{ $prod->country_iso }}</span> @unless ($prod->active)<x-admin.badge>Inactive</x-admin.badge>@endunless</td>
                            <td class="space-x-1">@if ($prod->cap_sms)<x-admin.badge tone="sky">SMS</x-admin.badge>@endif @if ($prod->cap_voice)<x-admin.badge tone="violet">Voice</x-admin.badge>@endif</td>
                            <td class="text-right tabular">{{ Money::format($prod->monthly_amount_minor, $prod->currency) }}</td>
                            <td class="text-right tabular">{{ $prod->subscriptions_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No products configured.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
        </x-admin.card>
        <x-admin.card title="Latest messages" subtitle="Inbound and outbound SMS and calls">
            @forelse ($messages as $m)
                <div class="flex gap-3 border-t border-ink/[.05] py-2.5 first:border-t-0 first:pt-0">
                    <span @class(['mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg', 'bg-teal-50 text-teal-600' => $m->direction === 'inbound', 'bg-coral-50 text-coral-600' => $m->direction !== 'inbound'])>
                        <i data-lucide="{{ $m->message_type === 'voice' ? 'phone-call' : ($m->direction === 'inbound' ? 'arrow-down-left' : 'arrow-up-right') }}" class="h-3.5 w-3.5"></i>
                    </span>
                    <div class="min-w-0 flex-1 text-[13px]">
                        <div class="flex justify-between gap-2"><span class="font-medium tabular">{{ $m->from }} → {{ $m->to }}</span><span class="shrink-0 text-[12px] text-faint">{{ $m->created_at?->diffForHumans(short: true) }}</span></div>
                        <div class="truncate text-muted">{{ $m->body ?: ($m->message_type === 'voice' ? 'Voice call'.($m->recording_duration ? ' · '.$m->recording_duration.'s' : '') : '—') }}</div>
                    </div>
                </div>
            @empty
                <x-admin.empty icon="messages-square" title="No message traffic yet" class="!py-8" />
            @endforelse
        </x-admin.card>
    </div>
</x-admin.layout>
