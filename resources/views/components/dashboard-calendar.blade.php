<div class="py-6">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
        <section class="rounded-xl border border-(--color-border) bg-(--color-card) p-[18px] shadow-(--workspace-shadow) md:p-7">
            <div class="mb-[26px] flex flex-col items-start justify-between gap-3 md:flex-row md:items-center md:gap-5">
                <div>
                    <p class="block text-[11px] font-semibold tracking-[.12em] uppercase text-(--color-subtletext)">The next four weeks</p>
                    <h2 class="mt-1.5 mb-1 text-[23px] font-medium tracking-[-.035em]">Who's out of office?</h2>
                    <p class="text-[13px] text-(--color-subtletext)">A shared view of the team's time away.</p>
                </div>
                <a class="inline-flex items-center gap-4 text-[13px] font-medium text-(--color-primary) hover:underline underline-offset-4" href="{{ route('calendar') }}">Full calendar <span aria-hidden="true">&#8599;</span></a>
            </div>
            <div class="overflow-x-auto"><div id="dashboard-calendar" class="min-w-[560px]"></div></div>
        </section>
    </div>
</div>
