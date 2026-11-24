<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\WorkJob;
use App\Services\JobInvoices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'admin']));
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorForRememberedSession::class);
    }

    private function job(): WorkJob
    {
        $client = Client::firstOrCreate(['name' => 'Invoice client']);

        return WorkJob::create(['title' => 'Job', 'project_type' => 'Website', 'client_id' => $client->id]);
    }

    public function test_invoice_changes_are_audited_and_unchanged_saves_are_skipped(): void
    {
        $job = $this->job();
        $amounts = array_fill_keys(JobInvoices::MONTHS, '0.00');
        $amounts['March'] = '125.50';
        $url = route('invoice.update', ['job' => $job, 'year' => 2026]);

        $this->patch($url, ['amounts' => $amounts])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('audit_invoices_logs', [
            'entity_id' => (string) $job->invoices()->where('month', 'March')->value('id'),
            'action' => 'invoice_updated',
        ]);
        $entry = \Illuminate\Support\Facades\DB::table('audit_invoices_logs')->first();
        $this->assertStringContainsString('amount from 0.00 to 125.50', json_decode($entry->metadata, true)['message']);

        $this->patch($url, ['amounts' => $amounts])->assertRedirect();
        $this->assertDatabaseCount('audit_invoices_logs', 1);

        $stateUrl = route('invoice.state', ['job' => $job, 'year' => 2026, 'month' => 'March']);
        $this->patchJson($stateUrl, ['state' => 'Ready To Invoice'])->assertOk();
        $entry = \Illuminate\Support\Facades\DB::table('audit_invoices_logs')->where('action', 'invoice_state_updated')->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('from In Progress to Ready To Invoice', json_decode($entry->metadata, true)['message']);

        $this->patchJson($stateUrl, ['state' => 'Ready To Invoice'])->assertOk();
        $this->assertDatabaseCount('audit_invoices_logs', 2);
    }

    public function test_table_shows_current_year_and_inherited_amounts_without_creating_records(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-11-18 12:00:00'));
        $job = $this->job();
        Invoice::create([
            'work_job_id' => $job->id, 'client_id' => $job->client_id,
            'year' => 2026, 'month' => 'March', 'amount' => '125.50',
            'frequency' => 'yearly', 'recurring' => true,
        ]);
        Invoice::create([
            'work_job_id' => $job->id, 'client_id' => $job->client_id,
            'year' => 2027, 'month' => 'March', 'amount' => '80.00',
            'frequency' => 'yearly', 'recurring' => false,
        ]);

        $this->get(route('job.table'))->assertOk()
            ->assertViewHas('year', 2027)->assertViewHas('currentMonth', 'November')
            ->assertViewHas('amounts', fn ($amounts) => $amounts[$job->id]['March'] === '80.00'
                && $amounts[$job->id]['April'] === '0.00')
            ->assertSee('Invoice client')->assertSee('aria-current="date"', false)
            ->assertSee(route('invoice.form', ['job' => $job, 'year' => 2027]), false);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_empty_invoice_table_renders(): void
    {
        $this->get(route('job.table'))->assertOk()->assertSee('No jobs to display.');
    }

    public function test_month_state_cycles_and_preserves_amounts_and_other_months(): void
    {
        $job = $this->job();
        $invoices = app(JobInvoices::class)->ensureYear($job, 2026);
        $invoices['March']->update(['amount' => '125.50']);
        $url = route('invoice.state', ['job' => $job, 'year' => 2026, 'month' => 'March']);

        foreach (['Ready To Invoice', 'Already Invoiced', 'In Progress'] as $state) {
            $this->patchJson($url, ['state' => $state])->assertOk()->assertJson(['state' => $state]);
            $this->assertSame($state, $invoices['March']->fresh()->state);
            $this->assertSame('125.50', $invoices['March']->fresh()->amount);
            $this->assertSame('In Progress', $invoices['April']->fresh()->state);
        }
    }

    public function test_month_state_validates_input_and_materializes_inherited_amount(): void
    {
        $job = $this->job();
        Invoice::create([
            'work_job_id' => $job->id, 'client_id' => $job->client_id,
            'year' => 2025, 'month' => 'March', 'amount' => '125.50',
            'frequency' => 'yearly', 'recurring' => true,
        ]);
        $url = route('invoice.state', ['job' => $job, 'year' => 2026, 'month' => 'March']);
        $this->patchJson($url, ['state' => 'invalid'])->assertUnprocessable();
        $this->patchJson(route('invoice.state', ['job' => $job, 'year' => 2026, 'month' => 'invalid']), ['state' => 'Ready To Invoice'])->assertNotFound();
        $this->assertDatabaseCount('invoices', 1);
        $this->patchJson($url, ['state' => 'Ready To Invoice'])->assertOk();
        $this->assertDatabaseHas('invoices', [
            'work_job_id' => $job->id, 'year' => 2026, 'month' => 'March',
            'amount' => 125.50, 'state' => 'Ready To Invoice',
        ]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23'));
        $this->get(route('job.table'))->assertOk()->assertViewHas('states',
            fn ($states) => $states[$job->id]['March'] === 'Ready To Invoice');
    }

    public function test_table_browses_years_and_preserves_filter_without_highlighting_other_years(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23'));
        $job = $this->job();
        Invoice::create([
            'work_job_id' => $job->id, 'client_id' => $job->client_id,
            'year' => 2025, 'month' => 'March', 'amount' => '125.50',
            'frequency' => 'yearly', 'recurring' => true,
        ]);

        foreach ([2025, 2027] as $year) {
            $this->get(route('job.table', ['year' => $year, 'status' => 'all']))
                ->assertOk()->assertViewHas('year', $year)->assertViewHas('currentMonth', null)
                ->assertViewHas('amounts', fn ($amounts) => $amounts[$job->id]['March'] === '125.50')
                ->assertSee(route('job.table', ['year' => $year - 1, 'status' => 'all']))
                ->assertSee(route('job.table', ['year' => $year + 1, 'status' => 'all']))
                ->assertSee(route('job.table', ['status' => 'active', 'year' => $year]))
                ->assertDontSee('aria-current="date"', false);
        }
        $this->assertDatabaseCount('invoices', 1);
        foreach ([1900, 2156, 'invalid'] as $year) {
            $this->getJson(route('job.table', ['year' => $year]))->assertUnprocessable();
        }
    }

    public function test_jobs_without_invoices_or_with_only_zero_amounts_can_be_deleted(): void
    {
        $empty = $this->job();
        $this->delete(route('jobs.destroy', $empty))->assertRedirect(route('jobs.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($empty);

        $job = $this->job();
        app(JobInvoices::class)->ensureYear($job, 2025);
        app(JobInvoices::class)->ensureYear($job, 2027);
        $this->delete(route('jobs.destroy', $job))->assertRedirect(route('jobs.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($job);
        $this->assertDatabaseMissing('invoices', ['work_job_id' => $job->id]);
    }

    public function test_deleting_jobs_removes_all_invoices_regardless_of_amount_state_or_year(): void
    {
        $other = $this->job();
        $service = app(JobInvoices::class);
        $otherInvoices = $service->ensureYear($other, 2024);
        $otherInvoices['March']->update(['amount' => '75.00']);
        foreach (['In Progress', 'Ready To Invoice', 'Already Invoiced'] as $state) {
            $job = $this->job();
            $invoices = $service->ensureYear($job, 2024);
            $service->ensureYear($job, 2027);
            $invoices['March']->update(['amount' => '100.00', 'state' => $state]);
            $invoices['April']->update(['amount' => '-100.00']);
            $this->get(route('jobs.index'))->assertOk()
                ->assertSee('all associated invoices across every year')
                ->assertSee('delete-jobs-'.$job->id)
                ->assertSee(route('jobs.destroy', $job), false);
            $this->delete(route('jobs.destroy', $job))->assertRedirect(route('jobs.index'))->assertSessionHasNoErrors();
            $this->assertModelMissing($job);
            $this->assertDatabaseMissing('invoices', ['work_job_id' => $job->id]);
        }
        $this->assertModelExists($other);
        $this->assertSame(12, $other->invoices()->count());
        $this->assertSame('75.00', $otherInvoices['March']->fresh()->amount);
    }

    public function test_reassigning_a_job_moves_invoice_ownership_and_survives_deleting_the_old_client(): void
    {
        $job = $this->job();
        $oldClient = $job->client;
        $newClient = Client::create(['name' => 'New client']);
        $service = app(JobInvoices::class);
        $invoices = $service->ensureYear($job, 2025);
        $invoices['March']->update(['amount' => '125.50', 'frequency' => 'monthly', 'recurring' => true, 'state' => 'Already Invoiced']);
        $invoices['July']->update(['amount' => '0.00', 'stops_recurrence' => true]);
        $service->ensureYear($job, 2027);
        $before = $job->invoices()->get()->mapWithKeys(fn ($invoice) => [
            $invoice->id => $invoice->only(['amount', 'year', 'month', 'frequency', 'recurring', 'stops_recurrence', 'state']),
        ])->all();
        // A duplicate title must not change the redirect target or another job's ownership.
        $other = WorkJob::create(['title' => $job->title, 'client_id' => $newClient->id, 'project_type' => '']);
        $otherInvoice = $service->ensureYear($other, 2025)['March'];
        $otherInvoice->update(['amount' => '75.00']);

        $this->patch(route('jobs.update', $job), [
            'title' => $job->title, 'client_id' => $newClient->id, 'project_type' => null,
            'active' => true, 'completed' => false,
        ])->assertSessionHasNoErrors()->assertRedirect(route('jobs.edit', $job));
        $this->assertEquals($newClient->id, $job->fresh()->client_id);
        $this->assertSame(24, $job->invoices()->count());
        foreach ($job->invoices()->get() as $invoice) {
            $this->assertEquals($newClient->id, $invoice->client_id);
            $this->assertSame($before[$invoice->id], $invoice->only(array_keys($before[$invoice->id])));
        }
        $this->assertSame('75.00', $otherInvoice->fresh()->amount);
        $this->delete(route('clients.destroy', $oldClient))->assertRedirect(route('clients.index'));
        $this->assertModelMissing($oldClient);
        $this->assertModelExists($job);
        $this->assertSame(24, $job->invoices()->count());
        $this->assertSame('125.50', $invoices['March']->fresh()->amount);
        $this->assertTrue($invoices['March']->fresh()->recurring);
        $this->assertSame('Already Invoiced', $invoices['March']->fresh()->state);
        $this->assertTrue($invoices['July']->fresh()->stops_recurrence);
    }

    private function data(WorkJob $job, int $year): array
    {
        $invoices = app(JobInvoices::class)->ensureYear($job, $year);

        return [
            'amounts' => $invoices->map->amount->all(),
            'frequencies' => array_filter(app(JobInvoices::class)->frequenciesFor($job, $invoices)),
        ];
    }

    public function test_opening_a_year_creates_twelve_records_once_for_each_job(): void
    {
        $job = $this->job();
        $this->get(route('invoice.form', ['job' => $job, 'year' => 2026]))->assertOk();
        $this->get(route('invoice.form', ['job' => $job, 'year' => 2026]))->assertOk();
        $this->assertDatabaseCount('invoices', 12);
        app(JobInvoices::class)->ensureYear($this->job(), 2026);
        $this->assertDatabaseCount('invoices', 24);
    }

    public function test_yearly_changes_repeat_in_existing_and_unopened_years_only_in_the_same_month(): void
    {
        $job = $this->job();
        $other = $this->job();
        $data = $this->data($job, 2026);
        $this->data($job, 2027);
        $this->data($job, 2025);
        $this->data($other, 2027);
        $data['amounts']['March'] = '125.50';
        $data['frequencies']['March'] = 'yearly';
        $this->patch(route('invoice.update', ['job' => $job, 'year' => 2026]), $data)
            ->assertSessionHasNoErrors()->assertRedirect(route('invoice.form', ['job' => $job, 'year' => 2026]));
        $future = app(JobInvoices::class)->ensureYear($job, 2030);
        $this->assertSame('125.50', $future['March']->amount);
        $this->assertSame('0.00', $future['April']->amount);
        $this->assertDatabaseHas('invoices', ['work_job_id' => $job->id, 'year' => 2027, 'month' => 'March', 'amount' => 125.50]);
        $this->assertDatabaseHas('invoices', ['work_job_id' => $job->id, 'year' => 2025, 'month' => 'March', 'amount' => 0]);
        $this->assertDatabaseHas('invoices', ['work_job_id' => $other->id, 'year' => 2027, 'month' => 'March', 'amount' => 0]);
    }

    public function test_monthly_overwrites_later_submitted_values_and_future_rules(): void
    {
        $job = $this->job();
        $service = app(JobInvoices::class);
        $future = $this->data($job, 2027);
        $future['amounts']['June'] = '500';
        $future['frequencies']['June'] = 'yearly';
        $service->update($job, 2027, $future);
        $data = $this->data($job, 2026);
        $data['amounts']['March'] = '100';
        $data['frequencies']['March'] = 'monthly';
        $data['amounts']['April'] = '999';
        $service->update($job, 2026, $data);
        $current = $service->ensureYear($job, 2026);
        $this->assertSame('0.00', $current['February']->amount);
        $this->assertSame('100.00', $current['April']->amount);
        $this->assertSame('100.00', $service->ensureYear($job, 2027)['June']->amount);
        $this->assertSame('100.00', $service->ensureYear($job, 2030)['January']->amount);
        $service->update($job, 2026, $this->data($job, 2026));
        $this->assertSame(1, Invoice::where('work_job_id', $job->id)->where('recurring', true)->count());
    }

    public function test_narrowing_monthly_recurrence_clears_inherited_amounts_but_preserves_one_offs(): void
    {
        $job = $this->job();
        $service = app(JobInvoices::class);
        $data = $this->data($job, 2026);
        $data['amounts']['March'] = '100';
        $data['frequencies']['March'] = 'monthly';
        $service->update($job, 2026, $data);
        $current = $service->ensureYear($job, 2026);
        $future = $service->ensureYear($job, 2027);
        // Explicit one-off amounts do not match the inherited monthly amount.
        $current['May']->update(['amount' => '75.00']);
        $future['June']->update(['amount' => '225.00']);
        $data = $this->data($job, 2026);
        $data['frequencies']['March'] = 'yearly';
        $this->patch(route('invoice.update', ['job' => $job, 'year' => 2026]), $data)
            ->assertSessionHasNoErrors()->assertRedirect();

        $current = $service->ensureYear($job, 2026);
        $future = $service->ensureYear($job, 2027);
        $unopened = $service->amountsForYear(Invoice::where('work_job_id', $job->id)->get(), 2028);
        foreach (JobInvoices::MONTHS as $month) {
            $this->assertSame(match ($month) {
                'March' => '100.00', 'May' => '75.00', default => '0.00',
            }, $current[$month]->amount);
            $this->assertSame(match ($month) {
                'March' => '100.00', 'June' => '225.00', default => '0.00',
            }, $future[$month]->amount);
            $this->assertSame($month === 'March' ? '100.00' : '0.00', $unopened[$month]);
        }
        $this->assertSame('yearly', $current['April']->frequency);
        $this->assertSame(1, Invoice::where('work_job_id', $job->id)->where('recurring', true)->count());
        // Saving the narrowed form again must not revive the old monthly rule.
        $service->update($job, 2026, $this->data($job, 2026));
        $this->assertSame('0.00', $current['April']->fresh()->amount);
        $this->assertSame('225.00', $future['June']->fresh()->amount);
    }

    public function test_invalid_amounts_do_not_create_records(): void
    {
        $job = $this->job();
        $this->patch(route('invoice.update', ['job' => $job, 'year' => 2026]), [
            'amounts' => ['January' => 'invalid'], 'frequencies' => ['January' => 'weekly'],
        ])->assertSessionHasErrors(['amounts.January', 'frequencies.January']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_amounts_without_a_frequency_update_only_the_selected_month(): void
    {
        $job = $this->job();
        $data = $this->data($job, 2026);
        unset($data['frequencies']);
        $data['amounts']['March'] = '75';

        $this->patch(route('invoice.update', ['job' => $job, 'year' => 2026]), $data)
            ->assertSessionHasNoErrors()->assertRedirect();

        $service = app(JobInvoices::class);
        $this->assertSame('75.00', $service->ensureYear($job, 2026)['March']->amount);
        $this->assertSame('0.00', $service->ensureYear($job, 2026)['April']->amount);
        $this->assertSame('0.00', $service->ensureYear($job, 2027)['March']->amount);
    }

    public function test_selecting_yearly_after_saving_an_amount_creates_a_recurrence(): void
    {
        $job = $this->job();
        $service = app(JobInvoices::class);
        $data = $this->data($job, 2026);
        $data['amounts']['July'] = '55';
        $url = route('invoice.update', ['job' => $job, 'year' => 2026]);
        $this->patch($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('0.00', $service->ensureYear($job, 2027)['July']->amount);

        $data['frequencies']['July'] = 'yearly';
        $this->patch($url, $data)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue($service->ensureYear($job, 2026)['July']->recurring);
        $this->assertSame('55.00', $service->ensureYear($job, 2027)['July']->amount);
        $this->assertSame('55.00', $service->ensureYear($job, 2028)['July']->amount);
        $this->assertSame('0.00', $service->ensureYear($job, 2028)['August']->amount);
    }

    public function test_frequency_is_checked_for_inherited_rules_without_creating_new_rules_on_save(): void
    {
        $job = $this->job();
        $service = app(JobInvoices::class);
        $data = $this->data($job, 2026);
        $data['amounts']['July'] = '55';
        $data['frequencies']['July'] = 'yearly';
        $service->update($job, 2026, $data);

        $response = $this->get(route('invoice.form', ['job' => $job, 'year' => 2027]))->assertOk();
        $response->assertViewHas('followingFrequencies', fn ($frequencies) => $frequencies['July'] === 'yearly'
            && $frequencies['June'] === null);
        $this->assertMatchesRegularExpression('/name="frequencies\[July\]"\s+value="yearly"/', $response->getContent());

        $invoices = $service->ensureYear($job, 2027);
        $this->patch(route('invoice.update', ['job' => $job, 'year' => 2027]), [
            'amounts' => $invoices->map->amount->all(),
            'frequencies' => array_filter($service->frequenciesFor($job, $invoices)),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Invoice::where('work_job_id', $job->id)->where('recurring', true)->count());

        $data = $this->data($job, 2026);
        $data['amounts']['September'] = '80';
        $data['frequencies']['September'] = 'monthly';
        $service->update($job, 2026, $data);
        $this->get(route('invoice.form', ['job' => $job, 'year' => 2028]))->assertOk()
            ->assertViewHas('followingFrequencies', fn ($frequencies) => count(array_filter($frequencies, fn ($value) => $value === 'monthly')) === 12);
    }

    public function test_clearing_an_inherited_monthly_frequency_stops_recurrence_and_zeroes_later_amounts(): void
    {
        $job = $this->job();
        $service = app(JobInvoices::class);
        $data = $this->data($job, 2026);
        $data['amounts']['March'] = '55';
        $data['frequencies']['March'] = 'monthly';
        $service->update($job, 2026, $data);
        $service->ensureYear($job, 2027);

        $data = $this->data($job, 2026);
        // Only clear July in the request: later submitted selections must not restart recurrence.
        $data['frequencies']['July'] = null;
        $this->patch(route('invoice.update', ['job' => $job, 'year' => 2026]), $data)
            ->assertSessionHasNoErrors()->assertRedirect();

        $current = $service->ensureYear($job, 2026);
        $frequencies = $service->frequenciesFor($job, $current);
        $this->assertSame('monthly', $frequencies['June']);
        $this->assertSame('55.00', $current['June']->amount);
        $this->assertSame('0.00', $current['July']->amount);
        $this->assertNull($frequencies['July']);
        $this->assertNull($frequencies['December']);
        $this->assertSame('0.00', $current['December']->amount);
        $future = $service->ensureYear($job, 2027);
        $this->assertSame('0.00', $future['January']->amount);
        $this->assertEmpty(array_filter($service->frequenciesFor($job, $future)));
        $this->assertSame('0.00', $service->ensureYear($job, 2030)['January']->amount);
    }

    public function test_clearing_yearly_frequency_in_a_later_year_preserves_the_earlier_rule(): void
    {
        $job = $this->job();
        $service = app(JobInvoices::class);
        $data = $this->data($job, 2026);
        $data['amounts']['July'] = '55';
        $data['frequencies']['July'] = 'yearly';
        $service->update($job, 2026, $data);
        $data = $this->data($job, 2027);
        $data['frequencies']['July'] = null;
        $service->update($job, 2027, $data);
        $this->assertSame('yearly', $service->frequenciesFor($job, $service->ensureYear($job, 2026))['July']);
        $this->assertNull($service->frequenciesFor($job, $service->ensureYear($job, 2027))['July']);
        $this->assertSame('0.00', $service->ensureYear($job, 2028)['July']->amount);

        $data = $this->data($job, 2027);
        $data['amounts']['July'] = '55';
        $data['frequencies']['July'] = 'yearly';
        $service->update($job, 2027, $data);
        $this->assertSame('55.00', $service->ensureYear($job, 2028)['July']->amount);
    }
}
