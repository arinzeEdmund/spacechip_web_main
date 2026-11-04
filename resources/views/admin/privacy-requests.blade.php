<x-admin.layout title="Privacy requests" subtitle="Access, deletion and correction requests. Each has a legal deadline.">
    <x-admin.card :padding="false"><div class="p-5 pt-3">
        <table class="ad-table">
            <thead><tr><th>#</th><th>Who</th><th>Request</th><th>Due</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($requests as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>{{ $r->email }}@if ($r->user)<br><small>user #{{ $r->user_id }}</small>@else<br><small>no account found</small>@endif</td>
                    <td>{{ \App\Models\PrivacyRequest::TYPES[$r->type] ?? $r->type }}@if ($r->details)<br><small>{{ \Illuminate\Support\Str::limit($r->details, 160) }}</small>@endif</td>
                    <td @if ($r->isOverdue()) style="color:#dc2626;font-weight:700" @endif>{{ $r->due_at->format('M j, Y') }}@if ($r->isOverdue()) · overdue @endif</td>
                    <td>{{ ucfirst($r->status) }}@if ($r->resolution)<br><small>{{ \Illuminate\Support\Str::limit($r->resolution, 100) }}</small>@endif</td>
                    <td class="text-right">
                        @unless ($r->completed_at)
                            <form method="POST" action="{{ route('admin.privacy-requests.complete', $r) }}" class="flex gap-2 items-center justify-end">
                                @csrf
                                <select name="status" class="ad-input" style="width:120px"><option value="completed">Completed</option><option value="rejected">Rejected</option></select>
                                <input name="resolution" class="ad-input" placeholder="What was done" required style="width:200px">
                                <x-admin.button type="submit" variant="primary" size="sm">Close</x-admin.button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-admin.empty icon="shield-check" title="No privacy requests" /></td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $requests->links('admin.partials.pagination') }}
    </div></x-admin.card>
</x-admin.layout>
