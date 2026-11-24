<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\User;
use App\Notifications\LeaveRequestResponded;
use App\Notifications\LeaveRequestRespondedEmail;
use App\Notifications\LeaveRequestSubmitted;
use App\Notifications\LeaveRequestSubmittedEmail;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_preliminary_lookup_precedes_the_locking_transaction_and_audit_is_inside_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'leave_allowance' => 20]);
        $leaveDate = Carbon::now()->addMonth()->toDateString();
        $leave = Leave::create([
            'user_id' => $employee->id, 'start_date' => $leaveDate, 'end_date' => $leaveDate,
            'reason' => 'Annual leave', 'is_half_day' => false,
        ]);
        $baseline = \Illuminate\Support\Facades\DB::transactionLevel();
        $reads = [];
        $readsAtAudit = [];
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$reads) {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, '"leaves"')) {
                $reads[] = $query->connection->transactionLevel();
            }
        });
        $this->mock(\App\Services\ActivityLogger::class, function ($mock) use ($baseline, &$reads, &$readsAtAudit) {
            $mock->shouldReceive('record')->once()->andReturnUsing(function () use ($baseline, &$reads, &$readsAtAudit) {
                $this->assertSame($baseline + 1, \Illuminate\Support\Facades\DB::transactionLevel());
                $readsAtAudit = $reads;
            });
        });

        $this->actingAs($admin)->post(route('admin.leave-requests.response', $leave), ['response' => 'approved'])
            ->assertRedirect(route('admin.leave-requests'))->assertSessionHasNoErrors();

        $this->assertGreaterThan(1, count($readsAtAudit));
        $this->assertSame($baseline, $readsAtAudit[0]);
        foreach (array_slice($readsAtAudit, 1) as $level) {
            $this->assertSame($baseline + 1, $level);
        }
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_approval_and_notifications_roll_back_when_its_audit_write_fails(): void
    {
        config(['queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'leave_allowance' => 20]);
        $leaveDate = Carbon::now()->addMonth()->toDateString();
        $leave = Leave::create([
            'user_id' => $employee->id, 'start_date' => $leaveDate, 'end_date' => $leaveDate,
            'reason' => 'Annual leave', 'is_half_day' => false,
        ]);
        $mailSent = false;
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class,
            function ($event) use (&$mailSent) {
                if ($event->channel === 'mail') {
                    $mailSent = true;
                }
            });
        $this->mock(\App\Services\ActivityLogger::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit unavailable'));
        });

        $this->actingAs($admin)->post(route('admin.leave-requests.response', $leave), ['response' => 'approved'])
            ->assertStatus(500);

        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('audit_leaves_logs', 0);
        $this->assertFalse($mailSent);
    }

    public function test_both_leave_emails_wait_for_the_outermost_transaction_even_when_queue_after_commit_is_false(): void
    {
        config(['queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
        $user = User::factory()->create();
        $leave = Leave::create([
            'user_id' => $user->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-02',
            'reason' => 'Annual leave', 'is_half_day' => false,
        ]);
        $sent = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class,
            function ($event) use (&$sent) {
                if ($event->channel === 'mail') {
                    $sent[] = $event->notification::class;
                }
            });

        \Illuminate\Support\Facades\DB::beginTransaction();
        \Illuminate\Support\Facades\DB::beginTransaction();
        $user->notify(new LeaveRequestSubmittedEmail($leave));
        $user->notify(new LeaveRequestRespondedEmail($leave));
        $this->assertSame([], $sent);

        \Illuminate\Support\Facades\DB::commit();
        $this->assertSame([], $sent);
        \Illuminate\Support\Facades\DB::commit();

        $this->assertSame([LeaveRequestSubmittedEmail::class, LeaveRequestRespondedEmail::class], $sent);
    }

    public function test_both_leave_emails_are_discarded_when_the_outer_transaction_rolls_back(): void
    {
        config(['queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
        $user = User::factory()->create();
        $leave = Leave::create([
            'user_id' => $user->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-02',
            'reason' => 'Annual leave', 'is_half_day' => false,
        ]);
        $sent = [];
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSending::class,
            function ($event) use (&$sent) {
                if ($event->channel === 'mail') {
                    $sent[] = $event->notification::class;
                }
            });

        \Illuminate\Support\Facades\DB::beginTransaction();
        \Illuminate\Support\Facades\DB::beginTransaction();
        $user->notify(new LeaveRequestSubmittedEmail($leave));
        $user->notify(new LeaveRequestRespondedEmail($leave));
        \Illuminate\Support\Facades\DB::commit();
        \Illuminate\Support\Facades\DB::rollBack();

        // A later commit must not release callbacks discarded by the rollback.
        \Illuminate\Support\Facades\DB::transaction(fn () => null);
        $this->assertSame([], $sent);
    }

    public function test_admins_are_notified_when_employee_submits_leave_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'leave_allowance' => 20]);
        $leaveDate = Carbon::now()->addMonth();

        $response = $this
            ->actingAs($employee)
            ->from(route('leave.form'))
            ->post(route('leave.create'), [
                'start_date' => $leaveDate->format('d-m-Y'),
                'end_date' => $leaveDate->format('d-m-Y'),
                'is_half_day' => '0',
                'reason' => 'Annual leave',
            ]);

        $response->assertRedirect(route('leave.view'));

        $notification = $admin->refresh()->unreadNotifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('New leave request', $notification->data['title']);
        $this->assertStringContainsString($employee->name, $notification->data['body']);
        $this->assertSame(route('admin.leave-requests'), $notification->data['action_url']);
    }

    public function test_employee_is_notified_when_admin_responds_to_leave_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'leave_allowance' => 20]);
        $leaveDate = Carbon::now()->addMonth()->toDateString();
        $leave = Leave::create([
            'user_id' => $employee->id,
            'start_date' => $leaveDate,
            'end_date' => $leaveDate,
            'reason' => 'Annual leave',
            'is_half_day' => false,
        ]);

        $this->mock(\App\Services\ActivityLogger::class, function ($mock) use ($admin, $employee, $leave, $leaveDate) {
            $date = Carbon::parse($leaveDate)->format('d/m/Y');
            $mock->shouldReceive('record')->once()->withArgs(
                fn ($actor, $subject, $action, $description) => $actor->is($admin)
                    && $subject->is($leave)
                    && $action === 'leave_request_response'
                    && $description === "approved the leave request for {$employee->name} from {$date} to {$date}"
            );
        });

        $response = $this
            ->actingAs($admin)
            ->post(route('admin.leave-requests.response', $leave), [
                'response' => 'approved',
                'manager_comment' => 'Enjoy your day off.',
            ]);

        $response->assertRedirect(route('admin.leave-requests'));

        $notification = $employee->refresh()->unreadNotifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('Leave request Approved', $notification->data['title']);
        $this->assertStringContainsString('approved', $notification->data['body']);
        $this->assertSame(route('leave.view'), $notification->data['action_url']);
    }

    public function test_opening_notification_marks_it_as_read(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'leave_allowance' => 20]);
        $leaveDate = Carbon::now()->addMonth();

        $this
            ->actingAs($employee)
            ->from(route('leave.form'))
            ->post(route('leave.create'), [
                'start_date' => $leaveDate->format('d-m-Y'),
                'end_date' => $leaveDate->format('d-m-Y'),
                'is_half_day' => '0',
                'reason' => 'Annual leave',
            ]);

        $notification = $admin->refresh()->unreadNotifications()->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->patch(route('notifications.open', $notification));

        $response->assertRedirect(route('admin.leave-requests'));
        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_leave_submission_notification_is_written_to_database_and_email_is_queued(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'name' => 'Alex Example']);
        $leave = Leave::create([
            'user_id' => $employee->id,
            'start_date' => Carbon::now()->addMonth()->toDateString(),
            'end_date' => Carbon::now()->addMonth()->toDateString(),
            'reason' => 'Annual leave',
            'is_half_day' => false,
        ]);
        $databaseNotification = new LeaveRequestSubmitted($leave);
        $mailNotification = new LeaveRequestSubmittedEmail($leave);

        $this->assertSame(['database'], $databaseNotification->via($admin));
        $this->assertSame(['mail'], $mailNotification->via($admin));
        $this->assertInstanceOf(ShouldQueue::class, $mailNotification);

        $mail = $mailNotification->toMail($admin);

        $this->assertSame('New leave request', $mail->subject);
        $this->assertStringContainsString('Alex Example requested leave', implode(' ', $mail->introLines));
    }

    public function test_leave_response_notification_is_written_to_database_and_email_is_queued(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $leaveDate = Carbon::now()->addMonth()->toDateString();
        $leave = Leave::create([
            'user_id' => $employee->id,
            'start_date' => $leaveDate,
            'end_date' => $leaveDate,
            'reason' => 'Annual leave',
            'is_half_day' => false,
        ]);
        $leave->forceFill(['status' => 'approved']);
        $databaseNotification = new LeaveRequestResponded($leave);
        $mailNotification = new LeaveRequestRespondedEmail($leave);

        $this->assertSame(['database'], $databaseNotification->via($employee));
        $this->assertSame(['mail'], $mailNotification->via($employee));
        $this->assertInstanceOf(ShouldQueue::class, $mailNotification);

        $mail = $mailNotification->toMail($employee);

        $this->assertSame('Leave request Approved', $mail->subject);
        $this->assertStringContainsString('was approved', implode(' ', $mail->introLines));
    }
}
