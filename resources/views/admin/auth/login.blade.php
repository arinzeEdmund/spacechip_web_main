<x-guest-layout>
    <div style="text-align:center;margin-bottom:18px">
        <h1 style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent-text)">Admin console</h1>
        <div style="margin-top:6px;font-size:13px;color:var(--muted)">Staff only. This sign-in is separate from the customer app.</div>
    </div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('admin.login.attempt') }}">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-password-input id="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-6">
            <x-primary-button class="w-full justify-center">Continue</x-primary-button>
        </div>
        <p class="mt-6 text-center text-sm text-gray-500">Next you will enter a code from your authenticator app or one we email you.</p>
    </form>
</x-guest-layout>
