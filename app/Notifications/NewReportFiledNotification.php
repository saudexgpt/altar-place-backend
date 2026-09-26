<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewReportFiledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Report $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_report',
            'report_id' => $this->report->id,
            'reason' => $this->report->reason,
            'message' => "New report filed: {$this->report->reason}.",
        ];
    }
}
