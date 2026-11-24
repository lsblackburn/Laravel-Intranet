<?php

namespace Tests\Feature;

use App\Models\LeaveSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveAllowanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \iamfarhad\LaravelAuditLog\Drivers\MySQLDriver::clearCache();
    }

    public function test_allowance_audit_lists_only_changed_settings_and_skips_unchanged_saves(): void
    {
        $settings = LeaveSetting::firstOrFail();
        $settings->update([
            'base_allowance' => 20, 'increase_after_years' => 2,
            'increase_by_days' => 1, 'maximum_allowance' => 30,
        ]);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $this->mock(\App\Services\ActivityLogger::class, function ($mock) use ($admin, $settings) {
            $mock->shouldReceive('record')->once()->withArgs(
                fn ($actor, $subject, $action, $description) => $actor->is($admin)
                    && $subject->is($settings)
                    && $action === 'update_leave_allowance'
                    && $description === 'updated leave allowance settings: base allowance from 20 to 22.5 days; service threshold for allowance increases from 2 to 3 years'
            );
        });

        $payload = [
            'base_allowance' => 22.5, 'increase_after_years' => 3,
            'increase_by_days' => 1, 'maximum_allowance' => 30,
        ];

        $this->patch(route('admin.leave-allowance.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->patch(route('admin.leave-allowance.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_admin_can_save_numeric_allowance_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.view-leave-rules'))
            ->patch(route('admin.leave-allowance.update'), [
                'base_allowance' => 20,
                'increase_after_years' => 2,
                'increase_by_days' => 1,
                'maximum_allowance' => 30,
            ]);

        $response
            ->assertRedirect(route('admin.view-leave-rules'))
            ->assertSessionHasNoErrors();

        $settings = LeaveSetting::first();

        $this->assertSame(20.0, (float) $settings->base_allowance);
        $this->assertSame(2, (int) $settings->increase_after_years);
        $this->assertSame(1.0, (float) $settings->increase_by_days);
        $this->assertSame(30.0, (float) $settings->maximum_allowance);
    }

    public function test_admin_cannot_set_maximum_allowance_below_base_allowance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.view-leave-rules'))
            ->patch(route('admin.leave-allowance.update'), [
                'base_allowance' => 20,
                'increase_after_years' => 2,
                'increase_by_days' => 1,
                'maximum_allowance' => 19,
            ]);

        $response
            ->assertRedirect(route('admin.view-leave-rules'))
            ->assertSessionHasErrors('maximum_allowance');

        $this->assertSame(30.0, (float) LeaveSetting::first()->maximum_allowance);
    }
}
