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
        $client = null;
        $query = WorkJob::with('client');

        if ($request->filled('client_id')) {
            $request->validate(['client_id' => ['integer']]);
            $client = Client::findOrFail($request->input('client_id'));
            $query->where('client_id', $client->id);
        }

        $jobs = $query->orderBy('created_at', 'asc')->paginate(50)->withQueryString();

        return view('admin.jobs.index', compact('jobs', 'client'));
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

        $job->update($data);

        $job = WorkJob::where('title', $data['title'])->first();

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

        DB::transaction(function () use ($job, $year, $data) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            app(JobInvoices::class)->update($job, $year, $data);
        });

        return redirect()->route('invoice.form', ['job' => $job, 'year' => $year])
            ->with('success', 'Invoices updated successfully.');
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

        return response()->json(['state' => $invoice->state]);
    }

    public function tableView(Request $request): View
    {
        $request->validate([
            'status' => ['nullable', 'in:all,active,complete'],
            'year' => ['nullable', 'integer', 'between:1901,2155'],
        ]);
        $status = $request->input('status', 'all');
        $today = now();
        $year = (int) ($request->input('year') ?? $today->year);
        $currentMonth = $year === $today->year ? $today->format('F') : null;
        $months = JobInvoices::MONTHS;
        $jobs = WorkJob::with('client')
            ->when($status === 'active', fn ($query) => $query->where('active', true)->where('completed', false))
            ->when($status === 'complete', fn ($query) => $query->where('completed', true))
            ->orderBy('project_type')->orderBy('title')->get();
        $invoices = Invoice::whereIn('work_job_id', $jobs->modelKeys())
            ->where('year', '<=', $year)
            ->where(fn ($query) => $query->where('year', $year)
                ->orWhere('recurring', true)->orWhere('stops_recurrence', true))
            ->get()->groupBy('work_job_id');
        $amounts = $jobs->mapWithKeys(fn ($job) => [
            $job->id => app(JobInvoices::class)->amountsForYear($invoices->get($job->id, collect()), $year),
        ]);
        $states = $invoices->map(fn ($rows) => $rows->where('year', $year)->pluck('state', 'month'));
        $clientGroups = $jobs->groupBy('client_id')
            ->sortBy(fn ($group) => mb_strtolower($group->first()->client?->name ?? 'Unassigned'));

        $total = $amounts->sum(fn ($values) => $values->sum());
        $activeCount = $jobs->filter(fn ($job) => $job->active && ! $job->completed)->count();

        return view('admin.jobs.table', compact('clientGroups', 'amounts', 'year', 'currentMonth', 'months', 'status', 'total', 'activeCount', 'states'));
    }

    public function destroy(WorkJob $job): RedirectResponse
    {
        DB::transaction(function () use ($job) {
            $job = WorkJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $job->invoices()->delete();
            $job->delete();
        });

        return to_route('jobs.index')->with('success', 'Job and all associated invoices were deleted successfully.');
    }

}
