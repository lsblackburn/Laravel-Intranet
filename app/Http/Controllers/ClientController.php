<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Models\Client;

class ClientController extends Controller
{

    private function validatedData(Request $request): array
    {
        // Function to validate the request data for creating or updating a client
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:clients,name'],
        ]);
    }
    
    public function index(): View
    {
        // Function to display a paginated list of clients ordered by name
        $clients = Client::orderBy('name', 'asc')->paginate(50);

        return view('auth.credentials.clients.index', compact('clients'));
    }

    public function create(): View
    {
        // Function to show the form for creating a new client
        return view('auth.credentials.clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // Function to store a newly created client in the database
        $data = $this->validatedData($request);

        Client::create($data);

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function edit(Client $client): View
    {
        // Function to show the form for editing an existing client
        $client = Client::findOrFail($client->id);

        return view('auth.credentials.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        // Function to update an existing client in the database
        $data = $this->validatedData($request);

        $client->update($data);

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        // Function to delete a client and its associated data from the database
        DB::transaction(fn () => $client->delete());

        return to_route('clients.index')->with('success', 'Client and its associated data was deleted successfully.');
    }

}
