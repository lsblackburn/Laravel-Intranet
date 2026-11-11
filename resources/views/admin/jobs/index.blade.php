<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Jobs') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            @if ($client)
                <x-primary-link class="mb-5" href="{{ route('jobs.create', ['client_id' => $client->id]) }}">Add Job<span
                    aria-hidden="true">&#8599;</span></x-primary-link>
            @else
                <x-primary-link class="mb-5" href="{{ route('jobs.create') }}">Add Job<span
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

                <div class="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] shadow-sm">
                    <table class="min-w-full text-sm text-left">
                        <caption class="sr-only">Passwords and their clients</caption>
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
                                                <x-primary-link href="{{ route('jobs.edit', ['password' => $job, 'client_id' => $client->id]) }}">View/Edit</x-primary-link>
                                            @else
                                                <x-primary-link href="{{ route('jobs.edit', $job) }}">View/Edit</x-primary-link>
                                            @endif

                                            <x-table-delete resource="jobs" :record="$job" :label="$job->name">
                                                This permanently deletes the password.
                                            </x-table-delete>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-[var(--color-subtletext)]">No jobs yet. Add your first job to get started.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            @if ($client)
                </div>
            @endif

            <x-pagination :items="$jobs" />

        </section>
        
    </main>

</x-app-layout>
