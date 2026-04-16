<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary inline-flex items-center px-6 py-3 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-[#f27457] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
