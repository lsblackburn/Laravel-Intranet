<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-(--color-text) sm:text-4xl">
            {{ __('Invoices') }}
        </h1>
    </x-slot>

    <main class="py-6">

        <section class="max-w-7xl mx-auto py-3.5 px-4 sm:px-6 lg:px-8">

            @if ($client)
                <x-primary-link class="mb-5" href="{{ route('jobs.index', ['client_id' => $client->id]) }}">Back to Jobs
                    List<span aria-hidden="true">&#8599;</span></x-primary-link>
            @else
                <x-primary-link class="mb-5" href="{{ route('jobs.index') }}">Back to Jobs List<span
                        aria-hidden="true">&#8599;</span></x-primary-link>
            @endif

            <nav>

                <ul class="flex flex-row flex-wrap gap-4">

                    <li>

                        <a href="{{ route('jobs.edit', $job) }}"
                            class="block bg-(--color-card)/65 px-5 py-3 border-box rounded-t-lg transition-colors duration-200 hover:bg-(--color-card)/85">Job
                            Details</a>

                    </li>

                    <li>

                        <a href="{{ route('invoice.form', ['job' => $job, 'year' => $year]) }}"
                            class="block bg-(--color-card) px-5 py-3 border-box rounded-t-lg">Invoice
                            Amounts</a>

                    </li>

                </ul>

            </nav>

            <div class="p-4 sm:p-8 bg-(--color-card) shadow rounded-lg rounded-tl-none">
                <div class="w-full">

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-(--color-text)">{{ $job->title }}</h2>
                        <p class="mt-1 text-sm text-(--color-subtletext)">Review monthly invoice amounts and billing frequency.</p>
                    </div>

                    <nav aria-label="Invoice year" class="mb-8 overflow-x-auto rounded-xl border border-(--color-border) bg-(--color-background) p-1.5">
                        <div class="flex min-w-max gap-1 sm:min-w-0">
                            @foreach (range(max(1901, $year - 3), min(2155, $year + 3)) as $navigationYear)
                                <a href="{{ route('invoice.form', ['job' => $job, 'year' => $navigationYear]) }}"
                                    @if ($navigationYear === $year) aria-current="page" @endif
                                    @class([
                                        'flex-1 rounded-lg px-5 py-2.5 text-center text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-primary)',
                                        'bg-(--color-primary) text-(--color-background) shadow-sm' => $navigationYear === $year,
                                        'text-(--color-subtletext) hover:bg-(--color-surface-alt) hover:text-(--color-text)' => $navigationYear !== $year,
                                    ])>{{ $navigationYear }}</a>
                            @endforeach
                        </div>
                    </nav>

                    @if (session('success'))
                        <p role="status" class="mb-4 text-sm text-(--color-success-text)">{{ session('success') }}</p>
                    @endif
                    <p class="mb-4 text-sm text-(--color-subtletext)">Choose a frequency to repeat a change. Click the selected frequency again to stop recurrence and reset amounts to &pound;0 from that month onward, including future years. Monthly changes replace every later month, including other values entered below.</p>
                    <form method="post" action="{{ route('invoice.update', ['job' => $job, 'year' => $year]) }}" class="space-y-6"
                        x-data="{
                            total: '0.00',
                            frequencies: @js(session()->hasOldInput() ? old('frequencies', []) : $followingFrequencies),
                            months: @js($months),
                            toggleFrequency(month, frequency) {
                                if (this.frequencies[month] === frequency) {
                                    this.months.slice(this.months.indexOf(month)).forEach(name => {
                                        this.frequencies[name] = null;
                                        this.$el.querySelector('#amount-' + name).value = '0.00';
                                    });
                                    this.updateTotal();
                                } else {
                                    this.frequencies[month] = frequency;
                                }
                            },
                            updateTotal() {
                                const pennies = Array.from(this.$el.querySelectorAll('input[type=number]'))
                                    .reduce((sum, input) => sum + Math.round((Number(input.value) || 0) * 100), 0);
                                this.total = (pennies / 100).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }" x-init="updateTotal()" @input="updateTotal()">
                        @csrf
                        @method('PATCH')

                        <div class="flex items-baseline justify-between gap-4">
                            <h2 class="text-2xl font-semibold tracking-tight text-(--color-text)">Year {{ $year }}</h2>
                            <span class="text-sm text-(--color-subtletext)">12 months &middot; GBP</span>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-(--color-border)">

                            <table class="w-full min-w-[580px] border-collapse text-sm text-(--color-text)">
                                <caption class="sr-only">Monthly invoices for {{ $job->title }}, {{ $year }}</caption>

                                <thead class="bg-(--color-surface-alt) text-(--color-subtletext)">

                                    <tr>

                                        <th scope="col" class="px-5 py-4 text-left font-semibold sm:px-6">Month</th>

                                        <th scope="col" class="px-5 py-4 text-left font-semibold sm:px-6">Invoice amount (&pound;)</th>

                                        <th scope="col" class="px-5 py-4 text-left font-semibold sm:px-6">Frequency</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    @foreach ($months as $month)
                                    <tr class="border-t border-(--color-border)/60 even:bg-(--color-background)/60 hover:bg-(--color-surface-alt)/60 transition-colors">

                                        <th scope="row" class="whitespace-nowrap px-5 py-3 text-left font-medium sm:px-6">
                                            <label for="amount-{{ $month }}">{{ substr($month, 0, 3) }} <span class="font-normal text-(--color-subtletext)">{{ $year }}</span></label>
                                        </th>

                                        <td class="px-5 py-3 sm:px-6">

                                            <x-text-input id="amount-{{ $month }}" class="block w-full max-w-64 tabular-nums" type="number" required placeholder="0.00"
                                                step="0.01" name="amounts[{{ $month }}]"
                                                aria-label="{{ $month }} {{ $year }} invoice amount"
                                                :value="old('amounts.'.$month, ($invoices->get($month)?->cell_amount ?? $invoices->get($month)?->amount))" />
                                            <x-input-error :messages="$errors->get('amounts.'.$month)" class="mt-2" />

                                        </td>

                                        <td class="px-5 py-3 sm:px-6">

                                            <fieldset class="inline-flex gap-1 rounded-lg border border-(--color-border) bg-(--color-background) p-1">
                                                <legend class="sr-only">Billing frequency for {{ $month }} {{ $year }}</legend>
                                                <input type="hidden" name="frequencies[{{ $month }}]"
                                                    value="{{ session()->hasOldInput() ? old('frequencies.'.$month) : ($followingFrequencies[$month] ?? '') }}"
                                                    :value="frequencies['{{ $month }}'] || ''">
                                                @foreach (['yearly' => 'Yearly', 'monthly' => 'Monthly'] as $frequency => $label)
                                                    <button type="button" @click="toggleFrequency('{{ $month }}', '{{ $frequency }}')"
                                                        aria-label="{{ $label }} frequency for {{ $month }} {{ $year }}"
                                                        :aria-pressed="frequencies['{{ $month }}'] === '{{ $frequency }}'"
                                                        :class="frequencies['{{ $month }}'] === '{{ $frequency }}' ? 'bg-(--color-primary) text-(--color-background)' : 'text-(--color-subtletext)'"
                                                        class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors">{{ $label }}</button>
                                                @endforeach
                                            </fieldset>

                                        </td>

                                    </tr>
                                    @endforeach

                                </tbody>

                                <tfoot class="border-t border-(--color-border) bg-(--color-surface-alt)">
                                    <tr>
                                        <th scope="row" class="px-5 py-5 text-left font-semibold sm:px-6">Total ({{ $year }})</th>
                                        <td colspan="2" class="px-5 py-5 text-lg font-semibold tabular-nums text-(--color-primary) sm:px-6">
                                            &pound;<span x-text="total">{{ number_format(collect($months)->sum(fn ($month) => (float) old('amounts.'.$month, ($invoices->get($month)?->cell_amount ?? $invoices->get($month)?->amount))), 2) }}</span>
                                        </td>
                                    </tr>
                                </tfoot>

                            </table>

                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <a href="{{ route('jobs.edit', $job) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-(--color-subtletext) hover:text-(--color-text)">Cancel</a>
                            <x-primary-button>{{ __('Update invoices') }}</x-primary-button>
                        </div>

                    </form>

                </div>

            </div>

        </section>

    </main>

</x-app-layout>
