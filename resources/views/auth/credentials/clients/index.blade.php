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

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-[var(--color-background2)]">
                <table class="w-full text-left">
                    <caption class="sr-only">Clients and their active status</caption>
                    <thead class="border-b border-gray-200">
                        <tr>
                            <th scope="col" class="bg-[var(--color-primary)] text-(--color-background) px-5 py-4">Name</th>
                            <th scope="col" class="bg-[var(--color-primary)] text-(--color-background) px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="px-5 py-4">{{ $client->name }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-4">

                                        <x-primary-link class="bg-blue-500 hover:bg-blue-600 text-white" href="{{ route('clients.edit', $client) }}">View/Edit</x-primary-link>

                                        <x-table-delete resource="clients" :record="$client" :label="$client->name">
                                            This permanently deletes the client, and its associated credentials. History of the client and projects completed (e.g., financial reports, marketing campaigns, etc.) will remain in the system.
                                        </x-table-delete>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center">No clients yet. Add your first client to get started.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$clients" />

        </section>
        
    </main>

</x-app-layout>
