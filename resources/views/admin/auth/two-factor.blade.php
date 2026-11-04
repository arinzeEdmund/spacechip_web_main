<x-guest-layout>
    <div style="text-align:center;margin-bottom:18px">
        <h1 style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent-text)">Two-step verification</h1>
        <div style="margin-top:6px;font-size:14px;color:var(--muted)">
            Enter a 6-digit code from
            @if ($hasAuthenticator) your authenticator app <strong>or</strong> the one we emailed to {{ $emailMasked }}.
            @else the email we sent to {{ $emailMasked }}.
            @endif
        </div>
    </div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('admin.two-factor.verify') }}">
        @csrf
        <x-input-label for="code" value="6-digit code" />
        <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="9" autocomplete="one-time-code" required autofocus style="letter-spacing:.3em;text-align:center;font-size:20px" />
        <x-input-error :messages="$errors->get('code')" class="mt-2" />
        <div class="mt-6"><x-primary-button class="w-full justify-center">Verify and sign in</x-primary-button></div>
    </form>
    <form method="POST" action="{{ route('admin.two-factor.email') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900">Email me a code instead</button>
    </form>
    <p class="mt-4 text-center text-sm"><a class="underline text-gray-500" href="{{ route('admin.login') }}">Start over</a></p>
</x-guest-layout>
