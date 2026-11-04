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

                                        <x-primary-link class="bg-blue-500 hover:bg-blue-600 text-white" href="{{ route('passwords.edit', $password) }}">View/Edit</x-primary-link>

                                        <x-table-delete resource="passwords" :record="$password" :label="$password->name">
                                            This permanently deletes the password.
                                        </x-table-delete>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center">{{ $search !== '' ? 'No passwords match your search.' : 'No passwords yet. Add your first password to get started.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :items="$passwords" />
