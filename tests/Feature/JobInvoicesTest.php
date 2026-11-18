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
        $this->actingAs(\App\Models\User::factory()->create());
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorForRememberedSession::class);
    }

    private function job(): WorkJob
    {
        $client = Client::firstOrCreate(['name' => 'Invoice client']);

        return WorkJob::create(['title' => 'Job', 'project_type' => 'Website', 'client_id' => $client->id]);
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
