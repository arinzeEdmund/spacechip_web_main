@props(['disabled' => false])

<div class="relative mt-1">
    <input
        @disabled($disabled)
        type="password"
        {{ $attributes->merge(['class' => 'block w-full border-gray-300 dark:border-white/10 bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100 focus:border-[#f27457] focus:ring-[#f27457] rounded-xl shadow-sm py-3 pl-4 pr-12 transition-colors']) }}
    >

    <button
        type="button"
        class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-gray-500 transition-colors hover:text-[#145454] focus:outline-none focus:ring-2 focus:ring-[#f27457] focus:ring-offset-2"
        aria-label="Show password"
        title="Show password"
        onclick="const input=this.parentElement.querySelector('input'); const isVisible=input.type==='text'; input.type=isVisible?'password':'text'; this.setAttribute('aria-label', isVisible?'Show password':'Hide password'); this.title=isVisible?'Show password':'Hide password'; this.querySelector('[data-password-show]').classList.toggle('hidden', !isVisible); this.querySelector('[data-password-hide]').classList.toggle('hidden', isVisible);"
    >
        <svg data-password-show xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2.06 12.35a1 1 0 0 1 0-.7C3.5 7.5 7.44 5 12 5s8.5 2.5 9.94 6.65a1 1 0 0 1 0 .7C20.5 16.5 16.56 19 12 19s-8.5-2.5-9.94-6.65Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
        <svg data-password-hide xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.56 0 8.5 2.5 9.94 6.65a1 1 0 0 1 0 .7 10.92 10.92 0 0 1-1.67 2.68" />
            <path d="M6.61 6.61A11 11 0 0 0 2.06 11.65a1 1 0 0 0 0 .7C3.5 16.5 7.44 19 12 19a10.4 10.4 0 0 0 5.39-1.48" />
            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
            <path d="M3 3l18 18" />
        </svg>
    </button>
</div>
