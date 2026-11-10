<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Modify Project Types') }}
        </h1>
    </x-slot>

    <main class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            @include('admin.partials.add-project-types')
        </div>
    </main>

    <x-pagination :items="$types" />
</x-app-layout>
