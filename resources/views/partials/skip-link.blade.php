{{-- Lets keyboard and screen-reader users jump past the header. Visible only while focused. --}}
<a href="#main" class="skip-link">Skip to main content</a>
<style>
    .skip-link{position:absolute;left:12px;top:-60px;z-index:10000;padding:10px 16px;border-radius:10px;background:#111;color:#fff;font:600 14px/1 system-ui,sans-serif;text-decoration:none;transition:top .15s}
    .skip-link:focus{top:12px;outline:3px solid #ffbf47;outline-offset:2px}
    :focus-visible{outline:3px solid rgb(var(--accent-rgb, 20 120 90) / .9);outline-offset:2px}
</style>
