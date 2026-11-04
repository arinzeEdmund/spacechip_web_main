<x-public-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-text">Terms &amp; Conditions</h1>
            <p class="mt-2 text-sm sm:text-base text-muted">By creating an account or buying from {{ config('legal.company_name') }} ("spacechip", "we"), you agree to these terms, the <a class="font-semibold text-accent-text" href="{{ route('privacy') }}">Privacy Policy</a>, the <a class="font-semibold text-accent-text" href="{{ route('refund') }}">Refund Policy</a> and the <a class="font-semibold text-accent-text" href="{{ route('acceptable.use') }}">Acceptable Use Policy</a>.</p>
        </div>
        @include('pages.legal._draft', ['key' => 'terms'])

        <div class="space-y-5 text-sm text-muted leading-7">
            <div><div class="font-semibold text-text">1. Who we are</div>
                <div class="mt-2">{{ config('legal.company_name') }}@if (config('legal.company_number')), company no. {{ config('legal.company_number') }}@endif @if (config('legal.company_address')), {{ config('legal.company_address') }}@endif. Contact: <a class="font-semibold text-accent-text" href="mailto:{{ config('legal.support_email') }}">{{ config('legal.support_email') }}</a>.</div></div>
            <div><div class="font-semibold text-text">2. The service</div>
                <div class="mt-2">spacechip sells access to eSIM data plans, virtual phone numbers, and verification numbers (one-time and monthly), supplied through third-party network, telecom and payment providers. We are a reseller: coverage, speeds and number availability depend on those providers and on local networks, and we do not guarantee any particular result.</div></div>
            <div><div class="font-semibold text-text">3. Your account</div>
                <div class="mt-2">You must be at least 18 and able to enter a contract. Give accurate details, keep your password secret, and tell us if you suspect unauthorised use. You are responsible for activity on your account. One person, one account.</div></div>
            <div><div class="font-semibold text-text">4. Devices and activation</div>
                <div class="mt-2">You must check that your device supports eSIM and is network-unlocked. Plan validity starts as described on the plan. Installation steps vary by device.</div></div>
            <div><div class="font-semibold text-text">5. Prices and payment</div>
                <div class="mt-2">Prices are shown in USD (or your local currency at checkout, converted at the displayed rate) before you pay and include any fees we charge; your bank may add its own. Wallet top-ups are held as a balance usable only on spacechip. Monthly rentals and subscriptions renew automatically from your wallet until you cancel; we tell you the amount and the renewal date.</div></div>
            <div><div class="font-semibold text-text">6. Immediate delivery and refunds</div>
                <div class="mt-2">Services are delivered immediately. Where a statutory right to withdraw applies, you agree at checkout that delivery may start straight away and that the right ends once delivered. Refunds follow the <a class="font-semibold text-accent-text" href="{{ route('refund') }}">Refund Policy</a>.</div></div>
            <div><div class="font-semibold text-text">7. Numbers and verification codes</div>
                <div class="mt-2">Numbers are rented, not owned, and may be recycled after they are released. We cannot promise that any app or website will accept a number or keep an account open. Use numbers lawfully and follow the <a class="font-semibold text-accent-text" href="{{ route('acceptable.use') }}">Acceptable Use Policy</a>.</div></div>
            <div><div class="font-semibold text-text">8. Suspension and ending</div>
                <div class="mt-2">We may suspend or close an account, and cancel numbers or plans, if you break these terms, abuse the service, appear in sanctions lists or are in a country where we are legally unable to serve you, or if the law requires. You can close your account at any time from the app or the <a class="font-semibold text-accent-text" href="{{ route('delete.account') }}">delete account</a> page.</div></div>
            <div><div class="font-semibold text-text">9. Sanctions and export rules</div>
                <div class="mt-2">You may not use the service from, or for people in, countries or regions under comprehensive sanctions, or if you are on a sanctions list. We may block sign-ups and payments from those places.</div></div>
            <div><div class="font-semibold text-text">10. Availability and liability</div>
                <div class="mt-2">We try to keep the service available but do not promise it will be uninterrupted or error-free. To the extent the law allows, we are not liable for indirect or consequential loss, for loss caused by third-party providers, or for blocked accounts on other platforms, and our total liability for any claim is limited to the amount you paid for the service concerned in the 12 months before it. Nothing here limits liability that cannot be limited by law, including for death or personal injury caused by negligence, or fraud, or your statutory consumer rights.</div></div>
            <div><div class="font-semibold text-text">11. Changes</div>
                <div class="mt-2">We may update these terms. For material changes we will notify you in the app or by email, and continued use after the change takes effect means you accept it. Current version: {{ config('legal.versions.terms') }}.</div></div>
            <div><div class="font-semibold text-text">12. Governing law and disputes</div>
                <div class="mt-2">@if (config('legal.governing_law')) These terms are governed by the laws of {{ config('legal.governing_law') }}. @endif Your mandatory consumer protections in the country where you live are not affected. Please contact us first so we can try to resolve a problem.</div></div>
        </div>
    </div>
</x-public-layout>
