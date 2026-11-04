<form method="post" action="{{ route('password.update') }}" class="space-y-5">
    @csrf
    @method('put')

    <div class="space-y-2">
        <x-input-label for="update_password_current_password" :value="__('Current password')" />
        <x-password-input id="update_password_current_password" name="current_password" autocomplete="current-password" />
        <x-input-error :messages="$errors->updatePassword->get('current_password')" />
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="space-y-2">
            <x-input-label for="update_password_password" :value="__('New password')" />
            <x-password-input id="update_password_password" name="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
        </div>

        <div class="space-y-2">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm new password')" />
            <x-password-input id="update_password_password_confirmation" name="password_confirmation" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>
    </div>

    <div class="scp-actions">
        <x-primary-button>{{ __('Update password') }}</x-primary-button>

        @if (session('status') === 'password-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2400)" class="scp-saved">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>
                {{ __('Password updated') }}
            </p>
        @endif
    </div>
</form>
