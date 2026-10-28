<?php

namespace App\Notifications;

use App\Models\ExamSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExamScheduleNotification extends Notification
{
    use Queueable;

    public function __construct(public ExamSchedule $schedule) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $schedule = $this->schedule;

        return [
            'title' => 'Exam date scheduled',
            'message' => $schedule->exam_period.' exam · '.$schedule->schoolTerm->display_name.' is scheduled for '.$schedule->exam_date->format('F j, Y').'.',
            'url' => route('notifications.index'),
        ];
    }
}
