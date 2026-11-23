            @if ($client)
                <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg rounded-tl-none">
            @endif

                <div class="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] shadow-sm">
                    <table class="min-w-full text-sm text-left">
                        <caption class="sr-only">Jobs and their clients</caption>
                        <thead class="bg-[var(--color-surface-alt)] text-xs uppercase tracking-wider text-[var(--color-subtletext)]">
                            <tr>
                                <th scope="col" class="px-6 py-4 font-medium">Client</th>
                                <th scope="col" class="px-6 py-4 font-medium">Job Title</th>
                                <th scope="col" class="px-6 py-4 font-medium">Project Type</th>
                                <th scope="col" class="px-6 py-4 font-medium text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--color-border)] text-[var(--color-text)]">
                            @forelse ($jobs as $job)
                                <tr class="hover:bg-[var(--color-surface-alt)] transition">
                                    <td class="px-6 py-4">{{ $job->client->name }}</td>
                                    <td class="px-6 py-4">{{ $job->title }}</td>
                                    <td class="px-6 py-4">{{ $job->project_type }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-3">

                                            @if ($client)
                                                <x-primary-link href="{{ route('jobs.edit', ['job' => $job, 'client_id' => $client->id]) }}">View/Edit</x-primary-link>
                                            @else
                                                <x-primary-link href="{{ route('jobs.edit', $job) }}">View/Edit</x-primary-link>
                                            @endif

                                            <x-table-delete resource="jobs" :record="$job" :label="$job->title">
                                                This permanently deletes the job and all associated invoices across every year, including invoices already marked as invoiced.
                                            </x-table-delete>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-[var(--color-subtletext)]">{{ request()->filled('search') || request()->filled('project_type') ? 'No jobs match your filters.' : 'No jobs yet. Add your first job to get started.' }}</td></tr>
                            @endforelse

                            <tr class="hover:bg-[var(--color-surface-alt)] transition">
                                <td colspan="4" class="text-center text-sm text-[var(--color-subtletext)]">
                                    <a href="{{ route('jobs.create', $client ? ['client_id' => $client->id] : []) }}"
                                        class="inline-flex items-center justify-center cursor-pointer w-full gap-2 px-6 py-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-plus-icon lucide-plus">
                                            <path d="M5 12h14" />
                                            <path d="M12 5v14" />
                                        </svg>

                                        Create new job
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            @if ($client)
                </div>
            @endif

            <x-pagination :items="$jobs" />
