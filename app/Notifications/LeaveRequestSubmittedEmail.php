<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * Queued mail-only variant so SMTP failures do not block leave request creation.
 */
class LeaveRequestSubmittedEmail extends LeaveRequestSubmitted implements ShouldQueueAfterCommit
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
