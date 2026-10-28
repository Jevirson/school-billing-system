<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment receipt {{ $payment->reference ?: '#'.$payment->id }}</title>
    <style>
        body { color: #172033; font: 15px/1.5 Arial, sans-serif; margin: 2rem auto; max-width: 720px; padding: 0 1rem; }
        .receipt { border: 1px solid #cbd5e1; border-radius: 12px; padding: 2rem; }
        .row { border-bottom: 1px solid #e2e8f0; display: flex; gap: 1rem; justify-content: space-between; padding: .8rem 0; }
        .label { color: #64748b; }
        .amount { font-size: 1.5rem; font-weight: 700; }
        @media print { body { margin: 0; } .receipt { border: 0; } .no-print { display: none; } }
    </style>
</head>
<body>
    <article class="receipt">
        <p>{{ config('app.name', 'Billing System') }}</p>
        <h1>OFFICIAL PAYMENT RECEIPT</h1>
        <p>Receipt {{ $payment->reference ?: '#'.$payment->id }}</p>
        <div class="row"><span class="label">Student</span><strong>{{ $payment->bill->studentProfile->invoice_name }}</strong></div>
        <div class="row"><span class="label">Student ID</span><strong>{{ $payment->bill->studentProfile->student_number }}</strong></div>
        <div class="row"><span class="label">Billing period / term</span><strong>{{ $payment->bill->billing_period ?? 'Full Term' }} · {{ $payment->bill->schoolTerm->display_name }}</strong></div>
        <div class="row"><span class="label">Payment date</span><strong>{{ $payment->paid_at->format('F j, Y g:i A') }}</strong></div>
        <div class="row"><span class="label">Payment method</span><strong>{{ $payment->method }}</strong></div>
        <div class="row"><span class="label">Amount received</span><strong class="amount">₱{{ number_format((float) $payment->amount, 2) }}</strong></div>
        <div class="row"><span class="label">Remaining balance after payment</span><strong>₱{{ number_format((float) ($payment->balance_after ?? $payment->bill->remaining_balance), 2) }}</strong></div>
        <div class="row"><span class="label">Recorded by</span><strong>{{ $payment->recordedBy->name }}</strong></div>
        @if ($payment->notes)<p>{{ $payment->notes }}</p>@endif
    </article>
    <p class="no-print"><button type="button" onclick="window.print()">Print receipt</button> <a href="{{ url()->previous() }}">Back</a></p>
</body>
</html>
