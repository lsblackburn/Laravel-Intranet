<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Password;
use App\Models\ProjectType;
use App\Models\User;
use App\Models\WorkJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFilteringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_clients_search_and_pagination_return_only_results(): void
    {
        for ($i = 0; $i < 51; $i++) {
            Client::create(['name' => sprintf('Matching client %02d', $i)]);
        }
        Client::create(['name' => 'Excluded client']);

        $this->get(route('clients.index', ['search' => 'Matching']))->assertOk()
            ->assertSee('Search clients')->assertDontSee('Excluded client');
        $this->get(route('clients.index', ['search' => 'Matching', 'page' => 2]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Matching client 50')->assertDontSee('Matching client 00')
            ->assertDontSee('<html', false)->assertSee('search=Matching', false);
        $this->get(route('clients.index', ['search' => 'Nothing']))->assertOk()->assertSee('No clients match your search.');
    }

    public function test_password_search_stays_within_selected_client(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $other = Client::create(['name' => 'Other']);
        foreach ([[$client, 'Portal Alpha', 'alice'], [$client, 'Email Beta', 'bob'], [$other, 'Portal Gamma', 'alice']] as [$owner, $name, $username]) {
            Password::create(['client_id' => $owner->id, 'name' => $name, 'username' => $username, 'password' => 'secret']);
        }
        $this->get(route('passwords.index', ['client_id' => $client->id, 'search' => 'alice']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Portal Alpha')->assertDontSee('Email Beta')->assertDontSee('Portal Gamma')
            ->assertSee('client_id='.$client->id, false)->assertDontSee('<html', false);
        $this->get(route('passwords.index', ['search' => 'Acme']))->assertOk()
            ->assertSee('Portal Alpha')->assertSee('Email Beta')->assertDontSee('Portal Gamma');
        $this->get(route('passwords.index', ['search' => 'Nothing']))->assertOk()->assertSee('No passwords match your search.');
    }

    public function test_jobs_combine_search_project_type_and_client_filters(): void
    {
        ProjectType::create(['name' => 'Website']);
        ProjectType::create(['name' => 'Hosting']);
        $client = Client::create(['name' => 'Acme']);
        $other = Client::create(['name' => 'Other']);
        foreach ([[$client, 'Website', 'Portal Alpha'], [$client, 'Hosting', 'Portal Beta'], [$other, 'Website', 'Portal Gamma']] as [$owner, $type, $title]) {
            WorkJob::create(['client_id' => $owner->id, 'project_type' => $type, 'title' => $title]);
        }
        $this->get(route('jobs.index'))->assertOk()->assertSee('All project types')->assertSee('Hosting');
        $this->get(route('jobs.index', ['client_id' => $client->id, 'search' => 'Portal', 'project_type' => 'Website']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Portal Alpha')->assertDontSee('Portal Beta')->assertDontSee('Portal Gamma')
            ->assertSee('client_id='.$client->id, false)->assertDontSee('<html', false);
        $this->get(route('jobs.index', ['search' => 'Acme', 'project_type' => 'Hosting']))->assertOk()
            ->assertSee('Portal Beta')->assertDontSee('Portal Alpha')->assertDontSee('Portal Gamma');
        $this->get(route('jobs.index', ['search' => 'Nothing']))->assertOk()->assertSee('No jobs match your filters.');
    }

    public function test_filters_reject_arrays_and_overlong_searches(): void
    {
        foreach (['clients.index', 'passwords.index', 'jobs.index'] as $route) {
            $this->getJson(route($route, ['search' => ['invalid']]))->assertUnprocessable();
            $this->getJson(route($route, ['search' => str_repeat('a', 256)]))->assertUnprocessable();
        }
        $this->getJson(route('jobs.index', ['project_type' => ['invalid']]))->assertUnprocessable();
    }
}
