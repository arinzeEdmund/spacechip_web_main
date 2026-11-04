<x-public-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-text">Accessibility statement</h1>
            <p class="mt-2 text-sm text-muted">We want spacechip to work for everyone.</p>
        </div>
        <div class="space-y-5 text-sm text-muted leading-7">
            <div>We aim to meet WCAG 2.2 level AA. What we have done so far: every public page has a skip-to-content link, a main landmark and a single page heading; form fields have labels; images have text alternatives; zooming is never disabled; keyboard focus is clearly visible; and animations respect the "reduce motion" setting. These structural checks run automatically on our public pages.</div>
            <div>What we have <strong>not</strong> yet done: a full manual audit with screen readers and a colour-contrast review of every screen, and testing of the signed-in dashboard and checkout with assistive technology. Some parts of the site and app may therefore still be hard to use.</div>
            <div>If something is hard to use, tell us at <a class="font-semibold text-accent-text" href="mailto:{{ config('legal.support_email') }}">{{ config('legal.support_email') }}</a> and we will try to fix it or give you another way to complete what you need. We aim to reply within 5 working days.</div>
            <div>Statement date: {{ config('legal.versions.terms') }}.</div>
        </div>
    </div>
</x-public-layout>
