<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            @if ($client)
                {{ __('Passwords for') }} {{ $client->name }}
            @else
                {{ __('List of passwords') }}
            @endif
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            @if ($client)
                <x-primary-link class="mb-5" href="{{ route('passwords.create', ['client_id' => $client->id]) }}">Add Password<span
                    aria-hidden="true">&#8599;</span></x-primary-link>
            @else
                <x-primary-link class="mb-5" href="{{ route('passwords.create') }}">Add Password<span
                    aria-hidden="true">&#8599;</span></x-primary-link>
            @endif

            @if ($client)

                <nav>

                    <ul class="flex flex-row flex-wrap gap-4">

                        <li>

                            <a href="{{ route('clients.edit', $client) }}" class="block bg-(--color-card)/65 px-5 py-3 border-box rounded-t-lg transition-colors duration-200 hover:bg-(--color-card)/85">Client Details</a>

                        </li>

                        <li>

                            <a href="{{ route('passwords.index', ['client_id' => $client->id]) }}" class="block bg-(--color-card) px-5 py-3 border-box rounded-t-lg">Credentials</a>

                        </li>

                        <li>

                            <a href="{{ route('jobs.index', ['client_id' => $client->id]) }}" class="block bg-(--color-card)/65 px-5 py-3 border-box rounded-t-lg transition-colors duration-200 hover:bg-(--color-card)/85">Jobs</a>

                        </li>

                    </ul>

                </nav>

            @endif

            <x-ajax-list label="Passwords">
                @include('credentials.passwords.results')
            </x-ajax-list>

        </section>
        
    </main>

</x-app-layout>
