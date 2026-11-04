<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Models\Password;
use App\Models\Client;

class PasswordController extends Controller
{
    
     private function validatedData(Request $request): array
    {
        // Function to validate the request data for creating or updating a client
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required'],
            'notes' => ['nullable'],
            'client_id' => ['required', 'exists:clients,id'],
        ]);
    }
    
    public function index(): View
    {
        // Function to display a paginated list of clients ordered by name
        $passwords = Password::with('client')->orderBy('name', 'asc')->paginate(50);

        return view('auth.credentials.passwords.index', compact('passwords'));
    }

    public function create(): View
    {
        // Function to show the form for creating a new password
        $clients = Client::orderBy('name', 'asc')->get();

        return view('auth.credentials.passwords.create', compact('clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Function to store a newly created password in the database
        $data = $this->validatedData($request);

        Password::create($data);

        return redirect()->route('passwords.index')->with('success', 'Password created successfully.');
    }

    public function edit(Password $password): View
    {
        // Fetches all clients ordered by name to populate a dropdown in the edit form
        $clients = Client::orderBy('name', 'asc')->get();

        // Function to show the form for editing an existing password
        $password = Password::findOrFail($password->id);

        return view('auth.credentials.passwords.edit', compact('password', 'clients'));
    }

    public function update(Request $request, Password $password): RedirectResponse
    {
        // Function to update an existing password in the database
        $data = $this->validatedData($request);

        $password->update($data);

        return redirect()->route('passwords.index')->with('success', 'Password updated successfully.');
    }

    public function destroy(Password $password): RedirectResponse
    {
        // Function to delete a password and its associated data from the database
        DB::transaction(fn () => $password->delete());

        return to_route('passwords.index')->with('success', 'Password and its associated data was deleted successfully.');
    }

}
