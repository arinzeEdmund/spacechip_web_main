<button {{ $attributes->merge(['type' => 'submit', 'class' => 'sc-btn sc-btn--danger']) }}>
    {{ $slot }}
</button>
