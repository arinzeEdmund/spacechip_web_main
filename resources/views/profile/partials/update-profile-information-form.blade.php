<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}" class="space-y-5">
    @csrf
    @method('patch')

    <div class="space-y-2">
        <x-input-label for="name" :value="__('Full name')" />
        <x-text-input id="name" name="name" type="text" class="block w-full" :value="old('name', $user->name)" required autocomplete="name" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="space-y-2">
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" class="block w-full" :value="old('email', $user->email)" required autocomplete="username" />
        <x-input-error :messages="$errors->get('email')" />

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="scp-note">
                {{ __('Your email address is unverified.') }}
                <button form="send-verification" class="scp-link">{{ __('Resend verification email') }}</button>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-1 font-medium text-accent-text">{{ __('A new verification link has been sent to your email address.') }}</p>
                @endif
            </div>
        @endif
    </div>

    <div class="scp-actions">
        <x-primary-button>{{ __('Save changes') }}</x-primary-button>

        @if (session('status') === 'profile-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2400)" class="scp-saved">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>
                {{ __('Saved') }}
            </p>
        @endif
    </div>
</form>
