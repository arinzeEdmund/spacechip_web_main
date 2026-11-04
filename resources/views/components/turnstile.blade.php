{{-- Cloudflare Turnstile widget. Renders only when TURNSTILE_SITE_KEY is configured. --}}
@if (config('security.turnstile.site_key'))
    <div class="mt-4">
        <div class="cf-turnstile" data-sitekey="{{ config('security.turnstile.site_key') }}" data-theme="auto"></div>
        <x-input-error :messages="$errors->get('captcha')" class="mt-2" />
    </div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
