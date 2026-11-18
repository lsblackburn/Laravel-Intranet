<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <span class="rounded-xl bg-violet-100 p-3 text-violet-800" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 10c0 4 16 4 16 0M4 15c0 4 16 4 16 0"/></svg></span>
            <div><h1 class="text-3xl font-semibold tracking-tight text-(--color-text)">Project Financial Forecast</h1><p class="mt-1 text-sm text-(--color-subtletext)">Manage project costs and forecasted income across your jobs.</p></div>
        </div>
    </x-slot>
    <style>
        .forecast { color:var(--color-text); }
        .forecast-panel { border:1px solid var(--color-border); border-radius:14px; background:var(--color-card); box-shadow:0 5px 24px #6d28d908; }
        .forecast-table { width:100%; min-width:1500px; border-collapse:separate; border-spacing:0; font-size:13px; }
        .forecast-table th,.forecast-table td { padding:8px 12px; border-right:1px solid var(--color-border); border-bottom:1px solid var(--color-border); }
        .forecast-table thead th { background:var(--color-surface-alt); font-weight:600; padding-top:14px; padding-bottom:14px; }
        .forecast-table .month-now { background:#fff5cc; color:#392c13; }
        .forecast-table .client-title { background:linear-gradient(110deg,#735090,#56357b); color:white; font-size:14px; }
        .forecast-table .client-total { background:#56357b; color:white; }
        .forecast-table .client-space { background:var(--color-surface-alt); }
        .forecast-table .project-type { background:var(--color-surface-alt); color:var(--color-subtletext); font-size:11px; text-transform:uppercase; letter-spacing:.06em; }
        .forecast-table .job-row:hover { background:var(--color-surface-alt); }
        .forecast-table .money { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .forecast-filter { padding:8px 16px; border-radius:8px; font-size:13px; }
        .forecast-filter[aria-current="page"] { background:linear-gradient(#8b3ee8,#5826a0); color:white; box-shadow:0 2px 5px #5826a030; }
    </style>
    <main class="forecast px-4 py-6 sm:px-6 lg:px-8">
        <nav aria-label="Breadcrumb" class="mb-5 flex gap-3 text-sm text-(--color-subtletext)"><a href="{{ route('dashboard') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route('jobs.index') }}">Jobs</a><span aria-hidden="true">/</span><span class="text-(--color-text)">Project Forecast</span></nav>
        <div class="forecast-panel mb-5 flex flex-wrap items-center justify-between gap-4 p-3">
            <div class="flex gap-3"><a href="{{ route('jobs.create') }}" class="rounded-lg bg-violet-700 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-800">+ Add Project</a><a href="{{ route('jobs.index') }}" class="rounded-lg border border-(--color-border) px-4 py-2 text-sm">Jobs list</a></div>
            <div class="flex flex-wrap items-center gap-4">
                <nav aria-label="Project status" class="flex items-center rounded-lg border border-(--color-border) p-1">
                    @foreach (['all' => 'All Projects', 'active' => 'Active', 'complete' => 'Complete'] as $value => $label)
                        <a href="{{ route('job.table', ['status' => $value]) }}" class="forecast-filter" @if ($status === $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <span class="rounded-lg border border-(--color-border) px-4 py-2 text-sm">Jan {{ $year }} &ndash; Dec {{ $year }}</span>
            </div>
        </div>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['Total (Year)' => '£'.number_format($total, 2), 'Clients' => $clientGroups->count(), 'Projects' => $clientGroups->sum(fn ($jobs) => $jobs->count()), 'Active Projects' => $activeCount] as $label => $value)
                <div class="forecast-panel flex items-center gap-4 px-5 py-4"><span aria-hidden="true" class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-xl text-violet-700">{{ $loop->first ? '£' : ($loop->last ? '↗' : '≡') }}</span><div><p class="text-xs text-(--color-subtletext)">{{ $label }}</p><p class="mt-1 text-2xl font-semibold">{{ $value }}</p></div></div>
            @endforeach
        </div>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3 text-xs text-(--color-subtletext)"><p>Select a project to update its monthly invoice amounts. All amounts in GBP.</p><span class="flex items-center gap-2"><span class="h-4 w-4 rounded border border-amber-200 bg-amber-100"></span>Yellow = Current month</span></div>
        <div class="forecast-panel overflow-hidden p-1.5">
            <div class="overflow-x-auto">
                <table class="forecast-table">
                    <caption class="sr-only">Monthly job invoice amounts grouped by client and project type for {{ $year }}. All amounts in pounds.</caption>
                    <thead><tr><th scope="col" class="min-w-80 text-left">Project / Item</th><th scope="col" class="money">Total</th>
                        @foreach ($months as $month)
                            <th scope="col" @class(['min-w-24 text-center', 'month-now' => $month === $currentMonth]) @if ($month === $currentMonth) aria-current="date" @endif>{{ substr($month, 0, 3) }}<br>{{ $year }}</th>
                        @endforeach
                        <th scope="col">Actions</th>
                    </tr></thead>
                    @forelse ($clientGroups as $clientId => $clientJobs)
                        <tbody x-data="{ open: true }">
                            <tr><th scope="rowgroup" class="client-title text-left"><button type="button" class="flex w-full items-center gap-3 text-left" @click="open = !open" :aria-expanded="open" aria-expanded="true"><span aria-hidden="true" x-text="open ? '&#8964;' : '&#8250;'">&#8964;</span>{{ $clientJobs->first()->client?->name ?? 'Unassigned' }}</button></th><td class="client-total money font-semibold">&pound;{{ number_format($clientJobs->sum(fn ($job) => $amounts[$job->id]->sum()), 2) }}</td><td colspan="13" class="client-space"></td></tr>

                            @foreach ($clientJobs->groupBy('project_type') as $projectType => $jobs)
                                <tr x-show="open" class="project-type"><th colspan="15" class="text-left">{{ $projectType ?: 'Unassigned' }}</th></tr>
                                @foreach ($jobs as $job)
                                    <tr x-show="open" class="job-row">
                                        <th scope="row" class="text-left font-normal"><a class="block pl-5 hover:text-violet-600 hover:underline" href="{{ route('invoice.form', ['job' => $job, 'year' => $year]) }}">{{ $job->title }}</a></th>
                                        <td class="money font-medium">&pound;{{ number_format($amounts[$job->id]->sum(), 2) }}</td>
                                        @foreach ($months as $month)
                                            <td @class(['money', 'month-now' => $month === $currentMonth])>
                                                @if ((float) $amounts[$job->id][$month] != 0)
                                                    &pound;{{ number_format((float) $amounts[$job->id][$month], 2) }}
                                                @else
                                                    <span class="sr-only">0.00</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-center"><a href="{{ route('jobs.edit', $job) }}" aria-label="Edit {{ $job->title }}" class="inline-flex p-1 hover:text-violet-600"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m16 3 5 5-12 12-6 1 1-6L16 3ZM14 5l5 5"/></svg></a></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    @empty
                        <tbody><tr><td colspan="15" class="py-10 text-center">No jobs to display.</td></tr></tbody>
                    @endforelse
                </table>
            </div>
        </div>
    </main>
</x-app-layout>
