@php use App\Support\Money; @endphp
<x-admin.layout title="Users" subtitle="Every customer account, with status, balance and activity.">
    <nav class="ad-seg mb-5" aria-label="Filter users">
        @foreach (['all' => 'All', 'verified' => 'Verified', 'unverified' => 'Unverified', 'funded' => 'Has balance', 'suspended' => 'Suspended', 'admins' => 'Admins'] as $key => $label)
            <a href="{{ route('admin.users.index', array_filter(['filter' => $key === 'all' ? null : $key, 'q' => $q, 'sort' => $sort])) }}" @class(['is-active' => $filter === $key])>{{ $label }}<em>{{ $counts[$key] }}</em></a>
        @endforeach
    </nav>

    <x-admin.card :padding="false">
        <div class="p-5 pb-0">
            <x-admin.filters>
                <input type="hidden" name="filter" value="{{ $filter }}">
                <x-admin.input name="q" icon="search" value="{{ $q }}" placeholder="Name, email, phone or ID" />
                <x-admin.select name="sort" :selected="$sort" :options="['newest' => 'Newest first', 'oldest' => 'Oldest first', 'balance' => 'Highest balance', 'last_login' => 'Recently active']" />
                @if ($q !== '')<x-admin.button href="{{ route('admin.users.index', ['filter' => $filter]) }}" variant="ghost" icon="x">Clear</x-admin.button>@endif
            </x-admin.filters>
        </div>
        <div class="px-5 pb-5">
            <x-admin.table>
                <thead><tr><th>Customer</th><th>Status</th><th class="text-right">Wallet</th><th class="text-right">Orders</th><th>Last sign-in</th><th class="text-right">Joined</th></tr></thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr class="cursor-pointer" onclick="location.href='{{ route('admin.users.show', $u) }}'">
                            <td><x-admin.user-cell :user="$u" /></td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($u->isSuspended())<x-admin.badge tone="red" dot>Suspended</x-admin.badge>
                                    @elseif ($u->hasVerifiedEmail())<x-admin.badge tone="emerald" dot>Active</x-admin.badge>
                                    @else<x-admin.badge tone="amber" dot>Unverified</x-admin.badge>@endif
                                    @if ($u->is_admin)<x-admin.badge tone="teal">Admin</x-admin.badge>@endif
                                </div>
                            </td>
                            <td class="text-right font-medium tabular">{{ Money::usd($u->wallet_balance_minor) }}</td>
                            <td class="text-right tabular text-muted">{{ $u->payments_count + $u->social_number_orders_count + $u->virtual_number_subscriptions_count }}</td>
                            <td class="text-muted">{{ $u->last_login_at?->diffForHumans() ?? 'Never recorded' }}</td>
                            <td class="text-right text-muted">{{ $u->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-admin.empty icon="user-search" title="No users match" body="Try a different search or filter." /></td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
            {{ $users->links('admin.partials.pagination') }}
        </div>
    </x-admin.card>
</x-admin.layout>
