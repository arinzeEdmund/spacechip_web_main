@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border border-border bg-surface text-text placeholder:text-faint rounded-xl h-12 px-4 text-[15px] transition-colors duration-150 hover:border-border-strong focus:border-accent-strong focus:ring-[3px] focus:ring-accent-soft focus:outline-none aria-[invalid=true]:border-danger disabled:cursor-not-allowed disabled:opacity-50']) }}>
