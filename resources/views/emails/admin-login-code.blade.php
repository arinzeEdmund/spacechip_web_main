<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Admin sign-in code</title></head>
<body style="margin:0;padding:24px;background:#f5f6f8;font-family:ui-sans-serif,system-ui,sans-serif">
<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:20px;padding:32px">
    <h1 style="margin:0 0 8px;font-size:20px;color:#0b1a1a">Admin sign-in code</h1>
    <p style="margin:0 0 20px;color:#555;line-height:1.5">Hi {{ $name }}, use this code to finish signing in to the {{ config('app.name') }} admin console.</p>
    <div style="text-align:center;font-size:40px;font-weight:800;letter-spacing:.2em;color:#0b1a1a;padding:18px;background:#f0f5f4;border-radius:14px">{{ $code }}</div>
    <p style="margin:20px 0 0;color:#777;font-size:13px;line-height:1.5">It expires in 10 minutes and works once.@if ($ip) The request came from IP {{ $ip }}.@endif If this was not you, someone knows your password: change it now and tell the team.</p>
</div></body></html>
