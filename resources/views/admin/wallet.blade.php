@php use App\Support\Money; @endphp
<x-admin.layout title="Wallet ledger" subtitle="Every credit and debit across all Reserve wallets.">
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-admin.stat label="Total float" :value="Money::usd($summary['float'])" icon="landmark" tone="emerald" hint="Customer money held in wallets" />
        <x-admin.stat label="Credits · 30 days" :value="Money::usd($summary['credits_30d'])" icon="arrow-down-left" tone="teal" />
        <x-admin.stat label="Debits · 30 days" :value="Money::usd($summary['debits_30d'])" icon="arrow-up-right" tone="coral" />
        <x-admin.stat label="Refunds · 30 days" :value="Money::usd($summary['refunds_30d'])" icon="undo-2" tone="amber" :hint="$summary['admin_adjustments'].' manual adjustments all time'" />
    </div>

    <div class="grid gap-4 xl:grid-cols-4">
        <x-admin.card class="xl:col-span-3" :padding="false"><div class="p-5">
            <x-admin.filters>
                <x-admin.input name="q" icon="search" value="{{ $filters['q'] }}" placeholder="Customer name or email" class="w-64" />
                <x-admin.select name="direction" :selected="$filters['direction']" :options="['' => 'Credits & debits', 'credit' => 'Credits only', 'debit' => 'Debits only']" />
                <x-admin.select name="action" :selected="$filters['action']" :options="['' => 'All activity'] + $actions->mapWithKeys(fn ($a) => [$a => ucfirst(str_replace('_', ' ', $a))])->all()" />
            </x-admin.filters>
            <x-admin.table>
                <thead><tr><th>Customer</th><th>Activity</th><th>Note</th><th class="text-right">Amount</th><th class="text-right">Balance</th><th class="text-right">Date</th></tr></thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td><x-admin.user-cell :user="$tx->user" /></td>
                            <td><span class="font-medium">{{ ucfirst(str_replace('_', ' ', $tx->action)) }}</span>@if ($tx->payment_id)<a href="{{ route('admin.payments.show', $tx->payment_id) }}" class="ml-1.5 text-[12px] text-coral-600 hover:underline">#{{ $tx->payment_id }}</a>@endif</td>
                            <td class="max-w-[220px] truncate text-muted">{{ data_get($tx->meta, 'reason') ?? '—' }}</td>
                            <td @class(['text-right font-medium tabular', 'text-success' => $tx->direction === 'credit'])>{{ $tx->direction === 'credit' ? '+' : '−' }}{{ Money::usd($tx->amount_minor) }}</td>
                            <td class="text-right tabular text-muted">{{ Money::usd($tx->balance_after_minor) }}</td>
                            <td class="whitespace-nowrap text-right text-muted">{{ $tx->created_at?->format('M j, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-admin.empty icon="wallet" title="No wallet activity" /></td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
            {{ $transactions->links('admin.partials.pagination') }}
        </div></x-admin.card>

        <x-admin.card title="Largest balances">
            @forelse ($topHolders as $u)
                <div class="flex items-center justify-between gap-3 border-t border-ink/[.05] py-2.5 first:border-t-0 first:pt-0">
                    <x-admin.user-cell :user="$u" /><span class="font-medium tabular">{{ Money::usd($u->wallet_balance_minor) }}</span>
                </div>
            @empty
                <p class="text-[13px] text-muted">No funded wallets.</p>
            @endforelse
        </x-admin.card>
    </div>
</x-admin.layout>
