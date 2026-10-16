@props([
    'disabled' => false,
    'value' => null,
])

<textarea @disabled($disabled)
    {{ $attributes->merge([
        'class' =>
            'bg-(--color-card) border-(--color-border) text-(--color-text) placeholder:text-(--color-surface-alt) focus:border-(--color-primary) focus:ring-(--color-primary) rounded-[7px] px-3 py-2.5 text-sm shadow-none',
    ]) }}>{{ old($attributes->get('name'), $value) }}</textarea>
