            @if ($client)
                <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg rounded-tl-none">
            @endif

                <div class="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] shadow-sm">
                    <table class="min-w-full text-sm text-left">
                        <caption class="sr-only">Passwords and their clients</caption>
                        <thead class="bg-[var(--color-surface-alt)] text-xs uppercase tracking-wider text-[var(--color-subtletext)]">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium">Client</th>
                                <th scope="col" class="px-6 py-4 font-medium">Name</th>
                                <th scope="col" class="px-6 py-4 font-medium">Username</th>
                                <th scope="col" class="px-6 py-4 font-medium">Password</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-border)] text-[var(--color-text)]">
                            @forelse ($passwords as $password)
                                <tr class="hover:bg-[var(--color-surface-alt)] transition">
                                    <td class="px-6 py-4">{{ $password->client->name }}</td>
                                    <td class="px-6 py-4">{{ $password->name }}</td>
                                    <td class="px-6 py-4">{{ $password->username }}</td>
                                    <td class="px-6 py-4">{{ $password->password }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-3">

                                            @if ($client)
                                                <x-primary-link href="{{ route('passwords.edit', ['password' => $password, 'client_id' => $client->id]) }}">View/Edit</x-primary-link>
                                            @else
                                                <x-primary-link href="{{ route('passwords.edit', $password) }}">View/Edit</x-primary-link>
                                            @endif

                                            <x-table-delete resource="passwords" :record="$password" :label="$password->name">
                                                This permanently deletes the password.
                                            </x-table-delete>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-[var(--color-subtletext)]">{{ request()->filled('search') ? 'No passwords match your search.' : 'No passwords yet. Add your first password to get started.' }}</td></tr>
                            @endforelse

                            <tr class="hover:bg-[var(--color-surface-alt)] transition">
                                <td colspan="5" class="text-center text-sm text-[var(--color-subtletext)]">
                                    <a href="{{ route('passwords.create', $client ? ['client_id' => $client->id] : []) }}"
                                        class="inline-flex items-center justify-center cursor-pointer w-full gap-2 px-6 py-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-plus-icon lucide-plus">
                                            <path d="M5 12h14" />
                                            <path d="M12 5v14" />
                                        </svg>

                                        Create new password
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            @if ($client)
                </div>
            @endif

            <x-pagination :items="$passwords" />
