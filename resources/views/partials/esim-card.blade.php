{{--
    Compact eSIM card. Keep in sync with createEsimCard() in dashboard.blade.php,
    which renders the same markup for cards loaded after the first paint.
--}}
@php
    $esim = $item['esim'] ?? [];
    $bundle = $item['bundle'] ?? [];
    $status = match ($item['status'] ?? 'valid') { 'expired' => 'expired', 'processing' => 'processing', default => 'valid' };
    [$statusLabel, $statusTone] = ['valid' => ['Active', 'success'], 'expired' => ['Expired', ''], 'processing' => ['Processing', 'info']][$status];
    $title = trim((string) ($bundle['name'] ?? '')) ?: 'eSIM';
    $sub = implode(' · ', array_filter([(string) ($bundle['data'] ?? ''), (string) ($bundle['validity'] ?? '')]));
    $str = fn ($k) => trim((string) ($esim[$k] ?? ''));
    $iccid = $str('iccid');
    $qrDataUrl = $str('qr_code_data_url');
    $qrUrl = $str('qr_code_url');
    $iosUrl = $str('direct_installation_link_ios');
    $androidUrl = $str('direct_installation_link_android');
    $canRenew = $esim['can_renew'] ?? null;
    $expires = ! empty($item['expires_at']) ? \Illuminate\Support\Carbon::parse($item['expires_at']) : null;
    $topupUrl = $canRenew === true && trim((string) ($item['asset_type'] ?? '')) !== '' && trim((string) ($item['asset_id'] ?? '')) !== '' && $str('esim_id') !== ''
        ? url('/assets/'.rawurlencode((string) $item['asset_type']).'/'.rawurlencode((string) $item['asset_id']).'?topup_esim_id='.rawurlencode($str('esim_id')))
        : '';
    $details = array_filter([
        'SM-DP+ address' => $str('smdp_address'),
        'Activation code' => $str('activation_code'),
        'LPA string' => $str('lpa') ?: $str('qr_payload'),
        'ICCID' => $iccid,
        'eSIM ID' => $str('esim_id'),
        'PUK' => $str('puk_code'),
        'Number' => $str('number'),
        'Provider status' => $str('esim_status'),
        'Renewable' => $canRenew === null ? '' : ($canRenew ? 'Yes' : 'No'),
    ], fn ($v) => $v !== '');
    $copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
@endphp
<article class="esim-card" data-status="{{ $status }}">
    <div class="esim-head">
        <span class="esim-chip" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><rect x="8.5" y="11" width="7" height="6" rx="1.2"/></svg></span>
        <div class="esim-titles">
            <h3 class="esim-title">{{ $title }}</h3>
            @if ($sub !== '')<p class="esim-sub">{{ $sub }}</p>@endif
        </div>
        <span @class(['ui-pill', 'ui-pill--'.$statusTone => $statusTone, 'ui-pill--pulse' => $status === 'processing'])><span class="ui-pill__dot"></span>{{ $statusLabel }}</span>
    </div>

    <dl class="esim-facts">
        <div><dt>{{ $status === 'expired' ? 'Expired' : 'Expires' }}</dt><dd>{{ $expires ? $expires->format('M j, Y') : '—' }}</dd></div>
        <div><dt>ICCID</dt><dd>
            @if ($iccid !== '')
                <span class="ui-mono" title="{{ $iccid }}">•••• {{ substr($iccid, -6) }}</span>
                <button type="button" class="ui-copy" data-copy="{{ $iccid }}" data-copy-label="ICCID" aria-label="Copy ICCID">{!! $copyIcon !!}</button>
            @else
                <span>—</span>
            @endif
        </dd></div>
    </dl>

    @if ($iosUrl !== '' || $androidUrl !== '' || $qrUrl !== '' || $topupUrl !== '')
        <div class="esim-actions">
            @if ($iosUrl !== '')<a class="ui-btn ui-btn--primary ui-btn--sm" href="{{ $iosUrl }}" target="_blank" rel="noopener noreferrer">Install on iPhone</a>@endif
            @if ($androidUrl !== '')<a @class(['ui-btn ui-btn--sm', 'ui-btn--primary' => $iosUrl === '', 'ui-btn--secondary' => $iosUrl !== '']) href="{{ $androidUrl }}" target="_blank" rel="noopener noreferrer">Install on Android</a>@endif
            @if ($qrUrl !== '')<a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ $qrUrl }}" target="_blank" rel="noopener noreferrer">Open QR</a>@endif
            @if ($topupUrl !== '')<a class="ui-btn ui-btn--secondary ui-btn--sm" href="{{ $topupUrl }}">Top up</a>@endif
        </div>
    @endif

    @if ($details || $qrDataUrl !== '')
        <details class="ui-disclosure esim-more">
            <summary>Installation details</summary>
            <div class="ui-disclosure__body">
                @if ($qrDataUrl !== '')
                    <div class="esim-qr"><img alt="eSIM QR code for {{ $title }}" src="{{ $qrDataUrl }}"></div>
                @endif
                <dl class="ui-kv">
                    @foreach ($details as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd><span class="ui-mono" title="{{ $value }}">{{ $value }}</span>
                                @unless (in_array($label, ['Renewable', 'Provider status'], true))
                                    <button type="button" class="ui-copy" data-copy="{{ $value }}" data-copy-label="{{ $label }}" aria-label="Copy {{ $label }}">{!! $copyIcon !!}</button>
                                @endunless
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </details>
    @endif
</article>
