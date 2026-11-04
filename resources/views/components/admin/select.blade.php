@props(['options' => [], 'selected' => ''])
<select {{ $attributes->merge(['class' => 'ad-select']) }}>
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
