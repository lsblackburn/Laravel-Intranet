<?php

namespace Tests\Unit;

use App\Models\Leave;
use App\Models\LeaveSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLeaveAllowanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_remaining_leave_allowance_only_subtracts_approved_leave_in_current_allowance_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-06'));
        LeaveSetting::first()->update([
            'leave_refresh_day' => 1,
            'leave_refresh_month' => 4,
        ]);

        $user = User::factory()->create(['leave_allowance' => 20]);

        $this->createLeave($user, '2025-06-10', '2025-06-12', 'approved');
        $this->createLeave($user, '2026-04-10', '2026-04-12', 'approved');
        $this->createLeave($user, '2026-05-01', '2026-05-02', 'pending');

        $this->assertSame(3.0, $user->approvedLeaveDaysUsed());
        $this->assertSame(17.0, $user->remainingLeaveAllowance());
    }

    public function test_leave_crossing_refresh_date_only_counts_days_in_current_allowance_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-06'));
        LeaveSetting::first()->update([
            'leave_refresh_day' => 1,
            'leave_refresh_month' => 4,
        ]);

        $user = User::factory()->create(['leave_allowance' => 20]);

        $this->createLeave($user, '2026-03-30', '2026-04-02', 'approved');

        $this->assertSame(2.0, $user->approvedLeaveDaysUsed());
        $this->assertSame(18.0, $user->remainingLeaveAllowance());
    }

    public function test_calculating_leave_allowance_preserves_existing_allowance_without_employment_start_date(): void
    {
        LeaveSetting::first()->update([
            'base_allowance' => 20,
            'increase_after_years' => 2,
            'increase_by_days' => 1,
            'maximum_allowance' => 30,
        ]);

        $user = User::factory()->create([
            'employment_start_date' => null,
            'leave_allowance' => 25,
        ]);

        $this->assertSame(25.0, $user->calculateLeaveAllowance());
    }

    private function createLeave(User $user, string $startDate, string $endDate, string $status): Leave
    {
        $leave = Leave::create([
            'user_id' => $user->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => 'Annual leave',
        ]);

        $leave->forceFill(['status' => $status])->save();

        return $leave;
    }
}
