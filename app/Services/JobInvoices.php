<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WorkJob;
use Illuminate\Support\Collection;

class JobInvoices
{
    public const MONTHS = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];

    // Resolve uncreated months without writing invoice records from the overview.
    public function amountsForYear(Collection $invoices, int $year): Collection
    {
        $existing = $invoices->where('year', $year)->keyBy('month');
        $rules = $invoices->filter(fn (Invoice $invoice) => $invoice->recurring || $invoice->stops_recurrence)
            ->sortByDesc(fn (Invoice $invoice) => $this->position($invoice));

        return collect(self::MONTHS)->mapWithKeys(fn ($month) => [
            $month => $existing->get($month)?->cell_amount ?? $existing->get($month)?->amount ?? $this->ruleFor($year, $month, $rules)?->amount ?? '0.00',
        ]);
    }

    private function position(Invoice $invoice): int
    {
        return (int) $invoice->year * 12 + array_search($invoice->month, self::MONTHS, true);
    }

    private function ruleFor(int $year, string $month, Collection $rules): ?Invoice
    {
        $position = $year * 12 + array_search($month, self::MONTHS, true);

        $rule = $rules->first(fn (Invoice $rule) => $this->position($rule) <= $position
            && ($rule->stops_recurrence || $rule->frequency === 'monthly' || $rule->month === $month));

        return $rule?->stops_recurrence ? null : $rule;
    }

    private function rules(WorkJob $job): Collection
    {
        return Invoice::where('work_job_id', $job->id)
            ->where(fn ($query) => $query->where('recurring', true)->orWhere('stops_recurrence', true))->get()
            ->sortByDesc(fn (Invoice $invoice) => $this->position($invoice));
    }

    // The caller locks the job inside a transaction to serialize creation and updates.
    public function ensureYear(WorkJob $job, int $year): Collection
    {
        $rules = $this->rules($job);

        foreach (self::MONTHS as $month) {
            $rule = $this->ruleFor($year, $month, $rules);
            Invoice::firstOrCreate([
                'work_job_id' => $job->id, 'year' => $year, 'month' => $month,
            ], [
                'client_id' => $job->client_id,
                'amount' => $rule?->amount ?? '0.00',
                'frequency' => $rule?->frequency ?? 'yearly',
                'recurring' => false,
            ]);
        }

        return Invoice::where('work_job_id', $job->id)->where('year', $year)->get()->keyBy('month');
    }

    public function frequenciesFor(WorkJob $job, Collection $invoices): array
    {
        $rules = $this->rules($job);

        return $invoices->map(function (Invoice $invoice) use ($rules) {
            $rule = $this->ruleFor((int) $invoice->year, $invoice->month, $rules);

            return $rule && $rule?->amount === $invoice->amount && $rule->frequency === $invoice->frequency
                ? $rule->frequency : null;
        })->all();
    }

    public function update(WorkJob $job, int $year, array $data): void
    {
        $invoices = $this->ensureYear($job, $year);
        $followingFrequencies = $this->frequenciesFor($job, $invoices);
        // Capture inheritance before changing rules: afterward an uncovered month
        // cannot be distinguished from an explicit one-off using the new rules.
        $previousRules = $this->rules($job);
        $inheritedIds = Invoice::where('work_job_id', $job->id)->where('year', '>=', $year)->get()
            ->filter(function (Invoice $invoice) use ($previousRules) {
                $rule = $this->ruleFor((int) $invoice->year, $invoice->month, $previousRules);

                return ! $invoice->recurring && ! $invoice->stops_recurrence && $rule
                    && $invoice->amount === $rule?->amount && $invoice->frequency === $rule->frequency;
            })->mapWithKeys(fn (Invoice $invoice) => [$invoice->id => true])->all();
        $localChanges = [];

        foreach (self::MONTHS as $month) {
            $invoice = $invoices->get($month);
            $amount = number_format((float) $data['amounts'][$month], 2, '.', '');
            $frequency = $data['frequencies'][$month] ?? null;

            if ($invoice->cell_amount !== null) {
                if ($invoice->cell_amount === $amount && ($followingFrequencies[$month] ?? null) === $frequency) {
                    continue;
                }
                $invoice->update(['cell_amount' => null]);
            }

            if ($frequency === null) {
                if (($followingFrequencies[$month] ?? null) !== null) {
                    foreach (Invoice::where('work_job_id', $job->id)->where('year', '>=', $year)->get() as $laterInvoice) {
                        if ($this->position($laterInvoice) >= $this->position($invoice)) {
                            $laterInvoice->update(['amount' => '0.00', 'recurring' => false, 'stops_recurrence' => false]);
                        }
                    }
                    $invoice->update(['amount' => '0.00', 'recurring' => false, 'stops_recurrence' => true]);
                    break;
                }

                if ($invoice->amount !== $amount) {
                    $invoice->update(['amount' => $amount, 'recurring' => false]);
                    $localChanges[] = $invoice->id;
                }

                continue;
            }

            if ($invoice->amount === $amount && ($followingFrequencies[$month] ?? null) === $frequency) {
                continue;
            }

            // An explicit change replaces later rules in the affected months.
            foreach ($this->rules($job) as $rule) {
                if ($this->position($rule) > $this->position($invoice)
                    && ($frequency === 'monthly' || $rule->month === $month)) {
                    $rule->update(['recurring' => false, 'stops_recurrence' => false]);
                }
            }

            $invoice->update(['amount' => $amount, 'frequency' => $frequency, 'recurring' => true, 'stops_recurrence' => false]);

            if ($frequency === 'monthly') {
                break;
            }
        }

        $rules = $this->rules($job);
        foreach (Invoice::where('work_job_id', $job->id)->where('year', '>=', $year)->get() as $invoice) {
            $rule = $this->ruleFor((int) $invoice->year, $invoice->month, $rules);
            if ($invoice->recurring || $invoice->stops_recurrence || in_array($invoice->id, $localChanges, true)) {
                continue;
            }

            if ($rule || isset($inheritedIds[$invoice->id])) {
                $invoice->update([
                    'amount' => $rule?->amount ?? '0.00',
                    'frequency' => $rule?->frequency ?? 'yearly',
                ]);
            }
        }
    }
}
