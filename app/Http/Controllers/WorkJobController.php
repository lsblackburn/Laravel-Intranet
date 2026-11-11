<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\WorkJob;
use App\Models\Client;
use App\Models\ProjectType;

class WorkJobController extends Controller
{
    
    private function validatedData(Request $request): array
    {
        // Validate the fields submitted by the job form.
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', 'max:255', 'exists:project_types,name'],
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

        return redirect()->route('jobs.index', ['client_id' => $job->client_id])->with('success', 'Job created successfully.');
    }

}
