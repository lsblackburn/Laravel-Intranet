<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\WorkJob;
use App\Models\Client;

class WorkJobController extends Controller
{
    
    private function validatedData(Request $request): array
    {
        // Function to validate the request data for creating or updating a client
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required'],
            'notes' => ['nullable'],
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

}
