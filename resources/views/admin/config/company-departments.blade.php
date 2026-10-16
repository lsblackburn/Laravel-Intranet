<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Modify Company Departments') }}
        </h1>
    </x-slot>

    <main class="py-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            @include('admin.partials.add-user-department')
        </div>
    </main>

    <x-pagination :items="$departments" />
</x-app-layout>
