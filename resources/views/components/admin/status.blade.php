@props(['value'])
@php
    $v = strtolower((string) $value);
    [$tone, $label, $pulse] = match (true) {
        in_array($v, ['fulfilled', 'received', 'finished', 'success', 'successful', 'active', 'completed'], true) => ['emerald', null, $v === 'active'],
        in_array($v, ['paid', 'paid_pending_fulfillment', 'pending', 'processing', 'waiting'], true) => ['amber', $v === 'paid_pending_fulfillment' ? 'Paid · pending eSIM' : null, true],
        in_array($v, ['past_due'], true) => ['amber', 'Past due', false],
        $v === 'initialized' => ['slate', 'Abandoned checkout', false],
        in_array($v, ['refunded', 'failed_refunded'], true) => ['violet', $v === 'failed_refunded' ? 'Failed · refunded' : null, false],
        str_starts_with($v, 'failed') || in_array($v, ['banned', 'revoked'], true) => ['red', $v === 'failed_insufficient_wallet' ? 'Failed · low balance' : null, false],
        in_array($v, ['timeout', 'canceled', 'cancelled', 'expired'], true) => ['slate', null, false],
        default => ['sky', null, false],
    };
@endphp
<x-admin.badge :tone="$tone" dot :pulse="$pulse">{{ $label ?? ucfirst(str_replace('_', ' ', $v ?: 'unknown')) }}</x-admin.badge>
