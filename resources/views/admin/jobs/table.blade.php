<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Project Financials') }}
        </h1>
    </x-slot>
    <main class="py-6 text-(--color-text)">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            <nav aria-label="Breadcrumb" class="mb-5 flex gap-3 text-sm text-(--color-subtletext)">
                <a href="{{ route('dashboard') }}">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('jobs.index') }}">Jobs</a>
                <span aria-hidden="true">/</span>
                <span class="text-(--color-text)">Project Forecast</span>
            </nav>

            <div class="rounded-[14px] border border-(--color-border) bg-(--color-card) shadow-(--workspace-shadow) mb-5 flex flex-wrap items-center justify-between gap-4 p-3">

                <div class="w-full flex flex-wrap justify-between items-center gap-4">

                    <nav aria-label="Project status" class="flex items-center rounded-lg border border-(--color-border) p-1">

                        @foreach (['all' => 'All Projects', 'active' => 'Active', 'complete' => 'Complete'] as $value => $label)

                            <a href="{{ route('job.table', ['status' => $value]) }}" class="rounded-lg px-4 py-2 text-[13px] text-(--color-subtletext) hover:bg-(--color-surface-alt) aria-[current=page]:bg-(--color-primary) aria-[current=page]:text-(--color-background)" @if ($status === $value) aria-current="page" @endif>{{ $label }}</a>

                        @endforeach

                    </nav>

                    <span class="rounded-lg border border-(--color-border) px-4 py-2 text-sm">Jan {{ $year }} &ndash; Dec {{ $year }}</span>

                </div>

            </div>

            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

                @foreach (['Total (Year)' => '£'.number_format($total, 2), 'Clients' => $clientGroups->count(), 'Projects' => $clientGroups->sum(fn ($jobs) => $jobs->count()), 'Active Projects' => $activeCount] as $label => $value)

                    <div class="rounded-[14px] border border-(--color-border) bg-(--color-card) shadow-(--workspace-shadow) flex items-center gap-4 px-5 py-4">

                        <span aria-hidden="true" class="flex h-11 w-11 items-center justify-center rounded-xl bg-(--color-primary)/10 text-xl text-(--color-primary)">{{ $loop->first ? '£' : ($loop->last ? '↗' : '≡') }}</span>

                        <div>

                            <p class="text-xs text-(--color-subtletext)">{{ $label }}</p>

                            <p class="mt-1 text-2xl font-semibold">{{ $value }}</p>

                        </div>

                    </div>

                @endforeach

            </div>

            <div class="mb-3 flex flex-wrap items-end justify-between gap-3 text-xs text-(--color-subtletext)">

                <p>Click a monthly amount to cycle its invoice status. Select a project to edit amounts. All amounts in GBP.</p>

                <span class="flex flex-col items-center gap-2">

                    <div class="w-full flex justify-between items-center gap-4">

                        <span class="h-4 w-4 rounded border border-(--color-warning)/40 bg-(--color-warning)/15"></span>

                        Yellow = Current month

                    </div>

                    <div class="w-full flex justify-between items-center gap-4">

                        <span class="h-4 w-4 rounded border border-(--color-text) bg-(--color-text)"></span>

                        <p class="text-(--color-subtletext)">
                        Normal = Job in progress
                        </p>

                    </div>

                    <div class="w-full flex justify-between items-center gap-4">

                        <span class="h-4 w-4 rounded border border-(--color-success) bg-(--color-success)/70"></span>

                        Green = Ready to invoice

                    </div>

                    <div class=" w-full flex justify-between items-center gap-4">

                        <span class="h-4 w-4 rounded border border-(--color-info) bg-(--color-info)/70"></span>

                        Blue = Already invoiced

                    </div>

                </span>

            </div>

            <div class="rounded-[14px] border border-(--color-border) bg-(--color-card) shadow-(--workspace-shadow) overflow-hidden p-1.5">

                <div class="overflow-x-auto">

                    <table class="w-full min-w-[1500px] border-separate border-spacing-0 text-[13px] [&_th]:border-r [&_th]:border-b [&_th]:border-(--color-border) [&_th]:px-3 [&_td]:border-r [&_td]:border-b [&_td]:border-(--color-border) [&_td]:px-3 [&_tbody_th]:py-2 [&_tbody_td]:py-2">

                        <caption class="sr-only">Monthly job invoice amounts grouped by client and project type for {{ $year }}. All amounts in pounds.</caption>

                        <thead class="bg-(--color-surface-alt) [&_th]:py-3.5 [&_th]:font-semibold">

                            <tr>

                                <th scope="col" class="min-w-80 text-left">Project / Item</th>

                                <th scope="col" class="text-right tabular-nums whitespace-nowrap">Total</th>

                                @foreach ($months as $month)
                                    <th scope="col" @class(['min-w-24 text-center', 'bg-(--color-warning)/15 text-(--color-warning-text)' => $month === $currentMonth]) @if ($month === $currentMonth) aria-current="date" @endif>{{ substr($month, 0, 3) }}<br>{{ $year }}</th>
                                @endforeach

                            <th scope="col">Actions</th>

                        </tr>

                    </thead>

                        @forelse ($clientGroups as $clientId => $clientJobs)

                            <tbody x-data="{ open: true }">

                                <tr>

                                    <th scope="rowgroup" class="bg-(--color-primary) text-sm text-(--color-background) text-left">

                                        <button type="button" class="flex w-full items-center gap-3 text-left" @click="open = !open" :aria-expanded="open" aria-expanded="true">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 transition-transform motion-reduce:transition-none" :class="{ 'rotate-90': open }">
                                                <path d="m9 18 6-6-6-6" />
                                            </svg>
                                            {{ $clientJobs->first()->client?->name ?? 'Unassigned' }}
                                        </button>
                                    
                                    </th>

                                    <td class="bg-(--color-primary) text-(--color-background) text-right tabular-nums whitespace-nowrap font-semibold">&pound;{{ number_format($clientJobs->sum(fn ($job) => $amounts[$job->id]->sum()), 2) }}</td>
                                    
                                    <td colspan="13" class="bg-(--color-surface-alt)"></td>
                                
                                </tr>

                                @foreach ($clientJobs->groupBy('project_type') as $projectType => $jobs)

                                    <tr x-show="open" class="bg-(--color-surface-alt) text-[11px] tracking-[0.06em] text-(--color-subtletext) uppercase">

                                        <th colspan="15" class="text-left">{{ $projectType ?: 'Unassigned' }}</th>

                                    </tr>

                                    @foreach ($jobs as $job)

                                        <tr x-show="open" class="hover:bg-(--color-surface-alt)">

                                            <th scope="row" class="text-left font-normal">
                                                
                                                <a class="block pl-5 hover:text-(--color-primary-hover) hover:underline" href="{{ route('invoice.form', ['job' => $job, 'year' => $year]) }}">{{ $job->title }}</a>
                                            
                                            </th>

                                            <td class="text-right tabular-nums whitespace-nowrap font-medium">&pound;{{ number_format($amounts[$job->id]->sum(), 2) }}</td>

                                            @foreach ($months as $month)
                                            
                                                <td @class(['text-right tabular-nums whitespace-nowrap', 'bg-(--color-warning)/15 text-(--color-warning-text)' => $month === $currentMonth])>

                                                    <div x-data="invoiceState(@js($states->get($job->id)?->get($month) ?? 'In Progress'), @js(route('invoice.state', ['job' => $job, 'year' => $year, 'month' => $month])), @js(csrf_token()))">
                                                        <button type="button" @click="cycle()" :disabled="saving" :aria-busy="saving"
                                                            :aria-label="@js($job->title.' ? '.$month.' '.$year.': ') + state + '. Click to change status.'"
                                                            :title="state + ' ? click to change status'"
                                                            class="w-full cursor-pointer rounded px-1 py-1 text-right focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-primary) disabled:cursor-wait disabled:opacity-50"
                                                            :class="{ 'text-(--color-text)': state === 'In Progress', 'text-(--color-success-text) font-bold': state === 'Ready To Invoice', 'text-(--color-info-text) font-bold': state === 'Already Invoiced' }">
                                                            &pound;{{ number_format((float) $amounts[$job->id][$month], 2) }}
                                                        </button>
                                                        <p x-cloak x-show="error" x-text="error" role="alert" class="max-w-40 whitespace-normal text-xs text-(--color-danger-text)"></p>
                                                    </div>

                                                </td>

                                            @endforeach

                                            <td class="text-center">

                                                <a href="{{ route('jobs.edit', $job) }}" aria-label="Edit {{ $job->title }}" class="inline-flex p-1 hover:text-(--color-primary-hover)">

                                                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m16 3 5 5-12 12-6 1 1-6L16 3ZM14 5l5 5"/></svg>

                                                </a>

                                            </td>

                                        </tr>

                                    @endforeach

                                @endforeach

                            </tbody>

                        @empty

                            <tbody>

                                <tr>

                                    <td colspan="15" class="py-10 text-center">No jobs to display.</td>

                                </tr>

                            </tbody>

                        @endforelse

                    </table>

                </div>

            </div>

        </section>

    </main>

</x-app-layout>
