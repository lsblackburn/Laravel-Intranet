@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[--color-text]']) }}>
    {{ $value ?? $slot }}
</label>
