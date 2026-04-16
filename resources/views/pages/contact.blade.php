<x-public-layout>
    <div class="space-y-6">
        <div>
            <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#0b1a1a]">Contact Us</div>
            <p class="mt-2 text-sm sm:text-base text-gray-600">Get help with purchases, activation, refunds, or account issues.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="text-xs font-extrabold uppercase tracking-wider text-gray-500">Email</div>
                <div class="mt-2 text-sm font-semibold text-[#145454]">
                    <a href="mailto:{{ config('mail.from.address', 'support@spacechip.com') }}" class="hover:text-[#f27457] transition-colors">
                        {{ config('mail.from.address', 'support@spacechip.com') }}
                    </a>
                </div>
                <div class="mt-2 text-sm text-gray-600">Include your order reference and the ICCID (if available) for faster support.</div>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="text-xs font-extrabold uppercase tracking-wider text-gray-500">Help Center</div>
                <div class="mt-2 text-sm font-semibold text-[#145454]">
                    <a href="{{ route('help') }}" class="hover:text-[#f27457] transition-colors">Browse common questions</a>
                </div>
                <div class="mt-2 text-sm text-gray-600">Start with activation steps, QR install, and troubleshooting tips.</div>
            </div>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
            <div class="text-xs font-extrabold uppercase tracking-wider text-gray-500">What to send</div>
            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                <li>Reference: Paystack reference or Cryptomus order ID</li>
                <li>eSIM ID and ICCID (from your dashboard)</li>
                <li>Device model and OS version</li>
                <li>Screenshot of the error (if any)</li>
            </ul>
        </div>
    </div>
</x-public-layout>
