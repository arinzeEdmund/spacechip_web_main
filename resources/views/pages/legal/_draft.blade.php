@unless (config('legal.reviewed'))
<div class="rounded-xl border border-amber-300/60 bg-amber-50/70 dark:bg-amber-500/10 px-4 py-3 text-xs text-muted">
    <strong class="text-text">Draft for legal review.</strong> This page describes how the service works today. It has not yet been reviewed by a lawyer in your country; the operator should have counsel confirm it before relying on it. Version {{ config('legal.versions.'.($key ?? 'terms')) }}.
</div>
@endunless
