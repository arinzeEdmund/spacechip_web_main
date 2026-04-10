<x-public-layout>
    <div class="space-y-6">
        <div>
            <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#0b1a1a]">eSIM Guide</div>
            <p class="mt-2 text-sm sm:text-base text-gray-600">Install your eSIM using QR code or LPA details.</p>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
            <div class="font-extrabold text-[#145454]">What you need</div>
            <ul class="mt-3 space-y-2 text-sm text-gray-700">
                <li>An unlocked eSIM-compatible device</li>
                <li>Stable Wi‑Fi connection during installation</li>
                <li>QR code or LPA string from your dashboard</li>
            </ul>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="font-extrabold text-[#145454]">iPhone (iOS)</div>
                <ol class="mt-3 space-y-2 text-sm text-gray-700 list-decimal list-inside">
                    <li>Open Settings → Cellular (or Mobile Data)</li>
                    <li>Tap Add eSIM</li>
                    <li>Scan the QR code from your dashboard</li>
                    <li>If scanning fails, choose Enter Details Manually and paste the LPA string</li>
                </ol>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="font-extrabold text-[#145454]">Android</div>
                <ol class="mt-3 space-y-2 text-sm text-gray-700 list-decimal list-inside">
                    <li>Open Settings → Network &amp; Internet → SIMs</li>
                    <li>Tap Add SIM → Download a SIM instead</li>
                    <li>Scan the QR code from your dashboard</li>
                    <li>If available, use manual code entry and paste the LPA string</li>
                </ol>
            </div>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
            <div class="font-extrabold text-[#145454]">Troubleshooting</div>
            <div class="mt-2 text-sm text-gray-700">If installation fails, ensure your device is unlocked, your OS is updated, and you are connected to Wi‑Fi. If you still can’t install, contact support with your eSIM ICCID and order reference.</div>
            <div class="mt-3">
                <a href="{{ route('contact') }}" class="text-sm font-semibold text-[#145454] hover:text-[#f27457] transition-colors">Contact support</a>
            </div>
        </div>
    </div>
</x-public-layout>
