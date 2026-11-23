<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('List of Clients') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <x-primary-link class="mb-5" href="{{ route('clients.create') }}">Add Client<span
                aria-hidden="true">&#8599;</span></x-primary-link>

            <x-ajax-list label="Clients">
                @include('credentials.clients.results')
            </x-ajax-list>

        </section>
        
    </main>

</x-app-layout>
