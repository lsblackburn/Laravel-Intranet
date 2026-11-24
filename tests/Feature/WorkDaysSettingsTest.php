<?php

namespace Tests\Feature;

use App\Models\NonWorkDay;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\LeaveSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDaysSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
    }

    public function test_work_day_audits_describe_enabled_and_disabled_days_and_skip_unchanged_saves(): void
    {
        WorkDay::query()->update(['active' => false]);
        $monday = WorkDay::where('day', 'Monday')->firstOrFail();
        $tuesday = WorkDay::where('day', 'Tuesday')->firstOrFail();
        $monday->update(['active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->mock(\App\Services\ActivityLogger::class, function ($mock) use ($admin, $monday, $tuesday) {
            foreach ([[$monday, 'disabled Monday as a working day'], [$tuesday, 'enabled Tuesday as a working day']] as [$day, $message]) {
                $mock->shouldReceive('record')->once()->withArgs(
                    fn ($actor, $subject, $action, $description) => $actor->is($admin)
                        && $subject->is($day)
                        && $action === 'update_work_day'
                        && $description === $message
                );
            }
        });

        $payload = ['work_days' => [$tuesday->id]];
        $this->patch(route('admin.work-days.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('admin.work-days.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_admin_can_update_working_days(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $monday = WorkDay::where('day', 'Monday')->firstOrFail();
        $wednesday = WorkDay::where('day', 'Wednesday')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.work-days.update'), [
                'work_days' => [$monday->id, $wednesday->id],
            ]);

        $response->assertRedirect(route('admin.view-leave-rules'));

        $this->assertTrue($monday->refresh()->active);
        $this->assertTrue($wednesday->refresh()->active);
        $this->assertFalse(WorkDay::where('day', 'Tuesday')->firstOrFail()->active);
    }

    public function test_admin_must_select_at_least_one_working_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.view-leave-rules'))
            ->patch(route('admin.work-days.update'), [
                'work_days' => [],
            ]);

        $response
            ->assertRedirect(route('admin.view-leave-rules'))
            ->assertSessionHasErrors('work_days');
    }

    public function test_admin_cannot_add_duplicate_non_work_day_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        NonWorkDay::create([
            'name' => 'Existing bank holiday',
            'date' => '2026-05-15',
        ]);

        $response = $this
            ->actingAs($admin)
            ->from(route('admin.view-leave-rules'))
            ->post(route('admin.non-work-days.create'), [
                'name' => 'Duplicate bank holiday',
                'date' => '15-05-2026',
            ]);

        $response
            ->assertRedirect(route('admin.view-leave-rules'))
            ->assertSessionHasErrors('date');

        $this->assertSame(1, NonWorkDay::whereDate('date', '2026-05-15')->count());
    }

    public function test_admin_can_add_non_work_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.non-work-days.create'), [
                'name' => 'Bank holiday',
                'date' => '25-05-2026',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.view-leave-rules'));

        $this->assertDatabaseHas('non_work_days', [
            'name' => 'Bank holiday',
            'date' => '2026-05-25',
        ]);
    }

    public function test_non_work_days_list_keeps_current_allowance_year_dates_visible(): void
    {
        $this->travelTo('2026-05-15');

        $admin = User::factory()->create(['role' => 'admin']);

        LeaveSetting::first()->update([
            'leave_refresh_day' => 1,
            'leave_refresh_month' => 4,
        ]);

        NonWorkDay::create([
            'name' => 'Previous allowance year closure',
            'date' => '2026-03-31',
        ]);

        NonWorkDay::create([
            'name' => 'Passed current allowance year closure',
            'date' => '2026-05-14',
        ]);

        NonWorkDay::create([
            'name' => 'Today closure',
            'date' => '2026-05-15',
        ]);

        NonWorkDay::create([
            'name' => 'Future closure',
            'date' => '2026-05-16',
        ]);

        NonWorkDay::create([
            'name' => 'Next allowance year closure',
            'date' => '2027-04-01',
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('admin.view-leave-rules'));

        $response
            ->assertOk()
            ->assertSee('Passed current allowance year closure')
            ->assertSee('Today closure')
            ->assertSee('Future closure')
            ->assertSee('Next allowance year closure')
            ->assertDontSee('Previous allowance year closure');
    }
}
