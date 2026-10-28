<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">PAYMENT EXTENSIONS</p><h1 class="mt-1 text-2xl font-bold text-slate-900">My promissory letters</h1></div></x-slot>
    <div class="bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-6xl space-y-7">
        @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('status') }}</div>@endif
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="font-bold text-slate-900">Request more time to pay</h2><p class="mt-1 text-sm leading-6 text-slate-600">Choose an unpaid invoice to preview and submit a promissory letter for administrator review.</p></div><span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700">Requests are pending until approved</span></div>
            <div class="mt-5 grid gap-3 md:grid-cols-2">
                @php($requestableBills = $bills->filter(fn ($bill) => (float) $bill->remaining_balance > 0 && !$bill->extensions->isNotEmpty() && !$bill->promissoryLetters->contains(fn ($letter) => in_array($letter->status, ['Pending', 'Approved', 'Overdue'], true))))
                @forelse ($requestableBills as $bill)
                    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
                        <div><p class="font-semibold text-slate-900">{{ $bill->invoice_number }}</p><p class="mt-1 text-xs text-slate-500">{{ $bill->schoolTerm->display_name }} · Balance ₱{{ number_format((float) $bill->remaining_balance, 2) }}</p></div>
                        <a href="{{ route('student.promissory-letters.create', $bill) }}" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Request letter</a>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">There are no invoices currently eligible for a new request.</p>
                @endforelse
            </div>
        </section>

        @if ($carryForwards->isNotEmpty())
            <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-100 bg-amber-50/70 px-5 py-4 sm:px-6"><h2 class="font-bold text-amber-950">Balances carried to the next payment period</h2><p class="mt-1 text-sm text-amber-900">Each amount is included in its destination period and linked to the original invoice, so it is counted only once.</p></div>
                <div class="divide-y divide-slate-100">
                    @foreach ($carryForwards as $extension)
                        <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6">
                            <div><p class="font-semibold text-slate-900">{{ $extension->next_billing_period ?? 'Payment period' }} · {{ $extension->nextSchoolTerm->display_name }}</p><p class="mt-1 text-sm text-slate-600">Original invoice <a href="{{ route('student.bills.invoice', $extension->sourceBill) }}" class="font-semibold text-indigo-600 hover:underline">{{ $extension->sourceBill->invoice_number }}</a> · {{ $extension->sourceBill->billing_period }}</p><p class="mt-1 text-xs text-slate-500">Approved amount ₱{{ number_format((float) $extension->amount_at_approval, 2) }} · promised by {{ $extension->approved_until->format('M j, Y') }}@if($extension->destinationBill) · included on invoice {{ $extension->destinationBill->invoice_number }}@endif</p></div>
                            <div class="text-right"><p class="text-xs text-slate-500">Current original balance</p><p class="mt-1 text-xl font-bold text-slate-900">₱{{ number_format((float) $extension->remaining_balance, 2) }}</p></div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="space-y-4">
            <div><h2 class="text-lg font-bold text-slate-900">Request history</h2><p class="mt-1 text-sm text-slate-500">Track each request and read administrator decisions.</p></div>
            @forelse ($letters as $letter)
                <a href="{{ route('student.promissory-letters.show', $letter) }}" class="group flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200 hover:shadow-md">
                    <div><p class="font-semibold text-slate-900">{{ $letter->invoice_number }} <span class="font-normal text-slate-500">· {{ $letter->school_year }} · {{ $letter->semester }}</span></p><p class="mt-1 text-sm text-slate-500">Submitted {{ $letter->submitted_at->format('M j, Y') }} · Payment commitment {{ $letter->commitment_date->format('M j, Y') }}</p></div>
                    <span @class([
                        'rounded-full px-3 py-1.5 text-xs font-bold',
                        'bg-amber-50 text-amber-800' => $letter->status === 'Pending',
                        'bg-emerald-50 text-emerald-800' => in_array($letter->status, ['Approved', 'Fulfilled'], true),
                        'bg-rose-50 text-rose-800' => in_array($letter->status, ['Rejected', 'Overdue'], true),
                    ])>{{ $letter->status }}</span>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center"><p class="font-semibold text-slate-800">No promissory letters submitted</p><p class="mt-1 text-sm text-slate-500">Eligible unpaid invoices can be used to start a request above.</p></div>
            @endforelse
        </section>
    </div></div>
</x-app-layout>
