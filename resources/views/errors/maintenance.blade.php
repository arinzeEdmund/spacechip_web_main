<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'spacechip') }} · Back soon</title>
    @include('partials.theme-script')
    <link rel="stylesheet" href="{{ asset('css/spacechip-palette.css') }}">
    <link rel="stylesheet" href="{{ asset('css/spacechip-theme.css') }}">
    @include('partials.tailwind')
</head>
<body class="min-h-screen grid place-items-center bg-bg text-text antialiased" style="font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif">
    <div class="fixed inset-0 -z-10" style="background:radial-gradient(700px 420px at 15% 10%,rgb(var(--accent-rgb) / .14),transparent 60%)"></div>
    <main class="mx-6 max-w-md text-center">
        <div class="mb-8 flex justify-center text-text">@include('partials.brand-wordmark', ['class' => 'h-8 w-auto'])</div>
        <h1 class="text-2xl font-bold tracking-tight">We’ll be right back</h1>
        <p class="mt-3 text-[15px] leading-relaxed text-muted">{{ $message }}</p>
    </main>
</body>
</html>
