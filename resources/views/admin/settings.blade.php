<x-admin.layout title="Settings" subtitle="Control availability, features, pricing and alerts without a deploy.">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6" data-confirm="Apply these settings? Changes take effect immediately for every customer.">
        @csrf @method('PUT')
        @foreach ($groups as $group => $defs)
            <x-admin.card :title="$group" :subtitle="['Availability' => 'Take the app offline or pause sign-ups.', 'Features' => 'Turn individual products on or off. Existing orders are unaffected.', 'Pricing' => 'Overrides the values in .env. Leave blank to use the default.', 'Providers' => 'Choose which supplier sells new monthly rentals. Exactly one is always on.', 'Alerts' => 'Thresholds that drive dashboard warnings.'][$group] ?? null">
                <div class="ad-rows">
                    @foreach ($defs as $key => $def)
                        <div class="ad-row">
                            <div class="max-w-xl">
                                <div class="ad-row-title">{{ $def['label'] }}</div>
                                <div class="ad-row-help">{{ $def['help'] }}</div>
                            </div>
                            @if ($def['type'] === 'bool')
                                <label class="ad-switch" aria-label="{{ $def['label'] }}">
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked($values[$key])><i></i>
                                </label>
                            @elseif ($def['type'] === 'choice')
                                <div class="flex flex-col gap-3" data-exclusive-group>
                                    <input type="hidden" name="{{ $key }}" value="{{ $values[$key] }}" data-exclusive-value>
                                    @foreach ($def['options'] as $optValue => $optLabel)
                                        <label class="flex items-center justify-between gap-4" style="min-width:230px">
                                            <span class="ad-row-title">{{ $optLabel }}</span>
                                            <span class="ad-switch">
                                                <input type="checkbox" value="{{ $optValue }}" data-exclusive-option @checked($values[$key] === $optValue)><i></i>
                                            </span>
                                        </label>
                                    @endforeach
                                    @error($key)<div class="text-sm" style="color:#dc2626">{{ $message }}</div>@enderror
                                </div>
                            @elseif ($def['type'] === 'number')
                                <x-admin.input type="number" name="{{ $key }}" value="{{ $values[$key] }}" step="{{ $def['step'] ?? 'any' }}" min="{{ $def['min'] ?? '' }}" max="{{ $def['max'] ?? '' }}" style="width:140px;text-align:right" class="tabular" />
                            @else
                                <x-admin.input name="{{ $key }}" value="{{ $values[$key] }}" style="width:min(420px,100%)" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        @endforeach
        {{-- Save bar: docks to the bottom of the viewport, clear of the last row. --}}
        <div class="ad-savebar">
            <span><i data-lucide="info"></i>Changes apply to web and mobile immediately after saving.</span>
            <x-admin.button type="submit" variant="primary" icon="save">Save settings</x-admin.button>
        </div>
    </form>
    <script>
        // Radio-style switches: exactly one supplier is on; turning one on turns the other off.
        document.querySelectorAll('[data-exclusive-group]').forEach(function (group) {
            var hidden = group.querySelector('[data-exclusive-value]');
            var boxes = group.querySelectorAll('[data-exclusive-option]');
            boxes.forEach(function (box) {
                box.addEventListener('change', function () {
                    if (!box.checked) { box.checked = true; return; } // cannot turn both off
                    boxes.forEach(function (other) { if (other !== box) other.checked = false; });
                    hidden.value = box.value;
                });
            });
        });
    </script>
</x-admin.layout>
