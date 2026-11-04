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
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
    
    public function index(): View
    {
        $clients = Client::orderBy('name', 'asc')->paginate(50);

        return view('auth.credentials.clients.index', compact('clients'));
    }

    public function create(): View
    {
        return view('auth.credentials.clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        Client::create($data);

        return redirect()->route('clients.index')->with('success', 'Client created successfully.');
    }

    public function edit(Client $client): View
    {
        $client = Client::findOrFail($client->id);

        return view('auth.credentials.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validatedData($request);

        $client->update($data);

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        DB::transaction(fn () => $client->delete());

        return to_route('clients.index')->with('success', 'Client and its associated data was deleted successfully.');
    }

}
