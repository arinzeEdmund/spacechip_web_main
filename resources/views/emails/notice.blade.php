{{-- Shared transactional email (App\Mail\NoticeMail). Inline styles only: most mail clients strip <style>. --}}
@php
    $muted = 'rgba(0,0,0,.55)';
    $hairline = '1px solid rgba(0,0,0,.08)';
    $rows = array_filter($rows, fn ($v) => filled($v));
    $chips = array_filter($chips, fn ($v) => filled($v));
    $links = array_filter($links, fn ($v) => filled($v));
@endphp
<div style="font-family:'Instrument Sans',Arial,sans-serif;background:#f4f5f4;padding:24px;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:18px;border:{{ $hairline }};overflow:hidden;">
        <div style="padding:18px 20px;background:linear-gradient(90deg,#1f7a1f,#0b2e0b);color:#ffffff;">
            <div style="font-weight:900;letter-spacing:.02em;">{{ config('app.name', 'Spacechip') }}</div>
            <div style="opacity:.9;margin-top:6px;">{{ $kicker }}</div>
        </div>

        <div style="padding:18px 20px;color:#0a0a0a;">
            @if ($heading)
                <div style="font-weight:800;font-size:16px;margin-bottom:10px;">{{ $heading }}</div>
            @endif

            @if ($chips)
                <div style="margin-bottom:14px;">
                    @foreach ($chips as $label => $value)
                        <span style="display:inline-block;margin:0 6px 6px 0;padding:8px 10px;border-radius:12px;background:rgba(31,122,31,.07);border:1px solid rgba(31,122,31,.14);font-weight:700;font-size:12px;">{{ $label }}: {{ $value }}</span>
                    @endforeach
                </div>
            @endif

            @foreach ($rows as $label => $value)
                <div style="margin-bottom:8px;"><span style="color:{{ $muted }};font-weight:700;">{{ $label }}:</span> {{ $value }}</div>
            @endforeach

            @foreach ($links as $label => $url)
                <div style="margin-bottom:8px;"><span style="color:{{ $muted }};font-weight:700;">{{ $label }}:</span> <a href="{{ $url }}" style="color:#1f7a1f;font-weight:800;">Open {{ $label }}</a></div>
            @endforeach

            @if (filled($quote))
                <div style="margin-top:12px;padding:12px 14px;border-radius:14px;background:rgba(31,122,31,.06);border:1px solid rgba(31,122,31,.12);white-space:pre-wrap;">{{ $quote }}</div>
            @endif

            @if (filled($note))
                <div style="margin-top:10px;color:{{ $muted }};font-size:13px;font-weight:700;">{{ $note }}</div>
            @endif

            @if ($imageDataUrl)
                <div style="margin-top:12px;">
                    <img alt="eSIM QR code" src="{{ $imageDataUrl }}" style="width:220px;max-width:100%;border-radius:14px;border:{{ $hairline }};background:#ffffff;padding:10px;">
                </div>
            @endif

            @if ($action)
                <div style="margin:20px 0;">
                    <a href="{{ $action['url'] }}" style="display:inline-block;padding:12px 24px;background:#1f7a1f;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:800;">{{ $action['label'] }}</a>
                </div>
            @endif

            @if (filled($footnote))
                <div style="margin-top:14px;padding-top:14px;border-top:{{ $hairline }};color:{{ $muted }};font-size:13px;line-height:1.5;">{{ $footnote }}</div>
            @endif
        </div>
    </div>
</div>
