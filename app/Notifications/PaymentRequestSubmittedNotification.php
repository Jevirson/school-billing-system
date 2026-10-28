<?php

namespace App\Notifications;

use App\Models\PaymentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRequestSubmittedNotification extends Notification
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
            'title' => 'Student payment request',
            'message' => $this->paymentRequest->studentProfile->invoice_name.' requested ₱'.number_format((float) $this->paymentRequest->amount, 2).' for '.$this->paymentRequest->bill->invoice_number.'.',
            'payment_request_id' => $this->paymentRequest->id,
            'url' => route('admin.payment-requests.index', ['status' => 'Pending']),
        ];
    }
}
