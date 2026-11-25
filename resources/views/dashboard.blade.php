<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">{{ __('Your workspace') }}</h1>
    </x-slot>

    <div class="pt-[18px] max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <section
            class="flex flex-col items-start justify-between gap-5 pt-2.5 pb-8 md:flex-row md:items-center md:gap-6">
            <div>
                <p class="block text-[11px] font-semibold tracking-[.12em] uppercase text-(--color-subtletext)">A little
                    room to plan ahead</p>
                <h2 class="my-2.5 text-[clamp(30px,4vw,44px)] font-medium leading-tight tracking-[-.045em]">Hello,
                    {{ \Illuminate\Support\Str::before(Auth::user()->name, ' ') }}.</h2>
                <p class="text-(--color-subtletext)">Your team, your time, and everything coming up.</p>
            </div>
            <x-primary-link href="{{ route('leave.form') }}">Request time off <span
                    aria-hidden="true">&#8599;</span></x-primary-link>
        </section>

        @if (auth()->user()->isAdmin())

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:gap-[18px] pb-5">

                <section aria-labelledby="outstanding-invoices-heading" class="flex flex-col items-stretch rounded-xl border border-(--color-border) bg-(--color-card) p-5 md:px-[26px] md:py-6 shadow-(--workspace-shadow)">
                    <h2 id="outstanding-invoices-heading" class="text-[11px] font-semibold tracking-[.12em] uppercase text-(--color-subtletext)">Outstanding invoices</h2>
                    <p class="mt-2 text-sm text-(--color-subtletext)">{{ $previousMonth->format('F Y') }}</p>

                    @if ($outstandingInvoices->isNotEmpty())
                        <ul class="my-4 divide-y divide-(--color-border)">
                            @foreach ($outstandingInvoices as $outstanding)
                                <li>
                                    <a href="{{ route('job.table')}}" class="flex items-start justify-between gap-4 py-3 hover:text-(--color-primary)">
                                        <span>
                                            <span class="block font-medium">{{ $outstanding['job']->title }}</span>
                                            <span class="block text-xs text-(--color-subtletext)">{{ $outstanding['job']->client?->name ?? 'Unassigned' }}</span>
                                        </span>
                                        <span class="whitespace-nowrap font-semibold tabular-nums">&pound;{{ number_format((float) $outstanding['amount'], 2) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="my-5">All previous month's invoices have been invoiced.</p>
                    @endif

                    <a href="{{ route('job.table') }}" class="mt-auto flex items-center justify-between gap-3 border-t border-(--color-border) pt-3.5 text-xs text-(--color-subtletext) hover:text-(--color-primary)">View job invoices <span aria-hidden="true">&#8599;</span></a>
                </section>

                <a class="flex flex-col items-stretch rounded-xl border border-(--color-border) bg-(--color-card) p-5 md:px-[26px] md:py-6 shadow-(--workspace-shadow) transition-colors hover:border-(--color-primary)"
                href="{{ route('leave.view') }}">

                    <span class="block text-[11px] font-semibold tracking-[.12em] uppercase text-(--color-subtletext)">Time
                        for yourself</span>

                    <div class="mt-4 mb-5 flex items-baseline gap-3">
                        <strong
                            class="text-4xl font-medium leading-tight tracking-[-.05em] min-[421px]:text-[44px]">{{ Auth::user()->remainingLeaveAllowance() }}</strong>
                            <span class="text-[13px] text-(--color-subtletext)">days remaining</span>
                        </div>

                    <span
                        class="mt-auto flex items-center justify-between gap-3 border-t border-(--color-border) pt-3.5 text-xs text-(--color-subtletext)">Of
                        {{ Auth::user()->leave_allowance }} days annual allowance <span
                            aria-hidden="true">&#8599;</span>
                        
                    </span>
                </a>


            </div>
            
        @endif

        <div class="grid grid-cols-1 gap-3 min-[421px]:grid-cols-2 md:grid-cols-3 md:gap-[18px]">

            <a class="flex flex-col items-stretch rounded-xl border border-(--color-border) bg-(--color-card) p-5 md:px-[26px] md:py-6 shadow-(--workspace-shadow) transition-colors hover:border-(--color-primary)"
                href="{{ route('leave.view') }}">

                <span class="block text-[11px] font-semibold tracking-[.12em] uppercase text-(--color-subtletext)">Time
                    for yourself</span>

                <div class="mt-4 mb-5 flex items-baseline gap-3">
                    <strong
                        class="text-4xl font-medium leading-tight tracking-[-.05em] min-[421px]:text-[44px]">{{ Auth::user()->remainingLeaveAllowance() }}</strong>
                        <span class="text-[13px] text-(--color-subtletext)">days remaining</span>
                    </div>

                <span
                    class="mt-auto flex items-center justify-between gap-3 border-t border-(--color-border) pt-3.5 text-xs text-(--color-subtletext)">Of
                    {{ Auth::user()->leave_allowance }} days annual allowance <span
                        aria-hidden="true">&#8599;</span>
                    
                </span>
            </a>

            <a class="flex flex-col items-stretch rounded-xl border border-(--color-border) bg-(--color-card) p-5 md:px-[26px] md:py-6 shadow-(--workspace-shadow) transition-colors hover:border-(--color-primary)"
                href="{{ route('leave.view') }}">
                <span class="block text-[11px] font-semibold tracking-[.12em] uppercase text-(--color-subtletext)">In
                    the pipeline</span>
                <div class="mt-4 mb-5 flex items-baseline gap-3"><strong
                        class="text-4xl font-medium leading-tight tracking-[-.05em] min-[421px]:text-[44px]">{{ Auth::user()->calculatePendingLeaveNumber() }}</strong><span
                        class="text-[13px] text-(--color-subtletext)">days pending</span></div>
                <span
                    class="mt-auto flex items-center justify-between gap-3 border-t border-(--color-border) pt-3.5 text-xs text-(--color-subtletext)">View
                    your leave requests <span aria-hidden="true">&#8599;</span></span>
            </a>

            <a class="flex flex-col items-stretch rounded-xl border border-transparent bg-(--color-primary) p-5 text-(--color-background) shadow-(--workspace-shadow) transition-colors hover:border-(--color-primary-hover) min-[421px]:col-span-2 md:col-span-1 md:px-[26px] md:py-6"
                href="{{ route('calendar') }}">

                <span class="block text-[11px] font-semibold tracking-[.12em] uppercase opacity-85">Better,
                    together</span>

                <div class="mt-4 mb-5 text-2xl font-medium leading-tight tracking-tight md:text-[26px]">Make space<br
                        class="hidden md:block"> for your plans.</div>

                <span
                    class="mt-auto flex items-center justify-between gap-3 border-t border-current pt-3.5 text-xs opacity-85">See
                    the team calendar <span aria-hidden="true">&#8599;</span></span>
            </a>

        </div>

    </div>

    <x-dashboard-calendar />

</x-app-layout>
