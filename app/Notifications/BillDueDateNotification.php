<?php

namespace App\Notifications;

use App\Models\Bill;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BillDueDateNotification extends Notification
{
    use Queueable;

    public function __construct(public Bill $bill) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $bill = $this->bill;

        return [
            'title' => 'Billing due date scheduled',
            'message' => $bill->billing_period.' · '.$bill->schoolTerm->display_name.' is due '.$bill->due_date->format('F j, Y').'. Total: ₱'.number_format((float) $bill->total_fees, 2).'; scholarship: ₱'.number_format((float) $bill->scholarship_discount, 2).'; paid: ₱'.number_format((float) $bill->total_paid, 2).'; balance: ₱'.number_format((float) $bill->remaining_balance, 2).'.',
            'bill_id' => $bill->id,
            'url' => route('student.bills.statement', $bill),
        ];
    }
}
