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

    public function test_scoped_lists_validate_client_ids_and_missing_clients(): void
    {
        foreach (['passwords.index', 'jobs.index'] as $route) {
            $this->getJson(route($route, ['client_id' => 'invalid']))->assertUnprocessable();
            $this->getJson(route($route, ['client_id' => ['invalid']]))->assertUnprocessable();
            $this->getJson(route($route, ['client_id' => 99999]))->assertNotFound();
        }
    }

    public function test_password_search_matches_name_username_url_and_client_but_not_secrets(): void
    {
        $client = Client::create(['name' => 'Distinct client']);
        $password = Password::create(['client_id' => $client->id, 'name' => 'Control panel', 'username' => 'operator', 'password' => 'hidden-secret']);
        $password->forceFill(['url' => 'https://example.test/login'])->save();
        foreach (['Control', 'operator', 'example.test', 'Distinct'] as $search) {
            $this->get(route('passwords.index', ['search' => $search]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertViewHas('passwords', fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('passwords.index', ['search' => 'hidden-secret']))
            ->assertOk()->assertViewHas('passwords', fn ($rows) => $rows->total() === 0);
    }

    public function test_scoped_pagination_keeps_search_and_project_type_in_next_links(): void
    {
        $client = Client::create(['name' => 'Scoped client']);
        ProjectType::create(['name' => 'Website']);
        for ($i = 0; $i < 51; $i++) {
            Password::create(['client_id' => $client->id, 'name' => sprintf('Portal %02d', $i), 'username' => 'operator', 'password' => 'secret']);
            WorkJob::create(['client_id' => $client->id, 'title' => sprintf('Portal %02d', $i), 'project_type' => 'Website']);
        }
        foreach (['passwords.index', 'jobs.index'] as $route) {
            $filters = ['client_id' => $client->id, 'search' => 'Portal'];
            if ($route === 'jobs.index') $filters['project_type'] = 'Website';
            $response = $this->get(route($route, $filters), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
            $response->assertSee(e(route($route, [...$filters, 'page' => 2])), false);
            $this->get(route($route, [...$filters, 'page' => 2]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->assertSee('Portal 50')->assertDontSee('Portal 00');
        }
    }

    public function test_blank_searches_return_all_records_and_unknown_types_return_none(): void
    {
        $client = Client::create(['name' => 'Example']);
        Password::create(['client_id' => $client->id, 'name' => 'Portal', 'username' => 'operator', 'password' => 'secret']);
        WorkJob::create(['client_id' => $client->id, 'title' => 'Portal', 'project_type' => 'Website']);
        foreach (['clients.index' => 'clients', 'passwords.index' => 'passwords', 'jobs.index' => 'jobs'] as $route => $variable) {
            $this->get(route($route, ['search' => '   ']))->assertOk()
                ->assertViewHas($variable, fn ($rows) => $rows->total() === 1);
        }
        $this->get(route('jobs.index', ['project_type' => 'Unknown']))->assertOk()
            ->assertViewHas('jobs', fn ($rows) => $rows->total() === 0);
    }

    public function test_ajax_filters_require_authentication_and_jobs_require_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'employee']));
        $this->get(route('jobs.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();
        auth()->logout();
        foreach (['clients.index', 'passwords.index', 'jobs.index'] as $route) {
            $this->getJson(route($route, ['search' => 'test']))->assertUnauthorized();
        }
    }
}
