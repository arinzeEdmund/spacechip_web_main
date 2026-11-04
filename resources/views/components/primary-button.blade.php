<button {{ $attributes->merge(['type' => 'submit', 'class' => 'sc-btn sc-btn--primary btn-primary']) }}>
    {{ $slot }}
</button>
