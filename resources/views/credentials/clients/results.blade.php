            <div
                class="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] shadow-sm">
                <table class="min-w-full text-sm text-left">

                    <!-- Header -->
                    <thead
                        class="bg-[var(--color-surface-alt)] text-xs uppercase tracking-wider text-[var(--color-subtletext)]">
                        <tr>
                            <th class="px-6 py-4 font-medium">Name</th>
                            <th class="px-6 py-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>

                    <!-- Body -->
                    <tbody class="divide-y divide-[var(--color-border)] text-[var(--color-text)]">

                        @if ($clients->isEmpty())
                            <tr>
                                <td colspan="2" class="px-6 py-4 text-center text-sm text-[var(--color-subtletext)]">
                                    {{ request()->filled('search') ? 'No clients match your search.' : 'No clients yet. Add your first client to get started.' }}
                                </td>
                            </tr>
                        @else
                            @foreach ($clients as $client)
                                <tr class="hover:bg-[var(--color-surface-alt)] transition">

                                    <td class="px-6 py-4">
                                        {{ $client->name }}
                                    </td>

                                    <!-- Actions -->
                                    <td
                                        class="px-6 py-4 text-right text-sm text-[var(--color-subtletext)]">

                                        <div class="flex flex-wrap items-center justify-end gap-3">

                                            <x-primary-link href="{{ route('clients.edit', $client) }}">View/Edit</x-primary-link>

                                            <x-table-delete resource="clients" :record="$client" :label="$client->name">
                                                This permanently deletes the client, all associated jobs and their invoices across every year, including invoices already marked as invoiced. Associated contacts and credentials will also be deleted.
                                            </x-table-delete>
                                        </div>

                                    </td>

                                </tr>
                            @endforeach
                        @endif

                        <tr class="hover:bg-[var(--color-surface-alt)] transition">
                            <td colspan="2" class="text-center text-sm text-[var(--color-subtletext)]">
                                <a href="{{ route('clients.create') }}"
                                    class="inline-flex items-center justify-center cursor-pointer w-full gap-2 px-6 py-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-plus-icon lucide-plus">
                                        <path d="M5 12h14" />
                                        <path d="M12 5v14" />
                                    </svg>

                                    Create new client
                                </a>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <x-pagination :items="$clients" />
