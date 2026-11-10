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

                    </ul>

                </nav>

            @endif

            @if ($client)
                <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg rounded-tl-none">
            @endif

                <div class="overflow-x-auto rounded-xl border border-gray-200 bg-[var(--color-background2)]">
                    <table class="w-full text-left">
                        <caption class="sr-only">passwords and their active status</caption>
                        <thead class="border-b border-gray-200">
                            <tr>
                                <th scope="col" class="bg-[var(--color-primary)] text-(--color-background) px-5 py-4">Client</th>
                                <th scope="col" class="bg-[var(--color-primary)] text-(--color-background) px-5 py-4">Name</th>
                                <th scope="col" class="bg-[var(--color-primary)] text-(--color-background) px-5 py-4">Password</th>
                                <th scope="col" class="bg-[var(--color-primary)] text-(--color-background) px-5 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($passwords as $password)
                                <tr>
                                    <td class="px-5 py-4">{{ $password->client->name }}</td>
                                    <td class="px-5 py-4">{{ $password->name }}</td>
                                    <td class="px-5 py-4">{{ $password->password }}</td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-4">

                                            @if ($client)
                                                <x-primary-link class="bg-blue-500 hover:bg-blue-600 text-white" href="{{ route('passwords.edit', ['password' => $password, 'client_id' => $client->id]) }}">View/Edit</x-primary-link>
                                            @else
                                                <x-primary-link class="bg-blue-500 hover:bg-blue-600 text-white" href="{{ route('passwords.edit', $password) }}">View/Edit</x-primary-link>
                                            @endif

                                            <x-table-delete resource="passwords" :record="$password" :label="$password->name">
                                                This permanently deletes the password.
                                            </x-table-delete>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center">No passwords yet. Add your first client to get started.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @if ($client)
                </div>
            @endif

            <x-pagination :items="$passwords" />

        </section>
        
    </main>

</x-app-layout>
