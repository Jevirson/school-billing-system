<?php

namespace App\Notifications;

use App\Models\BillingSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillingScheduleNotification extends Notification
{
    use Queueable;

    public function __construct(public BillingSchedule $schedule) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $schedule = $this->schedule;

        return [
            'title' => 'Billing due date scheduled',
            'message' => $schedule->billing_period.' · '.$schedule->schoolTerm->display_name.' is due '.$schedule->due_date->format('F j, Y').'.',
            'url' => route('notifications.index'),
        ];
    }
}
