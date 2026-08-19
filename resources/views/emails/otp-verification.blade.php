<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email</title>
    <style>
        body { margin: 0; padding: 0; background: #f5f6f8; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        .wrapper { max-width: 520px; margin: 40px auto; padding: 0 16px; }
        .card { background: #ffffff; border-radius: 24px; padding: 40px 36px; box-shadow: 0 4px 24px rgba(15,31,31,.08); }
        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 32px; }
        .brand-logo { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(90deg, #f27457, #145454); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 14px; text-align: center; line-height: 40px; }
        .brand-name { font-weight: 800; color: rgba(20,84,84,.92); font-size: 16px; letter-spacing: .02em; }
        h1 { font-size: 22px; font-weight: 800; color: #0b1a1a; margin: 0 0 8px; }
        .sub { font-size: 15px; color: rgba(15,31,31,.62); margin: 0 0 32px; line-height: 1.5; }
        .otp-box { background: linear-gradient(135deg, rgba(242,116,87,.06), rgba(20,84,84,.06)); border: 1px solid rgba(20,84,84,.12); border-radius: 18px; padding: 28px; text-align: center; margin-bottom: 28px; }
        .otp-label { font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: rgba(15,31,31,.45); margin-bottom: 10px; }
        .otp-code { font-size: 46px; font-weight: 900; letter-spacing: .22em; color: #0b1a1a; font-variant-numeric: tabular-nums; }
        .expiry { font-size: 13px; color: rgba(15,31,31,.5); text-align: center; margin-bottom: 28px; }
        .expiry strong { color: rgba(242,116,87,.92); }
        .divider { border: none; border-top: 1px solid rgba(15,31,31,.08); margin: 24px 0; }
        .footer { font-size: 13px; color: rgba(15,31,31,.45); text-align: center; line-height: 1.6; }
        .footer a { color: rgba(20,84,84,.8); text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="brand">
                <div class="brand-logo">SC</div>
                <div class="brand-name">spacechip</div>
            </div>

            <h1>Verify your email</h1>
            <p class="sub">Hi {{ $userName }}, enter the code below in the app to verify your email address and complete your registration.</p>

            <div class="otp-box">
                <div class="otp-label">Your verification code</div>
                <div class="otp-code">{{ $otp }}</div>
            </div>

            <p class="expiry">This code expires in <strong>10 minutes</strong>.</p>

            <hr class="divider">

            <p class="footer">
                If you didn't create a {{ config('app.name') }} account, you can safely ignore this email.<br><br>
                &copy; {{ date('Y') }} {{ config('app.name') }} &mdash; <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
