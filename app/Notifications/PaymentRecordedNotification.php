<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $bill = $this->payment->bill;

        return [
            'title' => 'Payment recorded',
            'message' => 'A payment of ₱'.number_format((float) $this->payment->amount, 2).' was recorded for '.$bill->invoice_number.'.',
            'payment_id' => $this->payment->id,
            'url' => route('student.payments.index'),
        ];
    }
}
