<?php

namespace Tests\Feature;

use App\Models\LeaveSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRefreshSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
    }

    public function test_refresh_audit_includes_original_and_new_dates_and_skips_unchanged_saves(): void
    {
        $settings = LeaveSetting::firstOrFail();
        $settings->update(['leave_refresh_day' => 1, 'leave_refresh_month' => 1]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->mock(\App\Services\ActivityLogger::class, function ($mock) use ($admin, $settings) {
            $mock->shouldReceive('record')->once()->withArgs(
                fn ($actor, $subject, $action, $description) => $actor->is($admin)
                    && $subject->is($settings)
                    && $action === 'update_leave_refresh'
                    && $description === 'changed the annual leave refresh date from 1 January to 29 February'
            );
        });

        $payload = ['leave_refresh_day' => 29, 'leave_refresh_month' => 2];
        $this->patch(route('admin.leave-refresh.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('admin.leave-refresh.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_admin_can_set_february_twenty_ninth_as_leave_refresh_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.view-leave-rules'))
            ->patch(route('admin.leave-refresh.update'), [
                'leave_refresh_day' => 29,
                'leave_refresh_month' => 2,
            ]);

        $response
            ->assertRedirect(route('admin.view-leave-rules'))
            ->assertSessionHasNoErrors();

        $settings = LeaveSetting::first();

        $this->assertSame(29, (int) $settings->leave_refresh_day);
        $this->assertSame(2, (int) $settings->leave_refresh_month);
    }

    public function test_admin_cannot_set_day_beyond_selected_month_length(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.view-leave-rules'))
            ->patch(route('admin.leave-refresh.update'), [
                'leave_refresh_day' => 30,
                'leave_refresh_month' => 2,
            ]);

        $response
            ->assertRedirect(route('admin.view-leave-rules'))
            ->assertSessionHasErrors('leave_refresh_day');

        $settings = LeaveSetting::first();

        $this->assertSame(1, (int) $settings->leave_refresh_day);
        $this->assertSame(1, (int) $settings->leave_refresh_month);
    }
}
