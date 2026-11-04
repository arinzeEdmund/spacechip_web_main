<x-public-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-text">Privacy request</h1>
            <p class="mt-2 text-sm text-muted">Ask to see, correct or delete your personal data, or to stop us using it in some way. You do not need to be signed in.</p>
        </div>
        @if (session('status'))
            <div class="rounded-xl border border-green-300/60 bg-green-50/70 dark:bg-green-500/10 px-4 py-3 text-sm text-text">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('privacy.request.store') }}" class="space-y-4 max-w-lg">
            @csrf
            <div>
                <x-input-label for="email" value="Email on your account" />
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', auth()->user()?->email)" required />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="type" value="What would you like?" />
                <select id="type" name="type" class="block mt-1 w-full rounded-md border-gray-300" required>
                    @foreach (\App\Models\PrivacyRequest::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="details" value="Details (optional)" />
                <textarea id="details" name="details" rows="4" maxlength="2000" class="block mt-1 w-full rounded-md border-gray-300">{{ old('details') }}</textarea>
            </div>
            <x-primary-button>Send request</x-primary-button>
        </form>
        <div class="text-xs text-muted leading-6">We will confirm it is really you before sharing or deleting anything, and we answer within {{ config('legal.privacy_request_days') }} days. Signed-in users can also delete their account directly from <a class="font-semibold text-accent-text" href="{{ route('delete.account') }}">Delete account</a>. Some records (payments, tax and fraud records) must be kept by law and cannot be deleted on request.</div>
    </div>
</x-public-layout>
