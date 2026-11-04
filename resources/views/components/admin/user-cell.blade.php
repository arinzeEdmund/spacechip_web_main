@props(['user'])
@if ($user)
    <a href="{{ route('admin.users.show', $user) }}" class="group" style="display:flex;min-width:0;align-items:center;gap:12px;text-decoration:none;color:inherit">
        <x-admin.avatar :user="$user" size="sm" />
        <span style="min-width:0">
            <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;color:var(--text)">{{ $user->name }}</span>
            <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--muted)">{{ $user->email }}</span>
        </span>
    </a>
@else
    <span style="color:var(--faint)">Deleted user</span>
@endif
