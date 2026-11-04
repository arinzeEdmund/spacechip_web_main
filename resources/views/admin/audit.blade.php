<x-admin.layout title="Audit trail" subtitle="Every action taken by an admin, who took it and when.">
    <x-admin.card :padding="false"><div class="p-5">
        <x-admin.filters>
            <x-admin.select name="action" :selected="$filters['action']" :options="['' => 'All actions'] + $actions->mapWithKeys(fn ($a) => [$a => ucfirst(str_replace(['.', '_'], [' · ', ' '], $a))])->all()" />
        </x-admin.filters>
        <x-admin.table>
            <thead><tr><th>Admin</th><th>Action</th><th>Details</th><th>IP</th><th class="text-right">When</th></tr></thead>
            <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td><x-admin.user-cell :user="$e->admin" /></td>
                        <td><x-admin.badge :tone="str_contains($e->action, 'suspend') || str_contains($e->action, 'debit') || str_contains($e->action, 'cancel') ? 'red' : (str_starts_with($e->action, 'wallet') || str_contains($e->action, 'refund') ? 'amber' : 'teal')">{{ $e->action }}</x-admin.badge></td>
                        <td class="max-w-md">
                            <div>{{ $e->description }}</div>
                            @if ($e->subject_type === 'User' && $e->subject_id)<a href="{{ route('admin.users.show', $e->subject_id) }}" class="text-[12px] text-coral-600 hover:underline">View user</a>
                            @elseif ($e->subject_type === 'Payment' && $e->subject_id)<a href="{{ route('admin.payments.show', $e->subject_id) }}" class="text-[12px] text-coral-600 hover:underline">View payment</a>@endif
                        </td>
                        <td class="font-mono text-[12.5px] text-muted">{{ $e->ip_address }}</td>
                        <td class="whitespace-nowrap text-right text-muted">{{ $e->created_at?->format('M j, Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty icon="scroll-text" title="No admin actions yet" body="Suspensions, wallet adjustments, refunds and setting changes are all recorded here." /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        {{ $entries->links('admin.partials.pagination') }}
    </div></x-admin.card>
</x-admin.layout>
