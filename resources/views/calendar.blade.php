<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Calendar') }}
        </h1>
    </x-slot>

    <x-full-calendar/>

</x-app-layout>
