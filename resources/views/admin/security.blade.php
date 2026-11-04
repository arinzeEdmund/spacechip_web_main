<x-admin.layout title="My security" subtitle="Protect your admin account. Either method below completes sign-in.">
    <div class="space-y-6">
        <x-admin.card title="Emailed code" subtitle="Always available">
            <p class="ad-row-help">Every sign-in can use a 6-digit code emailed to <b>{{ auth()->user()->email }}</b>. It expires after 10 minutes and works once.</p>
        </x-admin.card>

        <x-admin.card title="Authenticator app" :subtitle="$enrolled ? 'Enabled' : 'Not set up'">
            @if ($enrolled)
                <p class="ad-row-help">Enabled {{ $enrolledAt?->diffForHumans() }}. At sign-in you can use the app <b>or</b> an emailed code.</p>
                <form method="POST" action="{{ route('admin.security.disable') }}" class="mt-4" data-confirm="Remove the authenticator app? Sign-in will use emailed codes only.">
                    @csrf
                    <div class="flex gap-3 items-end">
                        <x-admin.input type="password" name="password" placeholder="Confirm your password" autocomplete="current-password" style="width:260px" />
                        <x-admin.button type="submit" variant="danger">Remove authenticator</x-admin.button>
                    </div>
                    @error('password')<div class="text-sm mt-2" style="color:#dc2626">{{ $message }}</div>@enderror
                </form>
            @elseif ($pendingSecret)
                <p class="ad-row-help">Scan this with Google Authenticator, Microsoft Authenticator, 1Password, Authy or similar, then enter the 6-digit code it shows.</p>
                <div class="flex flex-wrap gap-6 mt-4 items-center">
                    <div style="background:#fff;padding:10px;border-radius:12px">{!! $qrSvg !!}</div>
                    <div>
                        <div class="ad-row-help">Can't scan? Enter this key manually:</div>
                        <code style="font-size:15px;letter-spacing:.06em;word-break:break-all">{{ chunk_split($pendingSecret, 4, ' ') }}</code>
                        <form method="POST" action="{{ route('admin.security.confirm') }}" class="mt-4 flex gap-3 items-end">
                            @csrf
                            <x-admin.input name="code" inputmode="numeric" maxlength="9" placeholder="123456" autocomplete="one-time-code" style="width:160px" />
                            <x-admin.button type="submit" variant="primary">Turn on</x-admin.button>
                        </form>
                        @error('code')<div class="text-sm mt-2" style="color:#dc2626">{{ $message }}</div>@enderror
                    </div>
                </div>
            @else
                <p class="ad-row-help">Optional but recommended: codes from an app work offline and arrive instantly.</p>
                <form method="POST" action="{{ route('admin.security.start') }}" class="mt-4">@csrf<x-admin.button type="submit" variant="primary">Set up authenticator app</x-admin.button></form>
            @endif
        </x-admin.card>
    </div>
</x-admin.layout>
