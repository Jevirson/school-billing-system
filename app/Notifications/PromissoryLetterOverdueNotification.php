<?php

namespace App\Notifications;

use App\Models\PromissoryLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PromissoryLetterOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public PromissoryLetter $letter) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Promissory commitment overdue',
            'message' => $this->letter->student_name.' has not paid invoice '.$this->letter->invoice_number.' by the promised date.',
            'status' => 'Overdue',
            'promissory_letter_id' => $this->letter->id,
            'url' => route('admin.promissory-letters.show', $this->letter),
        ];
    }
}
