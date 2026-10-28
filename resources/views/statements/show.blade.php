<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Billing record</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-900">Statement of Account</h2>
            </div>
            <div class="flex gap-2">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.bills.show', $bill) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to bill</a>
                @else
                    <a href="{{ route('student.bills.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">My bills</a>
                @endif
                <button onclick="window.print()" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Print statement</button>
            </div>
        </div>
    </x-slot>
    <style>
        @media print {
            nav, header, footer { display: none !important; }
            body { background: white !important; }
            .statement-card { border: 0 !important; box-shadow: none !important; }
        }
    </style>
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 print:min-h-0 print:bg-white print:p-0">
        <article class="statement-card mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <div class="flex flex-wrap justify-between gap-5 border-b border-slate-200 pb-6">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.15em] text-indigo-600">School Billing System</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-900">Statement of Account</h1>
                    <p class="mt-2 text-sm text-slate-500">Account balance and payment activity for this invoice.</p>
                </div>
                <div class="text-right text-sm">
                    <p class="font-semibold text-slate-900">{{ $bill->invoice_number }}</p>
                    <p class="mt-1 text-slate-600">Billing date: {{ $bill->billing_date->format('M j, Y') }}</p>
                    <p class="mt-1 text-slate-600">Printed: {{ now()->format('M j, Y g:i A') }}</p>
                </div>
            </div>

            <section class="grid gap-6 border-b border-slate-200 py-6 sm:grid-cols-2">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</h2>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $bill->studentProfile->invoice_name }}</p>
                    <p class="mt-1 text-sm text-slate-700">Student ID: {{ $bill->studentProfile->student_number }}</p>
                    <p class="mt-1 text-sm text-slate-700">Course: {{ $bill->studentProfile->course?->name ?? 'Not assigned' }}</p>
                    <p class="mt-1 text-sm text-slate-700">Year level: {{ $bill->studentProfile->year_level ?? 'Not assigned' }}</p>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Academic term</h2>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $bill->schoolTerm->display_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $bill->billing_period }} · Due {{ $bill->due_date?->format('F j, Y') ?? 'not scheduled' }}</p>
                    <p class="mt-3 text-sm text-slate-700">Payment status: <strong>{{ $bill->payment_status }}</strong></p>
                </div>
            </section>

            <section class="border-b border-slate-200 py-6">
                <h2 class="font-semibold text-slate-900">Charges</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="py-3">Description</th><th class="py-3 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($bill->items as $item)
                                <tr><td class="py-3">{{ $item->description }}</td><td class="py-3 text-right">₱{{ number_format((float) $item->line_total, 2) }}</td></tr>
                            @endforeach
                            @foreach ($bill->extensionsReceived as $extension)
                                <tr><td class="py-3">Carry-forward from {{ $extension->sourceBill->billing_period }} · invoice {{ $extension->sourceBill->invoice_number }}</td><td class="py-3 text-right">₱{{ number_format((float) $extension->amount_at_approval, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="ml-auto mt-5 max-w-sm space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Total fees</dt><dd>₱{{ number_format((float) $bill->total_fees, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Scholarship · {{ $bill->scholarship_name ?? 'None' }}</dt><dd>− ₱{{ number_format((float) $bill->scholarship_discount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Deferred to next period</dt><dd>− ₱{{ number_format((float) $bill->extensions->sum('amount_at_approval'), 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold"><dt>Total due this period</dt><dd>₱{{ number_format((float) $bill->total_due, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Total amount paid</dt><dd>₱{{ number_format((float) $bill->total_paid, 2) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold"><dt>Remaining balance</dt><dd>₱{{ number_format((float) $bill->remaining_balance, 2) }}</dd></div>
                </dl>
            </section>

            <section class="py-6">
                <div class="flex items-end justify-between gap-4">
                    <div><h2 class="font-semibold text-slate-900">Payment history</h2><p class="mt-1 text-sm text-slate-500">Latest payment: @if ($bill->latestPayment) ₱{{ number_format((float) $bill->latestPayment->amount, 2) }} on {{ $bill->latestPayment->paid_at->format('M j, Y') }} @else ₱0.00 · no payment recorded @endif</p></div>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <tr><th class="py-3">Date</th><th class="py-3">Method / reference</th><th class="py-3">Recorded by</th><th class="py-3 text-right">Amount</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($bill->payments as $payment)
                                <tr><td class="py-3">{{ $payment->paid_at->format('M j, Y g:i A') }}</td><td class="py-3">{{ $payment->method }}{{ $payment->reference ? ' · '.$payment->reference : '' }}</td><td class="py-3">{{ $payment->recordedBy->name }}</td><td class="py-3 text-right font-medium">₱{{ number_format((float) $payment->amount, 2) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="py-5 text-center text-slate-500">No payments recorded for this invoice.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($bill->extensions->isNotEmpty())
                <section class="border-t border-amber-200 bg-amber-50/60 px-4 py-4 text-sm text-amber-950">
                    <h2 class="font-semibold">Approved carry-forward</h2>
                    @foreach ($bill->extensions as $extension)
                        <p class="mt-1">{{ $extension->next_billing_period ?? 'Next period' }} · {{ $extension->nextSchoolTerm->display_name }} · current balance ₱{{ number_format((float) $extension->remaining_balance, 2) }}. This amount is due in its next period and removed from this period's balance.</p>
                    @endforeach
                </section>
            @endif
        </article>
    </main>
</x-app-layout>
