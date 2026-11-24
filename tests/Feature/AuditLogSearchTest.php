<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ajax_search_returns_only_matching_results_without_the_page_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        app(ActivityLogger::class)->record($admin, $admin, 'matching', 'updated Acme invoice');
        app(ActivityLogger::class)->record($admin, $admin, 'other', 'updated Other invoice');

        $this->get(route('admin.view-audit-logs', ['search' => 'Acme']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertViewIs('admin.partials.audit-log-results')
            ->assertSee('updated Acme invoice')
            ->assertDontSee('updated Other invoice')
            ->assertDontSee('View Audit Logs')
            ->assertDontSee('<html', false);

        $this->get(route('admin.view-audit-logs', ['search' => 'Acme']))
            ->assertOk()->assertViewIs('admin.audit-logs')->assertSee('View Audit Logs')
            ->assertSee('updated Acme invoice')->assertDontSee('updated Other invoice');
    }

    public function test_empty_ajax_search_results_do_not_include_the_layout(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.view-audit-logs', ['search' => 'missing']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('No logs match your filters.')->assertDontSee('View Audit Logs');
    }

    public function test_time_filters_include_boundary_minutes_and_combine_with_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        foreach (['09:59:59' => 'Before', '10:00:00' => 'Start', '11:00:59' => 'End', '11:01:00' => 'After'] as $time => $label) {
            $this->travelTo(\Carbon\Carbon::parse('2026-11-24 '.$time));
            app(ActivityLogger::class)->record($admin, $admin, 'example', 'updated Acme '.$label);
        }
        $filters = ['search' => 'Acme', 'from' => '2026-11-24T10:00', 'to' => '2026-11-24T11:00'];
        $this->get(route('admin.view-audit-logs', $filters), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Acme Start')->assertSee('Acme End')
            ->assertDontSee('Acme Before')->assertDontSee('Acme After');
        $this->get(route('admin.view-audit-logs', ['from' => $filters['from']]))
            ->assertOk()->assertSee('Acme After')->assertDontSee('Acme Before');
        $this->get(route('admin.view-audit-logs', ['to' => $filters['to']]))
            ->assertOk()->assertSee('Acme Before')->assertDontSee('Acme After');
        $this->get(route('admin.view-audit-logs', ['from' => $filters['from'], 'to' => '2026-11-23T10:00']))
            ->assertSessionHasErrors('to');
        $this->get(route('admin.view-audit-logs', ['from' => 'invalid']))->assertSessionHasErrors('from');
        $this->travelBack();
    }
}
