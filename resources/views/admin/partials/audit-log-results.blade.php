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
                        {{ request()->anyFilled(['search', 'from', 'to']) ? 'No logs match your filters.' : 'No logs exist yet.' }}
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
