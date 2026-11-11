<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use App\Models\Client;
use Illuminate\Validation\Rule;

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

        return view('credentials.clients.index', compact('clients'));
    }

    public function create(): View
    {
        // Function to show the form for creating a new client
        return view('credentials.clients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        // Function to store a newly created client in the database
        $data = $this->validatedData($request);

        Client::create($data);

        $client = Client::where('name', $data['name'])->first();

        return redirect()->route('clients.edit', $client)->with('success', 'Client created successfully.');
    }

    public function edit(Client $client): View
    {
        // Function to show the form for editing an existing client
        $client->load('contacts');

        return view('credentials.clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        // Function to update an existing client in the database
        $submittedContacts = $request->input('contacts', []);
        $retainedIds = collect(is_array($submittedContacts) ? $submittedContacts : [])
            ->filter(fn ($contact) => is_array($contact))
            ->pluck('id')
            ->filter(fn ($id) => is_scalar($id) && $id !== '')
            ->all();
        // These contacts are deleted before replacements are saved in the transaction below.
        $removedIds = $client->contacts()->whereNotIn('id', $retainedIds)->pluck('id')->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('clients', 'name')->ignore($client)],
            'contacts_present' => ['sometimes', 'accepted'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*' => ['array:id,name,role,email,phone,notes'],
            'contacts.*.id' => [
                'nullable', 'integer', 'distinct',
                Rule::exists('client_contacts', 'id')->where('client_id', $client->id),
            ],
            'contacts.*.name' => ['required', 'string', 'max:255'],
            'contacts.*.role' => ['required', 'string', 'max:255'],
            'contacts.*.email' => Rule::forEach(function ($value, $attribute) use ($request, $client, $removedIds) {
                $id = $request->input(str_replace('.email', '.id', $attribute));
                $contact = is_scalar($id) ? $client->contacts()->find($id) : null;

                return [
                    'required', 'email', 'max:255', 'distinct:ignore_case',
                    Rule::unique('client_contacts', 'email')->ignore($contact)
                        ->where(fn ($query) => $query->whereNotIn('id', $removedIds)),
                ];
            }),
            'contacts.*.phone' => ['nullable', 'string', 'max:255'],
            'contacts.*.notes' => ['nullable', 'string', 'max:65535'],
        ]);

        DB::transaction(function () use ($client, $data) {
            $client->update(['name' => $data['name']]);

            if (! isset($data['contacts_present']) && ! array_key_exists('contacts', $data)) {
                return;
            }

            $contacts = collect($data['contacts'] ?? []);
            $client->contacts()->whereNotIn('id', $contacts->pluck('id')->filter()->all())->delete();

            foreach ($contacts as $contact) {
                $id = $contact['id'] ?? null;
                unset($contact['id']);

                if ($id) {
                    $client->contacts()->findOrFail($id)->update($contact);
                } else {
                    $client->contacts()->create($contact);
                }
            }
        });

        return redirect()->route('clients.index')->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        // Function to delete a client and its associated data from the database
        DB::transaction(fn () => $client->delete());

        return to_route('clients.index')->with('success', 'Client and its associated data was deleted successfully.');
    }

}
