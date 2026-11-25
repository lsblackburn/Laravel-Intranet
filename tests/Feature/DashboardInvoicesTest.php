<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorForRememberedSession::class);
        $this->travelTo(Carbon::parse('2027-01-31 12:00:00'));
    }

    private function invoice(string $title, array $attributes = []): Invoice
    {
        $client = Client::firstOrCreate(['name' => 'Dashboard client']);
        $job = WorkJob::create(['title' => $title, 'client_id' => $client->id, 'project_type' => 'Website']);

        return Invoice::create(array_merge([
            'work_job_id' => $job->id, 'client_id' => $client->id,
            'year' => 2026, 'month' => 'December', 'amount' => '100.00', 'state' => 'In Progress',
        ], $attributes));
    }

    public function test_admin_sees_only_previous_month_outstanding_amounts_including_cell_edits_and_inheritance(): void
    {
        $this->invoice('Outstanding job');
        $this->invoice('Ready job', ['state' => 'Ready To Invoice', 'cell_amount' => '42.50']);
        $this->invoice('Inherited job', ['month' => 'November', 'recurring' => true, 'frequency' => 'monthly', 'amount' => '75.00', 'state' => 'Already Invoiced']);
        $this->invoice('Invoiced job', ['state' => 'Already Invoiced']);
        $this->invoice('Zero job', ['cell_amount' => '0.00']);
        $this->invoice('Current month job', ['year' => 2027, 'month' => 'January']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('December 2026')
            ->assertSee('Outstanding job')->assertSee('Ready job')->assertSee('Inherited job')
            ->assertSee('42.50')->assertSee('75.00')
            ->assertDontSee('Invoiced job')->assertDontSee('Zero job')->assertDontSee('Current month job')
            ->assertViewHas('outstandingInvoices', fn ($invoices) => $invoices->count() === 3);
        $this->assertDatabaseCount('invoices', 6);
    }

    public function test_admin_sees_completion_message_when_every_invoice_is_invoiced(): void
    {
        $this->invoice('Complete job', ['state' => 'Already Invoiced']);
        $this->get(route('dashboard'))->assertOk()
            ->assertSee("All previous month's invoices have been invoiced.", false)
            ->assertViewHas('outstandingInvoices', fn ($invoices) => $invoices->isEmpty());
    }

    public function test_non_admin_does_not_receive_invoice_data(): void
    {
        $this->invoice('Private invoice job');
        $this->actingAs(User::factory()->create(['role' => 'employee']));
        $this->get(route('dashboard'))->assertOk()
            ->assertDontSee('Outstanding invoices')->assertDontSee('Private invoice job')
            ->assertViewHas('outstandingInvoices', fn ($invoices) => $invoices->isEmpty());
    }

    public function test_current_month_progress_uses_only_invoiced_amounts_and_includes_cell_edits_and_inheritance(): void
    {
        $this->invoice('Invoiced this month', ['year' => 2027, 'month' => 'January', 'amount' => '300.00', 'cell_amount' => '250.00', 'state' => 'Already Invoiced']);
        $this->invoice('Ready this month', ['year' => 2027, 'month' => 'January', 'amount' => '50.00', 'state' => 'Ready To Invoice']);
        $this->invoice('Inherited this month', ['amount' => '50.00', 'frequency' => 'monthly', 'recurring' => true, 'state' => 'Already Invoiced']);
        $this->invoice('Last month only', ['amount' => '900.00', 'state' => 'Already Invoiced']);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('January 2027')->assertSee('&pound;250.00', false)->assertSee('/ &pound;350.00', false)
            ->assertViewHas('monthTotal', 350)->assertViewHas('monthInvoiced', 250)
            ->assertViewHas('invoicedPercentage', 71.4)
            ->assertSee('aria-valuenow="71.4"', false)->assertSee('width: 71.4%', false);
        $this->assertDatabaseCount('invoices', 4);
    }

    public function test_current_month_progress_handles_empty_and_fully_invoiced_months(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('monthTotal', 0)->assertViewHas('monthInvoiced', 0)
            ->assertSee('aria-valuenow="0"', false);
        $this->invoice('Fully invoiced', ['year' => 2027, 'month' => 'January', 'state' => 'Already Invoiced']);
        $this->get(route('dashboard'))->assertOk()
            ->assertViewHas('monthTotal', 100)->assertViewHas('monthInvoiced', 100)
            ->assertSee('aria-valuenow="100"', false)->assertSee('width: 100%', false);
    }
}
