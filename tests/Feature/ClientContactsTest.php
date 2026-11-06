<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientContactsTest extends TestCase
{
    use RefreshDatabase;

    private function contact(string $email = 'contact@example.com'): array
    {
        return ['name' => 'Jane Smith', 'role' => 'Manager', 'email' => $email, 'phone' => '0123456789', 'notes' => 'Main contact'];
    }

    public function test_contacts_are_available_only_on_the_edit_form(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $client->contacts()->create($this->contact());
        $this->actingAs(User::factory()->create());

        $this->get(route('clients.edit', $client))->assertOk()->assertSee('Add contact')->assertSee('contact@example.com');
        $this->get(route('clients.create'))->assertOk()->assertDontSee('Add contact');
    }

    public function test_contacts_can_be_added_updated_and_removed_with_the_client(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $this->actingAs(User::factory()->create());

        $this->put(route('clients.update', $client), [
            'name' => 'Acme', 'contacts_present' => '1',
            'contacts' => [$this->contact(), $this->contact('second@example.com')],
        ])->assertSessionHasNoErrors()->assertRedirect(route('clients.index'));

        $this->assertCount(2, $client->contacts);
        $contact = $client->contacts->first();
        $this->assertEquals($client->id, $contact->client->id);

        $this->put(route('clients.update', $client), [
            'name' => 'Acme', 'contacts_present' => '1',
            'contacts' => [array_merge($this->contact(), ['id' => $contact->id, 'name' => 'Updated name'])],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('client_contacts', 1);
        $this->assertDatabaseHas('client_contacts', ['id' => $contact->id, 'name' => 'Updated name']);

        $this->put(route('clients.update', $client), ['name' => 'Acme', 'contacts_present' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('client_contacts', 0);
    }

    public function test_another_clients_contact_cannot_be_changed_or_reassigned(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $other = Client::create(['name' => 'Other']);
        $contact = $other->contacts()->create($this->contact());

        $this->actingAs(User::factory()->create())->put(route('clients.update', $client), [
            'name' => 'Changed', 'contacts_present' => '1',
            'contacts' => [array_merge($this->contact('new@example.com'), ['id' => $contact->id])],
        ])->assertSessionHasErrors('contacts.0.id');

        $this->assertSame('Acme', $client->refresh()->name);
        $this->assertEquals($other->id, $contact->refresh()->client_id);
    }

    public function test_invalid_or_duplicate_contacts_do_not_save_partial_changes(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $other = Client::create(['name' => 'Other']);
        $other->contacts()->create($this->contact());
        $this->actingAs(User::factory()->create());

        $this->put(route('clients.update', $client), [
            'name' => 'Changed', 'contacts' => [$this->contact()],
        ])->assertSessionHasErrors('contacts.0.email');

        $this->put(route('clients.update', $client), [
            'name' => 'Changed', 'contacts' => [$this->contact('same@example.com'), $this->contact('same@example.com')],
        ])->assertSessionHasErrors('contacts.0.email');

        $this->put(route('clients.update', $client), [
            'name' => 'Changed', 'contacts' => [['name' => 'Jane']],
        ])->assertSessionHasErrors(['contacts.0.role', 'contacts.0.email']);

        $this->assertSame('Acme', $client->refresh()->name);
        $this->assertDatabaseCount('client_contacts', 1);
    }

    public function test_deleting_a_client_cascades_to_its_contacts(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $client->contacts()->create($this->contact());

        $this->actingAs(User::factory()->create())->delete(route('clients.destroy', $client))->assertRedirect();

        $this->assertDatabaseCount('client_contacts', 0);
    }
}
