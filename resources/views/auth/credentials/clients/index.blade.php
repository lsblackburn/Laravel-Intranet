<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('List of Clients') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-[var(--color-background2)]">
                <table class="w-full text-left">
                    <caption class="sr-only">Clients and their active status</caption>
                    <thead class="border-b border-gray-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">Name</th>
                            <th scope="col" class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($clients as $client)
                            <tr>
                                <td class="px-5 py-4">{{ $client->name }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-4">
                                        <a class="underline" href="{{ route('clients.edit', $client) }}" aria-label="Edit {{ $client->name }}">Edit</a>
                                        <x-table-delete resource="clients" :record="$client" :label="$client->name">
                                            This permanently deletes the client and all {{ $client->projects_count }} of its projects, including their media records and category and viewer links. The client's logo record and industry links are also removed.
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

        </section>
        
    </main>

</x-app-layout>
