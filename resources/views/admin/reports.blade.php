<x-admin.layout title="Sales export" subtitle="Settled payments as CSV for your accountant (VAT / OSS / sales-tax evidence).">
    <x-admin.card title="Export">
        <form method="GET" action="{{ route('admin.reports.sales') }}" class="flex flex-wrap items-end gap-3">
            <div><div class="ad-row-help">From</div><input type="date" name="from" class="ad-input" value="{{ now()->startOfMonth()->subMonth()->toDateString() }}" required></div>
            <div><div class="ad-row-help">To</div><input type="date" name="to" class="ad-input" value="{{ now()->startOfMonth()->subDay()->toDateString() }}" required></div>
            <x-admin.button type="submit" variant="primary" icon="download">Download CSV</x-admin.button>
        </form>
        <p class="ad-row-help mt-4">One row per paid, fulfilled or refunded payment: date, reference, product, provider, currency, amount, status, the buyer's IP country at payment time, and user id. It does not calculate tax. Amounts are in each payment's own currency; card payments in naira show in NGN.</p>
    </x-admin.card>
</x-admin.layout>
