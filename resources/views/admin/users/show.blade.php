@php use App\Support\Money; @endphp
<x-admin.layout :title="$user->name" :subtitle="'Customer #'.$user->id.' · joined '.$user->created_at?->format('M j, Y')">
    <x-slot:actions>
        <x-admin.button variant="primary" icon="wallet" data-open-dialog="walletDialog">Adjust wallet</x-admin.button>
        @if ($user->isSuspended())
            <form method="POST" action="{{ route('admin.users.unsuspend', $user) }}" data-confirm="Reinstate {{ $user->email }}? They will be able to sign in again.">@csrf<x-admin.button type="submit" icon="user-check">Reinstate</x-admin.button></form>
        @elseif (! $user->is(auth()->user()))
            <x-admin.button variant="danger" icon="ban" data-open-dialog="suspendDialog">Suspend</x-admin.button>
        @endif
    </x-slot:actions>

    @if ($user->isSuspended())
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-danger-soft px-4 py-3.5 text-[13.5px] text-danger ring-1 ring-danger/25">
            <i data-lucide="ban" class="mt-0.5 h-4 w-4"></i>
            <div><span class="font-medium">Suspended {{ $user->suspended_at->diffForHumans() }}.</span> {{ $user->suspension_reason }}</div>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Profile --}}
        <x-admin.card>
            <div class="flex items-center gap-4">
                <x-admin.avatar :user="$user" size="lg" />
                <div class="min-w-0">
                    <div class="truncate text-[16px] font-semibold">{{ $user->name }}</div>
                    <div class="truncate text-[13px] text-muted">{{ $user->email }}</div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if ($user->isSuspended())<x-admin.badge tone="red" dot>Suspended</x-admin.badge>
                        @elseif ($user->hasVerifiedEmail())<x-admin.badge tone="emerald" dot>Active</x-admin.badge>
                        @else<x-admin.badge tone="amber" dot>Email unverified</x-admin.badge>@endif
                        @if ($user->is_admin)<x-admin.badge tone="teal">Admin</x-admin.badge>@endif
                        @if ($user->google_id)<x-admin.badge>Google</x-admin.badge>@endif
                        @if ($user->facebook_id)<x-admin.badge>Facebook</x-admin.badge>@endif
                    </div>
                </div>
            </div>
            <dl class="mt-5">
                <x-admin.kv label="Phone">{{ $user->phone ?: '—' }}</x-admin.kv>
                <x-admin.kv label="Email verified">{{ $user->email_verified_at?->format('M j, Y H:i') ?? 'No' }}</x-admin.kv>
                <x-admin.kv label="Last sign-in">{{ $user->last_login_at ? $user->last_login_at->format('M j, Y H:i').' · '.$user->last_login_ip : 'Not recorded' }}</x-admin.kv>
                <x-admin.kv label="Active app sessions">{{ $tokens->count() }}</x-admin.kv>
            </dl>
            <div class="mt-5 grid grid-cols-2 gap-2">
                @unless ($user->hasVerifiedEmail())
                    <form method="POST" action="{{ route('admin.users.verify', $user) }}" data-confirm="Mark {{ $user->email }} as verified without a code?">@csrf<x-admin.button type="submit" icon="mail-check" class="w-full">Verify email</x-admin.button></form>
                @endunless
                <form method="POST" action="{{ route('admin.users.revoke-sessions', $user) }}" data-confirm="Sign {{ $user->name }} out of every device?">@csrf<x-admin.button type="submit" icon="log-out" class="w-full">Sign out devices</x-admin.button></form>
                @if (! $user->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.toggle-admin', $user) }}" data-confirm="{{ $user->is_admin ? 'Remove admin access from' : 'Give full admin access to' }} {{ $user->email }}?">@csrf
                        <x-admin.button type="submit" :icon="$user->is_admin ? 'shield-off' : 'shield-plus'" class="w-full">{{ $user->is_admin ? 'Revoke admin' : 'Make admin' }}</x-admin.button>
                    </form>
                @endif
            </div>
        </x-admin.card>

        {{-- Identity check (KYC) --}}
        <x-admin.card title="Identity check" subtitle="Used when KYC_THRESHOLD_USD is set. Verify ID outside the app, then record the decision here." class="xl:col-span-2">
            <form method="POST" action="{{ route('admin.users.kyc', $user) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <div class="ad-row-help">Status</div>
                    <select name="status" class="ad-input" style="width:160px">
                        @foreach (['none' => 'Not required', 'required' => 'Required', 'approved' => 'Approved'] as $v => $l)<option value="{{ $v }}" @selected($user->kyc_status === $v)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div><div class="ad-row-help">Note (what was checked)</div><input name="note" class="ad-input" maxlength="255" value="{{ $user->kyc_note }}" style="width:320px"></div>
                <x-admin.button type="submit" variant="primary">Save</x-admin.button>
                @if ($user->kyc_decided_at)<span class="ad-row-help">Last decision {{ $user->kyc_decided_at->diffForHumans() }}</span>@endif
            </form>
        </x-admin.card>

        {{-- Money --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:col-span-2">
            <x-admin.stat label="Wallet balance" :value="Money::usd($user->wallet_balance_minor)" icon="wallet" tone="emerald" />
            <x-admin.stat label="Lifetime spend" :value="Money::usd($lifetimeSpend)" icon="trending-up" tone="coral" hint="Settled USD purchases" />
            <x-admin.stat label="Payments" :value="$user->payments_count" icon="receipt" tone="teal" />
            <x-admin.stat label="Numbers" :value="$user->social_number_orders_count + $user->social_number_rentals_count + $user->virtual_number_subscriptions_count" icon="phone" tone="blue"
                          :hint="$user->virtual_number_subscriptions_count.' virtual · '.$user->social_number_orders_count.' one-time · '.$user->social_number_rentals_count.' rentals'" />
        </div>
    </div>

    {{-- Tabs --}}
    @php
        $tabs = ['payments' => ['Payments', $payments->count()], 'wallet' => ['Wallet', $wallet->count()], 'numbers' => ['Numbers', $virtualSubs->count() + $socialOrders->count() + $rentals->count()], 'security' => ['Security', $logins->count()], 'audit' => ['Admin history', $audit->count()]];
    @endphp
    <div class="mt-8 flex gap-1 overflow-x-auto border-b border-ink/[.07]" role="tablist">
        @foreach ($tabs as $key => [$label, $count])
            <button type="button" role="tab" data-tab="{{ $key }}" class="tab -mb-px flex items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-3.5 pb-3 text-[13.5px] text-muted hover:text-text">
                {{ $label }} <span class="rounded-full bg-surface-2 px-1.5 text-[11.5px] tabular text-muted">{{ $count }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-5">
        <section data-panel="payments">
            <x-admin.card :padding="false"><div class="p-5">
                <x-admin.table>
                    <thead><tr><th>#</th><th>Product</th><th>Provider</th><th>Status</th><th class="text-right">Amount</th><th class="text-right">Date</th></tr></thead>
                    <tbody>
                        @forelse ($payments as $p)
                            <tr class="cursor-pointer" onclick="location.href='{{ route('admin.payments.show', $p) }}'">
                                <td class="text-muted tabular">{{ $p->id }}</td>
                                <td class="font-medium">{{ $p->displayTitle() }} <span class="font-normal text-muted">· {{ $p->displaySubtitle() }}</span></td>
                                <td class="text-muted">{{ ucfirst($p->provider) }}</td>
                                <td><x-admin.status :value="$p->status" /></td>
                                <td class="text-right font-medium tabular">{{ Money::format($p->amount_minor, $p->currency) }}</td>
                                <td class="text-right text-muted">{{ $p->created_at?->format('M j, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty icon="receipt" title="No payments" /></td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div></x-admin.card>
        </section>

        <section data-panel="wallet" hidden>
            <x-admin.card :padding="false"><div class="p-5">
                <x-admin.table>
                    <thead><tr><th>Activity</th><th>Note</th><th class="text-right">Amount</th><th class="text-right">Balance after</th><th class="text-right">Date</th></tr></thead>
                    <tbody>
                        @forelse ($wallet as $tx)
                            <tr>
                                <td class="font-medium">{{ ucfirst(str_replace('_', ' ', $tx->action)) }}</td>
                                <td class="max-w-xs truncate text-muted">{{ data_get($tx->meta, 'reason') ?? data_get($tx->meta, 'reference') ?? '—' }}</td>
                                <td @class(['text-right font-medium tabular', 'text-success' => $tx->direction === 'credit'])>{{ $tx->direction === 'credit' ? '+' : '−' }}{{ Money::usd($tx->amount_minor) }}</td>
                                <td class="text-right tabular text-muted">{{ Money::usd($tx->balance_after_minor) }}</td>
                                <td class="text-right text-muted">{{ $tx->created_at?->format('M j, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty icon="wallet" title="No wallet activity" /></td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div></x-admin.card>
        </section>

        <section data-panel="numbers" hidden class="space-y-4">
            <x-admin.card title="Virtual numbers" :padding="false"><div class="p-5 pt-3">
                <x-admin.table>
                    <thead><tr><th>Number</th><th>Plan</th><th>Status</th><th>Renews</th><th class="text-right"></th></tr></thead>
                    <tbody>
                        @forelse ($virtualSubs as $s)
                            <tr>
                                <td class="font-medium tabular">{{ $s->phone_number ?: 'Not assigned' }}</td>
                                <td class="text-muted">{{ $s->product?->label ?? $s->country_iso }} · {{ Money::format($s->monthly_amount_minor, $s->currency) }}/mo</td>
                                <td><x-admin.status :value="$s->status" /></td>
                                <td class="text-muted">{{ $s->current_period_end?->format('M j, Y') ?? '—' }}</td>
                                <td class="text-right">@if ($s->status !== 'canceled')<form method="POST" action="{{ route('admin.numbers.virtual.cancel', $s) }}" data-confirm="Cancel this subscription and release {{ $s->phone_number }}? This cannot be undone.">@csrf<x-admin.button type="submit" variant="ghost">Cancel</x-admin.button></form>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No virtual numbers.</td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div></x-admin.card>
            <x-admin.card title="Verification numbers" :padding="false"><div class="p-5 pt-3">
                <x-admin.table>
                    <thead><tr><th>App</th><th>Number</th><th>Code</th><th>Status</th><th class="text-right">Price</th><th class="text-right">Date</th></tr></thead>
                    <tbody>
                        @forelse ($socialOrders as $o)
                            <tr>
                                <td class="font-medium">{{ $o->product_name }} <span class="font-normal text-muted">· {{ $o->country_name ?? $o->country }}</span></td>
                                <td class="tabular text-muted">{{ $o->phone ?: '—' }}</td>
                                <td class="font-mono text-[12.5px]">{{ $o->sms_code ?: '—' }}</td>
                                <td><x-admin.status :value="$o->status" /></td>
                                <td class="text-right tabular">{{ Money::usd($o->sell_amount_minor) }}</td>
                                <td class="text-right text-muted">{{ $o->created_at?->format('M j, H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">No verification numbers.</td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div></x-admin.card>
            <x-admin.card title="Rentals" :padding="false"><div class="p-5 pt-3">
                <x-admin.table>
                    <thead><tr><th>App</th><th>Number</th><th>Status</th><th>Period ends</th><th class="text-right">Monthly</th></tr></thead>
                    <tbody>
                        @forelse ($rentals as $r)
                            <tr>
                                <td class="font-medium">{{ $r->product_name }}</td>
                                <td class="tabular text-muted">{{ $r->phone ?: '—' }}</td>
                                <td><x-admin.status :value="$r->status" /></td>
                                <td class="text-muted">{{ $r->current_period_end?->format('M j, Y') ?? '—' }}</td>
                                <td class="text-right tabular">{{ Money::usd($r->monthly_amount_minor) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No rentals.</td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div></x-admin.card>
        </section>

        <section data-panel="security" hidden class="grid gap-4 xl:grid-cols-3">
            <x-admin.card class="xl:col-span-2" title="Sign-in history" :padding="false"><div class="p-5 pt-3">
                <x-admin.table>
                    <thead><tr><th>Result</th><th>Channel</th><th>Device</th><th>IP address</th><th class="text-right">When</th></tr></thead>
                    <tbody>
                        @forelse ($logins as $l)
                            <tr>
                                <td>@if ($l->successful)<x-admin.badge tone="emerald" dot>Success</x-admin.badge>@else<x-admin.badge tone="red" dot>Failed</x-admin.badge>@endif</td>
                                <td class="text-muted">{{ ucfirst($l->channel) }}</td>
                                <td class="text-muted">{{ $l->device }}</td>
                                <td class="font-mono text-[12.5px] text-muted">{{ $l->ip_address }}</td>
                                <td class="text-right text-muted">{{ $l->created_at?->format('M j, Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty icon="key-round" title="No sign-ins recorded yet" body="Sign-ins are recorded from now on." /></td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div></x-admin.card>
            <x-admin.card title="App sessions" subtitle="Mobile API tokens">
                @forelse ($tokens as $t)
                    <div class="flex items-center justify-between border-t border-ink/[.05] py-2.5 text-[13px] first:border-t-0 first:pt-0">
                        <span class="flex items-center gap-2"><i data-lucide="smartphone" class="h-4 w-4 text-faint"></i>{{ $t->name }}</span>
                        <span class="text-muted">{{ $t->last_used_at?->diffForHumans() ?? 'Never used' }}</span>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">Not signed in on any device.</p>
                @endforelse
            </x-admin.card>
        </section>

        <section data-panel="audit" hidden>
            <x-admin.card>
                @forelse ($audit as $a)
                    <div class="flex gap-3 border-t border-ink/[.05] py-3 first:border-t-0 first:pt-0">
                        <x-admin.avatar :user="$a->admin" size="sm" />
                        <div class="text-[13px]"><div>{{ $a->description }}</div><div class="text-[12px] text-faint">{{ $a->admin?->name ?? 'System' }} · {{ $a->created_at?->format('M j, Y H:i') }}</div></div>
                    </div>
                @empty
                    <x-admin.empty icon="scroll-text" title="No admin actions on this account" />
                @endforelse
            </x-admin.card>
        </section>
    </div>

    {{-- Dialogs --}}
    <x-admin.dialog id="walletDialog" title="Adjust wallet" description="Current balance {{ Money::usd($user->wallet_balance_minor) }}. Every adjustment is written to the ledger and audit trail.">
        <form method="POST" action="{{ route('admin.users.wallet', $user) }}" class="space-y-4 px-6 pb-6 pt-5">
            @csrf
            <div class="grid grid-cols-2 gap-2 rounded-xl bg-surface-2 p-1">
                <label class="cursor-pointer"><input type="radio" name="direction" value="credit" class="peer sr-only" checked><span class="block rounded-lg py-2 text-center text-[13px] font-medium text-muted peer-checked:bg-surface peer-checked:text-success peer-checked:shadow-sm">Add funds</span></label>
                <label class="cursor-pointer"><input type="radio" name="direction" value="debit" class="peer sr-only"><span class="block rounded-lg py-2 text-center text-[13px] font-medium text-muted peer-checked:bg-surface peer-checked:text-danger peer-checked:shadow-sm">Remove funds</span></label>
            </div>
            <label class="block"><span class="mb-1.5 block text-[12.5px] font-medium text-muted">Amount (USD)</span><x-admin.input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00" class="w-full" /></label>
            <label class="block"><span class="mb-1.5 block text-[12.5px] font-medium text-muted">Reason (visible in the ledger)</span><x-admin.input name="reason" required maxlength="255" placeholder="e.g. Goodwill credit for failed eSIM" class="w-full" /></label>
            <div class="flex justify-end gap-2 pt-1"><x-admin.button data-close-dialog variant="ghost">Cancel</x-admin.button><x-admin.button type="submit" variant="dark">Apply adjustment</x-admin.button></div>
        </form>
    </x-admin.dialog>

    <x-admin.dialog id="suspendDialog" title="Suspend {{ $user->name }}" description="They will be signed out everywhere and blocked from the website and mobile app until reinstated.">
        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-4 px-6 pb-6 pt-5">
            @csrf
            <label class="block"><span class="mb-1.5 block text-[12.5px] font-medium text-muted">Reason</span><x-admin.input name="reason" required maxlength="255" placeholder="e.g. Chargeback fraud" class="w-full" /></label>
            <div class="flex justify-end gap-2"><x-admin.button data-close-dialog variant="ghost">Cancel</x-admin.button><x-admin.button type="submit" variant="danger" icon="ban">Suspend account</x-admin.button></div>
        </form>
    </x-admin.dialog>

    @push('scripts')
    <script>
        (() => {
            const tabs = document.querySelectorAll('.tab');
            const show = (key) => {
                tabs.forEach((t) => {
                    const on = t.dataset.tab === key;
                    t.style.borderBottomColor = on ? 'var(--accent)' : 'transparent';
                    t.style.color = on ? 'var(--text)' : '';
                    t.style.fontWeight = on ? '500' : '';
                    t.setAttribute('aria-selected', on);
                });
                document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = false; p.style.display = p.dataset.panel === key ? '' : 'none'; });
                history.replaceState(null, '', '#' + key);
            };
            tabs.forEach((t) => t.addEventListener('click', () => show(t.dataset.tab)));
            show(location.hash.slice(1) || 'payments');
        })();
    </script>
    @endpush
</x-admin.layout>
