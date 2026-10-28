<?php

namespace App\Notifications;

use App\Models\PromissoryLetter;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PromissoryLetterStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public PromissoryLetter $letter) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $extension = $this->letter->extension;
        $message = match ($this->letter->status) {
            'Approved' => $extension
                ? 'Your request for invoice '.$this->letter->invoice_number.' was approved. ₱'.number_format((float) $extension->amount_at_approval, 2).' was moved to '.$extension->next_billing_period.'.'
                : 'Your request for invoice '.$this->letter->invoice_number.' was approved.',
            'Rejected' => 'Your request for invoice '.$this->letter->invoice_number.' was rejected. Open the request to read the administrator’s remarks.',
            default => 'Your request for invoice '.$this->letter->invoice_number.' is '.$this->letter->status.'.',
        };

        return [
            'title' => 'Promissory letter '.$this->letter->status,
            'message' => $message,
            'status' => $this->letter->status,
            'promissory_letter_id' => $this->letter->id,
            'url' => route('student.promissory-letters.show', $this->letter),
        ];
    }
}
