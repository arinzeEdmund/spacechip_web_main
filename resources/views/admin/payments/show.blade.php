@php use App\Support\Money; $f = (array) ($payment->fulfillment_payload ?? []); @endphp
<x-admin.layout :title="'Payment #'.$payment->id" :subtitle="$payment->provider_reference">
    <x-slot:actions>
        <x-admin.button href="{{ route('admin.payment', $payment) }}" icon="braces" target="_blank">Raw JSON</x-admin.button>
        @if ($canRetry)
            <form method="POST" action="{{ route('admin.payments.retry', $payment) }}" data-confirm="Ask the eSIM provider to provision this order again? Only use this when the customer has not received an eSIM.">@csrf<x-admin.button type="submit" variant="primary" icon="rotate-cw">Retry fulfilment</x-admin.button></form>
        @endif
        @if ($canRefund)<x-admin.button variant="danger" icon="undo-2" data-open-dialog="refundDialog">Refund to wallet</x-admin.button>@endif
    </x-slot:actions>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <x-admin.card>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-[12.5px] text-muted">{{ $payment->productTypeLabel() }} · paid via {{ ucfirst($payment->provider) }}</div>
                        <div class="mt-1 text-[22px] font-semibold tracking-tight">{{ $payment->displayTitle() }}</div>
                        <div class="mt-0.5 text-[13.5px] text-muted tabular">{{ $payment->displaySubtitle() }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[28px] font-semibold tracking-tight tabular">{{ Money::format($payment->amount_minor, $payment->currency) }}</div>
                        <div class="mt-1"><x-admin.status :value="$payment->status" /></div>
                    </div>
                </div>
                @if (! empty($f['error']))
                    <div class="mt-5 flex items-start gap-2.5 rounded-xl bg-danger-soft px-3.5 py-3 text-[13px] text-danger ring-1 ring-danger/25"><i data-lucide="circle-alert" class="mt-0.5 h-4 w-4"></i>{{ $f['error'] }}</div>
                @endif
            </x-admin.card>

            <x-admin.card title="Order details">
                <dl class="grid gap-x-8 sm:grid-cols-2">
                    <div>
                        <x-admin.kv label="Reference"><span class="font-mono text-[12.5px]">{{ $payment->provider_reference }}</span></x-admin.kv>
                        <x-admin.kv label="Asset">{{ $payment->asset_type }} · {{ $payment->asset_id }}</x-admin.kv>
                        <x-admin.kv label="Bundle"><span class="font-mono text-[12.5px]">{{ $payment->bundle_id }}</span></x-admin.kv>
                        <x-admin.kv label="Package type">{{ $payment->package_type }}</x-admin.kv>
                    </div>
                    <div>
                        @if ($payment->isEsim())
                            <x-admin.kv label="ICCID"><span class="font-mono text-[12.5px]">{{ $f['iccid'] ?? '—' }}</span></x-admin.kv>
                            <x-admin.kv label="Provider order">{{ $f['order_id'] ?? '—' }}</x-admin.kv>
                            <x-admin.kv label="eSIM ID">{{ $f['esim_id'] ?? '—' }}</x-admin.kv>
                            <x-admin.kv label="SM-DP+">{{ $f['smdp_address'] ?? '—' }}</x-admin.kv>
                        @else
                            <x-admin.kv label="Phone number">{{ ! empty($f['phone']) ? '+'.ltrim($f['phone'], '+') : '—' }}</x-admin.kv>
                            <x-admin.kv label="Provider order">{{ $f['provider_order_id'] ?? $f['order_id'] ?? '—' }}</x-admin.kv>
                            <x-admin.kv label="Internal order">{{ $f['order_id'] ?? '—' }}</x-admin.kv>
                        @endif
                    </div>
                </dl>
            </x-admin.card>

            <x-admin.card title="Provider payload" subtitle="What the payment provider sent us">
                <pre class="scrollbar-thin max-h-80 overflow-auto rounded-xl border border-border bg-black p-4 font-mono text-[12px] leading-relaxed text-white/80">{{ json_encode($payment->provider_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null' }}</pre>
            </x-admin.card>
            <x-admin.card title="Fulfilment payload" subtitle="What the product provider returned">
                <pre class="scrollbar-thin max-h-80 overflow-auto rounded-xl border border-border bg-black p-4 font-mono text-[12px] leading-relaxed text-white/80">{{ json_encode($payment->fulfillment_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null' }}</pre>
            </x-admin.card>
        </div>

        <div class="space-y-4">
            <x-admin.card title="Customer">
                <x-admin.user-cell :user="$payment->user" />
                @if ($payment->user)
                    <dl class="mt-4">
                        <x-admin.kv label="Wallet balance">{{ Money::usd($payment->user->wallet_balance_minor) }}</x-admin.kv>
                        <x-admin.kv label="Account">{{ $payment->user->isSuspended() ? 'Suspended' : 'Active' }}</x-admin.kv>
                    </dl>
                @endif
            </x-admin.card>
            <x-admin.card title="Timeline">
                <ol class="relative ml-1.5 border-l border-ink/[.08] pl-5 text-[13px]">
                    <li class="mb-4"><span class="absolute -left-[5px] mt-1 h-2.5 w-2.5 rounded-full bg-border-strong"></span><div class="font-medium">Checkout created</div><div class="text-muted">{{ $payment->created_at?->format('M j, Y H:i:s') }}</div></li>
                    @if (! empty($f['attempted_at']))
                        <li class="mb-4"><span @class(['absolute -left-[5px] mt-1 h-2.5 w-2.5 rounded-full', 'bg-emerald-500' => ($f['ok'] ?? false), 'bg-amber-500' => ! ($f['ok'] ?? false)])></span><div class="font-medium">Fulfilment {{ ($f['ok'] ?? false) ? 'succeeded' : 'attempted' }}{{ ! empty($f['retried_by_admin']) ? ' (admin retry)' : '' }}</div><div class="text-muted">{{ \Carbon\Carbon::parse($f['attempted_at'])->format('M j, Y H:i:s') }}</div></li>
                    @endif
                    <li><span class="absolute -left-[5px] mt-1 h-2.5 w-2.5 rounded-full bg-coral-500"></span><div class="font-medium">Last update · {{ ucfirst(str_replace('_', ' ', $payment->status)) }}</div><div class="text-muted">{{ $payment->updated_at?->format('M j, Y H:i:s') }}</div></li>
                </ol>
            </x-admin.card>
            <x-admin.card title="Wallet entries">
                @forelse ($walletTx as $tx)
                    <div class="flex items-center justify-between border-t border-ink/[.05] py-2.5 text-[13px] first:border-t-0 first:pt-0">
                        <span>{{ ucfirst(str_replace('_', ' ', $tx->action)) }}</span>
                        <span @class(['font-medium tabular', 'text-success' => $tx->direction === 'credit'])>{{ $tx->direction === 'credit' ? '+' : '−' }}{{ Money::usd($tx->amount_minor) }}</span>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">No wallet movements for this payment.</p>
                @endforelse
            </x-admin.card>
        </div>
    </div>

    @if ($canRefund)
        <x-admin.dialog id="refundDialog" title="Refund to wallet" description="Credits the customer’s Reserve wallet and marks this payment refunded. Card refunds must be issued in the Paystack dashboard.">
            <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="space-y-4 px-6 pb-6 pt-5">
                @csrf
                <label class="block"><span class="mb-1.5 block text-[12.5px] font-medium text-muted">Amount (USD)</span>
                    <x-admin.input type="number" name="amount" step="0.01" min="0.01" required class="w-full" value="{{ $payment->currency === 'USD' ? number_format($payment->amount_minor / 100, 2, '.', '') : '' }}" />
                    @if ($payment->currency !== 'USD')<span class="mt-1 block text-[12px] text-warning">Paid in {{ $payment->currency }}; enter the USD equivalent.</span>@endif
                </label>
                <label class="block"><span class="mb-1.5 block text-[12.5px] font-medium text-muted">Reason</span><x-admin.input name="reason" required maxlength="255" placeholder="e.g. eSIM never delivered" class="w-full" /></label>
                <div class="flex justify-end gap-2"><x-admin.button data-close-dialog variant="ghost">Cancel</x-admin.button><x-admin.button type="submit" variant="dark">Refund</x-admin.button></div>
            </form>
        </x-admin.dialog>
    @endif
</x-admin.layout>
