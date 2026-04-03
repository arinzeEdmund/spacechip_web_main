@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-[#f27457] focus:ring-[#f27457] rounded-xl shadow-sm py-3 px-4']) }}>
