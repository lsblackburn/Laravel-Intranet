<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\WorkJob;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\ProjectType;
use App\Services\JobInvoices;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class WorkJobController extends Controller
{
    
    private function validatedData(Request $request): array
    {
        // Validate the fields submitted by the job form.
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_type' => ['nullable', 'string', 'max:255', 'exists:project_types,name'],
            'notes' => ['nullable', 'string'],
            'client_id' => ['required', 'exists:clients,id'],
            'active' => ['required', 'boolean'],
            'completed' => ['required', 'boolean']
        ]);
    }
    
    public function index(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:255'], 'project_type' => ['nullable', 'string', 'max:255']]);
        $search = trim($request->input('search') ?? '');
        $client = null;
        $query = WorkJob::with('client');

        if ($request->filled('client_id')) {
            $request->validate(['client_id' => ['integer']]);
            $client = Client::findOrFail($request->input('client_id'));
            $query->where('client_id', $client->id);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%');
                $query->orWhere('project_type', 'like', '%'.$search.'%');
                $query->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', '%'.$search.'%'));
            });
        }
        $query->when($request->filled('project_type'), fn ($query) => $query->where('project_type', $request->input('project_type')));
        $projectTypes = ProjectType::orderBy('name')->pluck('name');

        $jobs = $query->orderBy('created_at', 'asc')->orderBy('id')->paginate(50)->withQueryString();

        return view($request->ajax() ? 'admin.jobs.results' : 'admin.jobs.index', compact('jobs', 'client', 'projectTypes'));
    }

    public function create(): View
    {
        $client = null;

        if (request()->filled('client_id')) {
            request()->validate(['client_id' => ['integer']]);
            $client = Client::findOrFail(request()->input('client_id'));
        }

        $clients = Client::orderBy('name', 'asc')->get();

        $projectTypes = ProjectType::orderBy('name', 'asc')->pluck('name', 'name');

        return view('admin.jobs.create', compact('clients', 'client', 'projectTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $job = WorkJob::create($data);

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $job,
            action: 'job_created',
            description: "created job for {$job->client->name}",
        );

        return redirect()->route('jobs.edit', $job)->with('success', 'Job created successfully.');
    }

    public function edit(WorkJob $job): View
    {
        $client = null;

        if (request()->filled('client_id')) {
            request()->validate(['client_id' => ['integer']]);
            $client = Client::findOrFail(request()->input('client_id'));
        }

        $clients = Client::orderBy('name', 'asc')->get();

        $projectTypes = ProjectType::orderBy('name', 'asc')->pluck('name', 'name');

        $job = WorkJob::findOrFail($job->id);

        return view('admin.jobs.edit', compact('job', 'projectTypes', 'clients', 'client'));
    }

    public function update(Request $request, WorkJob $job): RedirectResponse
    {
        $data = $this->validatedData($request);

        $job = DB::transaction(function () use ($job, $data) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $job->update($data);
            $job->invoices()->where('client_id', '!=', $job->client_id)
                ->update(['client_id' => $job->client_id]);

            return $job;
        });

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $job,
            action: 'job_updated',
            description: "updated job for {$job->client->name}",
        );

        return redirect()->route('jobs.edit', $job)->with('success', 'Job updated successfully.');
    }

    public function invoiceForm(WorkJob $job, int $year): View
    {
        $client = $job->client;
        abort_unless($year >= 1901 && $year <= 2155, 404);
        $months = JobInvoices::MONTHS;
        $invoices = DB::transaction(function () use ($job, $year) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();

            return app(JobInvoices::class)->ensureYear($job, $year);
        });

        $followingFrequencies = app(JobInvoices::class)->frequenciesFor($job, $invoices);

        return view('admin.jobs.invoice', compact('job', 'year', 'client', 'months', 'invoices', 'followingFrequencies'));
    }

    public function invoiceUpdate(Request $request, WorkJob $job, int $year): RedirectResponse
    {
        abort_unless($year >= 1901 && $year <= 2155, 404);
        $rules = ['amounts' => ['required', 'array'], 'frequencies' => ['sometimes', 'array']];
        foreach (JobInvoices::MONTHS as $month) {
            $rules['amounts.'.$month] = ['required', 'numeric', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'];
            $rules['frequencies.'.$month] = ['nullable', 'in:yearly,monthly'];
        }
        $data = $request->validate($rules);

        $changes = DB::transaction(function () use ($job, $year, $data) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            app(JobInvoices::class)->ensureYear($job, $year);
            $before = $job->invoices()->where('year', '>=', $year)->get()->keyBy('id');
            app(JobInvoices::class)->update($job, $year, $data);

            return $job->invoices()->where('year', '>=', $year)->get()
                ->map(function (Invoice $invoice) use ($before) {
                    $previous = $before->get($invoice->id);
                    $details = [];

                    foreach (['amount', 'frequency', 'recurring', 'stops_recurrence'] as $field) {
                        if ($previous->getAttribute($field) !== $invoice->getAttribute($field)) {
                            $old = $previous->getAttribute($field);
                            $new = $invoice->getAttribute($field);
                            $label = str_replace('_', ' ', $field);
                            $old = is_bool($old) ? ($old ? 'yes' : 'no') : $old;
                            $new = is_bool($new) ? ($new ? 'yes' : 'no') : $new;
                            $details[] = "{$label} from {$old} to {$new}";
                        }
                    }

                    return ['invoice' => $invoice, 'details' => $details];
                })->filter(fn ($change) => $change['details'] !== []);
        });

        foreach ($changes as $change) {
            $invoice = $change['invoice'];
            app(ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $invoice,
                action: 'invoice_updated',
                description: "updated the {$invoice->month} {$invoice->year} invoice for job {$job->title} for {$job->client->name} ("
                    .implode('; ', $change['details']).')',
            );
        }

        return redirect()->route('invoice.form', ['job' => $job, 'year' => $year])
            ->with('success', 'Invoices updated successfully.');
    }

    public function invoiceAmount(Request $request, WorkJob $job, int $year, string $month): \Illuminate\Http\JsonResponse
    {
        abort_unless($year >= 1901 && $year <= 2155 && in_array($month, JobInvoices::MONTHS, true), 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:-9999999999.99,9999999999.99'],
        ]);

        $invoice = DB::transaction(function () use ($job, $year, $month, $data) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $invoice = $job->invoices()->where('year', $year)->where('month', $month)->first();
            if (! $invoice) {
                $amount = app(JobInvoices::class)->amountsForYear($job->invoices()->get(), $year)->get($month);
                $invoice = $job->invoices()->create([
                    'client_id' => $job->client_id, 'year' => $year, 'month' => $month, 'amount' => $amount,
                ]);
            }
            $invoice->cell_amount = $data['amount'];
            $invoice->save();

            return $invoice;
        });

        if ($invoice->wasChanged('cell_amount')) {
            app(ActivityLogger::class)->record(
                actor: $request->user(), subject: $invoice, action: 'invoice_amount_updated',
                description: "changed the {$month} {$year} invoice amount for job {$job->title} from "
                    .($invoice->getPrevious()['cell_amount'] ?? $invoice->amount)." to {$invoice->cell_amount}",
            );
        }

        return response()->json(['amount' => $invoice->cell_amount]);
    }

    public function invoiceState(Request $request, WorkJob $job, int $year, string $month): \Illuminate\Http\JsonResponse
    {
        abort_unless($year >= 1901 && $year <= 2155 && in_array($month, JobInvoices::MONTHS, true), 404);
        $data = $request->validate([
            'state' => ['required', 'in:In Progress,Ready To Invoice,Already Invoiced'],
        ]);

        $invoice = DB::transaction(function () use ($job, $year, $month, $data) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $invoice = app(JobInvoices::class)->ensureYear($job, $year)->get($month);
            $invoice->update(['state' => $data['state']]);

            return $invoice;
        });

        if ($invoice->wasChanged('state')) {
            app(ActivityLogger::class)->record(
                actor: $request->user(),
                subject: $invoice,
                action: 'invoice_state_updated',
                description: "changed the {$month} {$year} invoice status for job {$job->title} for {$job->client->name} from "
                    .($invoice->getPrevious()['state'] ?? 'unset')." to {$invoice->state}",
            );
        }

        return response()->json(['state' => $invoice->state]);
    }

    public function tableView(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', 'in:all,active,complete'],
            'year' => ['nullable', 'integer', 'between:1901,2155'],
            'range' => ['nullable', 'in:rolling,financial'],
        ]);
        $status = $request->input('status', 'all');
        $today = now()->startOfMonth();
        $range = $request->input('range', 'rolling');
        $financialYear = $today->month >= 5 ? $today->year : $today->year - 1;
        $year = (int) ($request->input('year') ?? $financialYear);
        $start = $range === 'financial'
            ? $today->copy()->setDate($year, 5, 1)
            : $today->copy()->subMonths(3);
        $months = collect(range(0, $range === 'financial' ? 11 : 6))
            ->mapWithKeys(function ($offset) use ($start) {
                $date = $start->copy()->addMonths($offset);

                return [$date->format('Y-m') => $date];
            });
        $currentMonth = $months->has($today->format('Y-m')) ? $today->format('Y-m') : null;
        $periodLabel = $months->first()->format('M Y').' – '.$months->last()->format('M Y');
        $displayYears = $months->map->year->unique()->values();
        $jobs = WorkJob::with('client')
            ->when($status === 'active', fn ($query) => $query->where('active', true)->where('completed', false))
            ->when($status === 'complete', fn ($query) => $query->where('completed', true))
            ->orderBy('project_type')->orderBy('title')->get();
        $invoices = Invoice::whereIn('work_job_id', $jobs->modelKeys())
            ->where('year', '<=', $displayYears->max())
            ->where(fn ($query) => $query->whereIn('year', $displayYears)
                ->orWhere('recurring', true)->orWhere('stops_recurrence', true))
            ->get()->groupBy('work_job_id');
        $amounts = $jobs->mapWithKeys(function ($job) use ($invoices, $displayYears, $months) {
            $yearAmounts = $displayYears->mapWithKeys(fn ($displayYear) => [
                $displayYear => app(JobInvoices::class)->amountsForYear($invoices->get($job->id, collect()), $displayYear),
            ]);

            return [$job->id => $months->map(fn ($date) => $yearAmounts[$date->year][$date->format('F')])];
        });
        $states = $invoices->map(fn ($rows) => $months->map(fn ($date) => $rows
            ->first(fn ($invoice) => (int) $invoice->year === $date->year && $invoice->month === $date->format('F'))?->state ?? 'In Progress'));
        $clientGroups = $jobs->groupBy('client_id')
            ->sortBy(fn ($group) => mb_strtolower($group->first()->client?->name ?? 'Unassigned'));

        $total = $amounts->sum(fn ($values) => $values->sum());
        $monthlyTotals = $months->map(fn ($date, $month) => $amounts->sum(fn ($values) => (float) $values[$month]));
        $activeCount = $jobs->filter(fn ($job) => $job->active && ! $job->completed)->count();

        return view('admin.jobs.table', compact('clientGroups', 'amounts', 'year', 'currentMonth', 'months', 'status', 'total', 'monthlyTotals', 'activeCount', 'states', 'range', 'periodLabel', 'financialYear'));
    }

    public function destroy(Request $request, WorkJob $job): RedirectResponse
    {
        DB::transaction(function () use ($job) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $job->invoices()->delete();
            $job->delete();
        });

        app(ActivityLogger::class)->record(
            actor: $request->user(),
            subject: $job,
            action: 'job_deleted',
            description: "deleted job {$job->title} for {$job->client->name} and all its associated invoices",
        );

        return to_route('jobs.index')->with('success', 'Job and all associated invoices were deleted successfully.');
    }

}
