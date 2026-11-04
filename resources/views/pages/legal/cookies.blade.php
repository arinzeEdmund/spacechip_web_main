<x-public-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-text">Cookie policy</h1>
            <p class="mt-2 text-sm text-muted">What we store in your browser.</p>
        </div>
        @include('pages.legal._draft', ['key' => 'privacy'])
        <div class="space-y-5 text-sm text-muted leading-7">
            <div class="mt-2">We currently use only the storage the site needs to work. We do not run advertising or cross-site tracking cookies. If we add analytics in future, it will be optional and off until you opt in, and we will honour the Global Privacy Control signal from your browser.</div>
            <div class="overflow-x-auto"><table class="w-full text-left text-xs">
                <thead><tr class="text-text"><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Purpose</th><th class="py-2 pr-4">Type</th><th class="py-2">Lifetime</th></tr></thead>
                <tbody class="align-top">
                    <tr><td class="py-2 pr-4">spacechip session cookie</td><td class="py-2 pr-4">Keeps you signed in and protects forms</td><td class="py-2 pr-4">Essential</td><td class="py-2">Session / up to 2 hours idle</td></tr>
                    <tr><td class="py-2 pr-4">XSRF-TOKEN</td><td class="py-2 pr-4">Protects against cross-site request forgery</td><td class="py-2 pr-4">Essential</td><td class="py-2">Session</td></tr>
                    <tr><td class="py-2 pr-4">Theme preference (local storage)</td><td class="py-2 pr-4">Remembers light or dark mode</td><td class="py-2 pr-4">Preference</td><td class="py-2">Until cleared</td></tr>
                    <tr><td class="py-2 pr-4">Payment provider (Paystack, Cryptomus)</td><td class="py-2 pr-4">Set by the provider on its own checkout page to process and secure your payment</td><td class="py-2 pr-4">Essential</td><td class="py-2">Set by provider</td></tr>
                </tbody></table></div>
            <div>You can clear or block cookies in your browser settings; the site will not stay signed in without essential cookies.</div>
        </div>
    </div>
</x-public-layout>
