<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-4"><h2 class="text-2xl font-semibold text-slate-900">{{ $bill->invoice_number }}</h2><div class="flex gap-2"><a href="{{ route('admin.bills.statement', $bill) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Statement of account</a><a href="{{ route('admin.bills.invoice', $bill) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print invoice</a></div></div></x-slot>
    <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6"><div class="mx-auto max-w-5xl space-y-6">
        @if (session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="grid gap-5 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm text-slate-500">Student</p><h3 class="mt-1 text-xl font-semibold text-slate-900">{{ $bill->studentProfile->invoice_name }}</h3><p class="mt-2 text-sm text-slate-600">{{ $bill->studentProfile->student_number }} · {{ $bill->studentProfile->course?->name ?? 'Course not assigned' }} · {{ $bill->studentProfile->year_level ?? 'Year not assigned' }}</p><p class="mt-2 text-sm font-medium text-slate-700">{{ $bill->schoolTerm->display_name }} · {{ $bill->billing_period }}</p><p class="mt-1 text-sm text-slate-500">Due date: {{ $bill->due_date?->format('F j, Y') ?? 'Not scheduled' }}</p></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div class="grid grid-cols-2 gap-4 text-sm"><span class="text-slate-500">Total fees</span><strong>₱{{ number_format((float) $bill->total_fees, 2) }}</strong><span class="text-slate-500">Scholarship</span><strong>{{ $bill->scholarship_name ?? 'None' }}</strong><span class="text-slate-500">Discount</span><strong>− ₱{{ number_format((float) $bill->scholarship_discount, 2) }}</strong><span class="text-slate-500">Carried from earlier period</span><strong>₱{{ number_format((float) $bill->incomingExtensionBalance, 2) }}</strong><span class="text-slate-500">Deferred to next period</span><strong>− ₱{{ number_format((float) $bill->extensions->sum('amount_at_approval'), 2) }}</strong><span class="border-t border-slate-100 pt-3 text-slate-700">Total due for this period</span><strong class="border-t border-slate-100 pt-3">₱{{ number_format((float) $bill->total_due, 2) }}</strong><span class="text-slate-500">Latest payment</span><strong>
                @if ($bill->latestPayment)
                    ₱{{ number_format((float) $bill->latestPayment->amount, 2) }} <span class="block font-normal text-slate-500">{{ $bill->latestPayment->paid_at->format('M j, Y') }}</span>
                @else
                    ₱0.00
                @endif
            </strong><span class="text-slate-500">Total paid</span><strong>₱{{ number_format((float) $bill->total_paid, 2) }}</strong><span class="text-slate-700">Remaining balance</span><strong class="text-indigo-700">₱{{ number_format((float) $bill->remaining_balance, 2) }}</strong><span class="text-slate-500">Status</span><strong>{{ $bill->payment_status }}</strong></div></div>
        </section>
        @if ($bill->extensionsReceived->isNotEmpty())
            <section class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5">
                <h3 class="font-semibold text-amber-950">Carried-forward amounts included in this period</h3>
                <div class="mt-3 divide-y divide-amber-200/70">
                    @foreach ($bill->extensionsReceived as $extension)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                            <div><p class="font-semibold text-slate-900">₱{{ number_format((float) $extension->amount_at_approval, 2) }} from {{ $extension->sourceBill->billing_period }}</p><p class="mt-1 text-xs text-slate-600">Original invoice <a class="font-semibold text-indigo-700 hover:underline" href="{{ route('admin.bills.show', $extension->sourceBill) }}">{{ $extension->sourceBill->invoice_number }}</a> · {{ $extension->promissoryLetter->student_name }}</p></div>
                            <p class="font-bold text-amber-950">₱{{ number_format((float) $extension->remaining_balance, 2) }} unpaid</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @if ($bill->extensions->isNotEmpty())
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="font-semibold text-slate-900">Amounts deferred from this period</h3>
                @foreach ($bill->extensions as $extension)
                    <p class="mt-2 text-sm text-slate-600">₱{{ number_format((float) $extension->amount_at_approval, 2) }} moved to {{ $extension->next_billing_period ?? 'the next period' }} · currently unpaid ₱{{ number_format((float) $extension->remaining_balance, 2) }}.</p>
                @endforeach
            </section>
        @endif
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="font-semibold text-slate-900">Bill items</h3><div class="mt-4 divide-y divide-slate-100">@foreach($bill->items as $item)<div class="flex justify-between py-3 text-sm"><span>{{ $item->description }}</span><span>₱{{ number_format((float) $item->line_total, 2) }}</span></div>@endforeach</div></section>
        @if ($bill->payments_count === 0 && $bill->promissory_letters_count === 0 && $bill->extensions_count === 0 && $bill->extensions_received_count === 0)
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div><h3 class="font-semibold text-amber-950">Bill has no payment or promissory history</h3><p class="mt-1 text-sm text-amber-900">You can edit or delete this bill until financial history is recorded.</p></div>
                <div class="flex gap-3">
                    <a href="{{ route('admin.bills.edit', $bill) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit bill</a>
                    <form method="POST" action="{{ route('admin.bills.destroy', $bill) }}" onsubmit="return confirm('Delete this bill? This action cannot be undone.')">@csrf @method('DELETE')
                        <button class="rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">Delete bill</button>
                    </form>
                </div>
            </section>
        @endif
        <section id="payments" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="font-semibold text-slate-900">Payments recorded on this bill</h3><div class="mt-4 divide-y divide-slate-100">@forelse($bill->payments as $payment)<div class="flex flex-wrap justify-between gap-2 py-3 text-sm"><span>₱{{ number_format((float) $payment->amount, 2) }} · {{ $payment->method }} · {{ $payment->paid_at->format('M j, Y') }}<span class="block text-xs text-slate-500">{{ $payment->reference ?? 'No reference' }}</span></span><span class="flex gap-3"><a href="{{ route('admin.payments.receipt', $payment) }}" class="font-semibold text-indigo-600 hover:underline">Print receipt</a><a href="{{ route('admin.payments.edit', $payment) }}" class="font-semibold text-slate-700 hover:underline">Edit payment</a></span></div>@empty<p class="py-3 text-sm text-slate-500">No payments recorded.</p>@endforelse</div></section>
        @if ((float) $bill->remaining_balance > 0)
            <section id="record-payment" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 class="font-semibold text-slate-900">Record office payment</h3><p class="mt-1 text-sm text-slate-500">Record funds actually received at the school office. A receipt is available after saving.</p><form method="POST" action="{{ route('admin.bills.payments.store', $bill) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@csrf
                <label class="text-sm font-medium text-slate-700">Amount (₱)<input name="amount" type="number" min="0.01" max="{{ $bill->remaining_balance }}" step="0.01" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Payment date<input name="paid_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ now()->format('Y-m-d\TH:i') }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700">Method<select name="method" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"><option>Office Payment</option><option>Cash</option><option>Bank transfer</option><option>GCash</option><option>Check</option><option>Other</option></select></label>
                <label class="text-sm font-medium text-slate-700">Reference (optional)<input name="reference" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <label class="text-sm font-medium text-slate-700 lg:col-span-2">Notes (optional)<input name="notes" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
                <div class="sm:col-span-2 lg:col-span-3 flex justify-end"><button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Record Office Payment</button></div>
            </form></section>
        @endif
    </div></div>
</x-app-layout>
