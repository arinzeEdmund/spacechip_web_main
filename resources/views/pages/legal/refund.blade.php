<x-public-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-text">Refund &amp; cancellation policy</h1>
            <p class="mt-2 text-sm text-muted">What is refunded, what is not, and how to cancel.</p>
        </div>
        @include('pages.legal._draft', ['key' => 'refund'])
        <div class="space-y-5 text-sm text-muted leading-7">
            <div><div class="font-semibold text-text">Immediate delivery and the 14-day right</div>
                <div class="mt-2">eSIMs, verification numbers and numbers are digital services delivered straight away. At checkout you confirm you want delivery to start immediately and understand that, where a statutory 14-day right of withdrawal applies (for example in the EU and UK), you lose it once the service has been delivered. Nothing in this policy limits rights you cannot waive by law.</div></div>
            <div><div class="font-semibold text-text">Automatic refunds</div>
                <ul class="list-disc ml-6 mt-2 space-y-1">
                    <li><strong>One-time verification numbers:</strong> if no SMS arrives before the number expires, or you cancel before a code arrives, the full price returns to your wallet automatically. Once a code has been received there is no refund.</li>
                    <li><strong>eSIM orders:</strong> if we cannot deliver the eSIM after you pay, the payment is refunded automatically.</li>
                    <li><strong>Duplicate or failed payments:</strong> contact support with the order reference and we will correct it.</li>
                </ul></div>
            <div><div class="font-semibold text-text">Not refundable</div>
                <ul class="list-disc ml-6 mt-2 space-y-1">
                    <li>An eSIM that has been installed or activated, unless it has a technical fault we cannot fix or replace. Check device compatibility first using the eSIM guide.</li>
                    <li>Data already used, and plans that expired unused.</li>
                    <li>A verification code you received, even if the app or website you used it on later rejects it or restricts your account.</li>
                    <li>Time already used on a monthly rental or virtual number subscription.</li>
                </ul></div>
            <div><div class="font-semibold text-text">Cancelling monthly rentals and subscriptions</div>
                <div class="mt-2">Cancel any time in your account to stop auto-renewal. The number stays yours until the end of the period you have already paid for. We do not give partial refunds for the unused part of a period. Renewals are charged from your wallet shortly before the period ends; keep enough balance, and we email you if a renewal fails. A number whose renewal fails may be released and can later be assigned to someone else (see Acceptable use).</div></div>
            <div><div class="font-semibold text-text">Wallet balance</div>
                <div class="mt-2">Refunds for purchases paid from the wallet go back to the wallet. Wallet top-ups can be refunded to the original payment method on request while unused, subject to payment-provider rules and fraud checks.</div></div>
            <div><div class="font-semibold text-text">How to ask</div>
                <div class="mt-2">Email <a class="font-semibold text-accent-text" href="mailto:{{ config('legal.support_email') }}">{{ config('legal.support_email') }}</a> with your order reference. We aim to answer within 2 working days. If you paid by card you also keep your rights with your card issuer.</div></div>
        </div>
    </div>
</x-public-layout>
