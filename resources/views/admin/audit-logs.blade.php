<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('View Audit Logs') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <div class="overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-card)] shadow-sm">

                <table class="min-w-full text-sm text-left">

                    <!-- Header -->
                    <thead
                        class="bg-[var(--color-surface-alt)] text-xs uppercase tracking-wider text-[var(--color-subtletext)]">
                        <tr>
                            <th class="px-6 py-4 font-medium">Time</th>
                            <th class="px-6 py-4 font-medium">Log</th>
                        </tr>
                    </thead>

                    <!-- Body -->
                    <tbody class="divide-y divide-[var(--color-border)] text-[var(--color-text)]">

                        @if ($logs->isEmpty())
                            <tr>
                                <td colspan="2" class="px-6 py-4 text-center text-sm text-[var(--color-subtletext)]">
                                    {{ request()->filled('search') ? 'No clients match your search.' : 'No clients yet. Add your first client to get started.' }}
                                </td>
                            </tr>
                        @else
                            @foreach ($logs as $log)
                                <tr class="hover:bg-[var(--color-surface-alt)] transition">

                                    <td class="px-6 py-4">
                                        <time class="font-bold">{{ \Carbon\Carbon::parse($log->created_at)->format('H:i') }}</time>

                                        <time class="font-bold">{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y') }}</time>
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ $log->metadata['message'] ?? $log->action }}
                                    </td>

                                </tr>
                            @endforeach
                        @endif

                    </tbody>

                </table>

            </div>

            <x-pagination :items="$logs" />

        </section>
        
    </main>

</x-app-layout>
