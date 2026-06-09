@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 dark:border-white/10 bg-white dark:bg-slate-800 text-gray-900 dark:text-gray-100 focus:border-[#f27457] focus:ring-[#f27457] rounded-xl shadow-sm py-3 px-4 transition-colors']) }}>
