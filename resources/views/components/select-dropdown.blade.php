@props([
    'disabled' => false,
    'value' => null,
    'options' => [],
    'placeholder' => null,
])

@php
    $name = $attributes->get('name');
    $selected = old($name, $value);
@endphp

<select @disabled($disabled)
    {{ $attributes->merge([
        'class' =>
            'bg-(--color-card) border-(--color-border) text-(--color-text) placeholder:text-(--color-surface-alt) focus:border-(--color-primary) focus:ring-(--color-primary) rounded-[7px] px-3 py-2.5 text-sm shadow-none',
    ]) }}>
    @if ($placeholder)
        <option value="" @selected($selected === null || $selected === '')>
            {{ $placeholder }}
        </option>
    @endif

    @foreach ($options as $optionValue => $optionLabel)
        @php
            if (is_object($optionLabel)) {
                $optionValue = $optionLabel->id;
                $optionLabel =
                    $optionLabel->name ?? ($optionLabel->department ?? ($optionLabel->label ?? $optionValue));
            }

            if (is_array($optionLabel)) {
                $optionValue = $optionLabel['value'] ?? $optionValue;
                $optionLabel =
                    $optionLabel['label'] ?? ($optionLabel['name'] ?? ($optionLabel['department'] ?? $optionValue));
            }
        @endphp

        <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>
            {{ $optionLabel }}
        </option>
    @endforeach

    {{ $slot }}
</select>
