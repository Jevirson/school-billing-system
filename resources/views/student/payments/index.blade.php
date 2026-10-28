<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">STUDENT PORTAL</p><h2 class="mt-1 text-2xl font-bold text-slate-900">Payment history</h2></div>
            <a href="{{ route('student.bills.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">My bills</a>
        </div>
    </x-slot>
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-6">
            @if ($student)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $student->invoice_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $student->student_number }} · {{ $student->course?->name ?? 'Course not assigned' }}</p>
                </section>
            @endif
            <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold text-slate-900">Payment request history</h3><p class="mt-1 text-sm text-slate-500">Requests affect your balance only after the administrator confirms receipt.</p></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($student?->paymentRequests()->with('bill.schoolTerm')->latest()->get() ?? collect() as $paymentRequest)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div><p class="font-semibold text-slate-900">{{ $paymentRequest->bill->invoice_number }} · ₱{{ number_format((float) $paymentRequest->amount, 2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $paymentRequest->bill->schoolTerm->display_name }} · {{ $paymentRequest->bill->billing_period }} · Submitted {{ $paymentRequest->created_at->format('M j, Y') }}</p>@if($paymentRequest->admin_note)<p class="mt-1 text-sm text-slate-600">{{ $paymentRequest->admin_note }}</p>@endif</div>
                            <span @class(['rounded-full px-3 py-1 text-xs font-semibold','bg-amber-50 text-amber-800' => $paymentRequest->status === 'Pending','bg-emerald-50 text-emerald-800' => $paymentRequest->status === 'Approved','bg-rose-50 text-rose-800' => $paymentRequest->status === 'Rejected'])>{{ $paymentRequest->status }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-6 text-sm text-slate-500">No payment requests have been submitted.</p>
                    @endforelse
                </div>
            </section>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h3 class="font-semibold text-slate-900">Your bills and payments</h3><p class="mt-1 text-sm text-slate-500">Your unpaid, partially paid, and fully paid bills, with recorded payments.</p></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Invoice / term</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Total paid</th><th class="px-5 py-3 text-right">Balance</th><th class="px-5 py-3">Recorded payment details</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($bills as $bill)
                                <tr>
                                    <td class="px-5 py-4"><span class="block font-medium text-slate-900">{{ $bill->invoice_number }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $bill->schoolTerm->display_name }} · {{ $bill->billing_period }} · Due {{ $bill->due_date?->format('M j, Y') ?? 'not set' }}</span></td>
                                    <td class="px-5 py-4">
                                        <span @class([
                                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                                            'bg-rose-50 text-rose-700' => $bill->payment_status === 'Unpaid',
                                            'bg-amber-50 text-amber-700' => $bill->payment_status === 'Partially Paid',
                                            'bg-emerald-50 text-emerald-700' => $bill->payment_status === 'Fully Paid',
                                        ])>{{ $bill->payment_status }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold">₱{{ number_format((float) $bill->total_paid, 2) }}</td>
                                    <td class="px-5 py-4 text-right font-semibold">₱{{ number_format((float) $bill->remaining_balance, 2) }}</td>
                                    <td class="px-5 py-4">
                                        @forelse ($bill->payments as $payment)
                                            <p class="whitespace-nowrap">{{ $payment->paid_at->format('M j, Y') }} · ₱{{ number_format((float) $payment->amount, 2) }} · {{ $payment->method }} · <a href="{{ route('student.payments.receipt', $payment) }}" class="font-semibold text-indigo-600 hover:underline">Receipt</a></p>
                                            @if ($payment->reference)<p class="text-xs text-slate-500">Ref: {{ $payment->reference }}</p>@endif
                                        @empty
                                            <span class="text-slate-500">No payments recorded</span>
                                        @endforelse
                                    </td>
                                    <td class="px-5 py-4"><a class="whitespace-nowrap font-semibold text-indigo-600 hover:underline" href="{{ route('student.bills.statement', $bill) }}">Statement</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No bills are available for your account.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $bills->links() }}</div>
            </section>
        </div>
    </main>
</x-app-layout>
