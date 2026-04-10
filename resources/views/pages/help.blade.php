<x-public-layout>
    <div class="space-y-6">
        <div>
            <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-[#0b1a1a]">Help Center</div>
            <p class="mt-2 text-sm sm:text-base text-gray-600">Quick answers for purchasing, activation, and troubleshooting.</p>
        </div>

        <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
            <div class="text-xs font-extrabold uppercase tracking-wider text-gray-500">Getting started</div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <a class="rounded-xl border border-black/5 bg-white/70 px-4 py-3 text-sm font-semibold text-[#145454] hover:text-[#f27457] transition-colors" href="{{ route('esim.guide') }}">How to install an eSIM</a>
                <a class="rounded-xl border border-black/5 bg-white/70 px-4 py-3 text-sm font-semibold text-[#145454] hover:text-[#f27457] transition-colors" href="{{ route('contact') }}">Contact support</a>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="font-extrabold text-[#145454]">I paid successfully but my eSIM is not showing</div>
                <div class="mt-2 text-sm text-gray-700">Open your dashboard and wait a few seconds. If the eSIM is still missing, contact support with your Paystack reference or Cryptomus order ID.</div>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="font-extrabold text-[#145454]">QR code says “No QR”</div>
                <div class="mt-2 text-sm text-gray-700">Some providers may delay LPA/QR details. Refresh the dashboard. If the eSIM shows ICCID but no LPA after a few minutes, contact support.</div>
            </div>

            <div class="rounded-2xl border border-black/5 bg-white/60 p-5">
                <div class="font-extrabold text-[#145454]">How do I install manually?</div>
                <div class="mt-2 text-sm text-gray-700">Use the LPA string if your phone supports manual entry. The full steps are in the <a class="font-semibold text-[#145454] hover:text-[#f27457] transition-colors" href="{{ route('esim.guide') }}">eSIM Guide</a>.</div>
            </div>
        </div>
    </div>
</x-public-layout>
