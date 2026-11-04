<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-password-input id="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-password-input id="password_confirmation"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-5">
            <label class="flex items-start gap-2 text-sm text-gray-600">
                <input type="checkbox" name="accept_terms" value="1" class="mt-1 rounded border-gray-300" @checked(old('accept_terms')) required>
                <span>I am 18 or older and agree to the <a class="underline" href="{{ route('terms') }}" target="_blank" rel="noopener">Terms</a>, <a class="underline" href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a> and <a class="underline" href="{{ route('acceptable.use') }}" target="_blank" rel="noopener">Acceptable Use Policy</a>.</span>
            </label>
            <x-input-error :messages="$errors->get('accept_terms')" class="mt-2" />
        </div>

        <x-turnstile />

        <div class="mt-6">
            <x-primary-button class="w-full justify-center">
                {{ __('Register') }}
            </x-primary-button>
        </div>

        <div class="mt-10 text-center text-sm text-gray-500">
            Already have an account?
            <a href="{{ route('login') }}" class="font-bold leading-6 text-accent-text hover:text-accent-text transition-colors">Log in</a>
        </div>
    </form>
</x-guest-layout>
