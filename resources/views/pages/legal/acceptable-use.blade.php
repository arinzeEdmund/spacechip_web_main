<x-public-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-text">Acceptable use policy</h1>
            <p class="mt-2 text-sm text-muted">The rules for using spacechip numbers and eSIMs.</p>
        </div>
        @include('pages.legal._draft', ['key' => 'acceptable_use'])
        <div class="space-y-5 text-sm text-muted leading-7">
            <div><div class="font-semibold text-text">What our numbers are for</div>
                <div class="mt-2">A second number for privacy, business and travel, and receiving verification codes for accounts that you are entitled to create. You are responsible for following the terms of every app and service you use a number with and the laws where you live.</div></div>
            <div><div class="font-semibold text-text">You must not</div>
                <ul class="list-disc ml-6 mt-2 space-y-1">
                    <li>Use numbers for fraud, scams, phishing, harassment, spam, or any unlawful purpose.</li>
                    <li>Create accounts in bulk, run account farms, evade bans or manipulate platforms, or resell numbers or codes.</li>
                    <li>Impersonate anyone, or use a number to bypass identity checks you are legally required to pass.</li>
                    <li>Use the service where telecom or sanctions law forbids it, or from a sanctioned country.</li>
                    <li>Attack, probe, scrape or overload the service, or try to access other people's data.</li>
                    <li>Share your account, or use another person's payment method without permission.</li>
                </ul></div>
            <div><div class="font-semibold text-text">Third-party rules</div>
                <div class="mt-2">Apps such as WhatsApp, Telegram and Google can refuse or later restrict accounts registered with a virtual number, and we cannot control that. Use of a number does not give you any right on those platforms.</div></div>
            <div><div class="font-semibold text-text">Number recycling</div>
                <div class="mt-2">Numbers belong to our suppliers and are rented to you. When a rental or subscription ends, or a renewal fails, the number is released and may later be given to someone else. Accounts tied to a released number may become inaccessible: move important accounts to a number you keep before cancelling, and watch the renewal reminders we email you.</div></div>
            <div><div class="font-semibold text-text">Messages and monitoring</div>
                <div class="mt-2">Incoming SMS content is stored encrypted and deleted after a fixed period (see the privacy policy). We do not read it except to provide support you ask for, prevent abuse, or comply with the law.</div></div>
            <div><div class="font-semibold text-text">Enforcement</div>
                <div class="mt-2">We may suspend or close accounts, cancel numbers without refund, and cooperate with lawful requests from authorities if we see abuse or are legally required to.</div></div>
            <div><div class="font-semibold text-text">Report abuse</div>
                <div class="mt-2">Email <a class="font-semibold text-accent-text" href="mailto:{{ config('legal.support_email') }}">{{ config('legal.support_email') }}</a>.</div></div>
        </div>
    </div>
</x-public-layout>
