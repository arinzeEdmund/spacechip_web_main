<button {{ $attributes->merge(['type' => 'button', 'class' => 'sc-btn sc-btn--secondary']) }}>
    {{ $slot }}
</button>
