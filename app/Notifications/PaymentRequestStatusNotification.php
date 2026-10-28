<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public PaymentRequest $paymentRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment request '.$this->paymentRequest->status,
            'message' => 'Your request for ₱'.number_format((float) $this->paymentRequest->amount, 2).' on '.$this->paymentRequest->bill->invoice_number.' was '.$this->paymentRequest->status.'.',
            'payment_request_id' => $this->paymentRequest->id,
            'url' => route('student.payments.index'),
        ];
    }
}
