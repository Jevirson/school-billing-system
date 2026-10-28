<?php

namespace App\Notifications;

use App\Models\SubjectWithdrawalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubjectWithdrawalStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public SubjectWithdrawalRequest $withdrawalRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $subject = $this->withdrawalRequest->enrollment->subject;
        $status = strtolower($this->withdrawalRequest->status);

        return [
            'title' => 'Subject withdrawal '.$status,
            'message' => 'Your request to withdraw from '.$subject->code.' — '.$subject->name.' was '.$status.'.',
            'url' => route('student.enrollment.index'),
        ];
    }
}
