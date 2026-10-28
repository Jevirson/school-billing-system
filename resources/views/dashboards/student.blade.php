<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">STUDENT PORTAL</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Your dashboard</h1>
            </div>
            <p class="text-sm text-slate-500">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </x-slot>

    <div class="bg-slate-50 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="mx-auto max-w-6xl space-y-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @if ($billingNotifications->isNotEmpty())
                <section class="space-y-3" aria-label="Notifications">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-900">Notifications</h2>
                        <a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">View all</a>
                    </div>
                    @foreach ($billingNotifications as $notification)
                        <a href="{{ $notification->data['url'] ?? route('student.promissory-letters.index') }}" class="flex items-start gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950 shadow-sm transition hover:bg-indigo-100">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-indigo-700"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5.5v4.25c0 3.65-2.4 6.2-6 7.75-3.6-1.55-6-4.1-6-7.75V5.5l6-3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m7.5 10 1.6 1.6 3.5-3.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span><strong>{{ $notification->data['title'] ?? 'Billing update' }}</strong><span class="mt-0.5 block text-indigo-800">{{ $notification->data['message'] ?? 'There is an update to your billing account.' }}</span></span>
                        </a>
                    @endforeach
                </section>
            @endif

            <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-700 via-indigo-700 to-blue-800 px-6 py-8 text-white shadow-xl sm:px-10 sm:py-10">
                <div class="absolute -right-16 -top-28 -z-10 h-80 w-80 rounded-full border-[40px] border-white/10"></div>
                <div class="absolute -bottom-36 right-40 -z-10 h-64 w-64 rounded-full bg-sky-300/15 blur-3xl"></div>
                <div class="relative flex flex-col gap-7 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-indigo-100">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 2.5 16 5.5v4.25c0 3.65-2.4 6.2-6 7.75-3.6-1.55-6-4.1-6-7.75V5.5l6-3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="m7.5 10 1.6 1.6 3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            YOUR STUDENT ACCOUNT
                        </span>
                        <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">Welcome back, {{ Auth::user()->name }}.</h2>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-indigo-100 sm:text-base">Your student details, school bills, and payment history are all in one place.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('student.bills.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-indigo-800 shadow-sm transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-700">
                            View my bills <span aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('profile.show') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white">
                            Edit profile
                        </a>
                    </div>
                </div>
            </section>

            @if ($studentProfile)
                @php
                    $totalBalance = $studentBills->sum(fn ($bill) => (float) $bill->period_balance)
                        + $studentCarryForwards->whereNull('destination_bill_id')->sum(fn ($extension) => (float) $extension->remaining_balance);
                    $totalFees = $studentBills->sum(fn ($bill) => (float) $bill->total_fees);
                    $totalDiscount = $studentBills->sum(fn ($bill) => (float) $bill->scholarship_discount);
                    $totalPaid = $studentBills->sum(fn ($bill) => (float) $bill->total_paid);
                    $openBills = $studentBills->filter(fn ($bill) => (float) $bill->period_balance > 0)->count()
                        + $studentCarryForwards->whereNull('destination_bill_id')->filter(fn ($extension) => (float) $extension->remaining_balance > 0)->count();
                    $currentBill = $studentBills->first();
                    $latestPayment = $studentBills->pluck('latestPayment')->filter()->sort(function ($left, $right) {
                        return ($right->paid_at->getTimestamp() <=> $left->paid_at->getTimestamp())
                            ?: ($right->id <=> $left->id);
                    })->first();
                    $recentPayments = $studentBills->flatMap(fn ($bill) => $bill->payments->map(fn ($payment) => ['bill' => $bill, 'payment' => $payment]))
                        ->sortByDesc(fn ($entry) => $entry['payment']->paid_at)
                        ->take(5);
                @endphp

                @if ($currentBill)
                    <section class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm sm:p-6" aria-label="Current billing period">
                        <h2 class="font-bold text-slate-900">Your current billing period</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $currentBill->schoolTerm->school_year }} · {{ $currentBill->schoolTerm->semester }} · {{ $currentBill->billing_period }}</p>
                        <p class="mt-1 text-sm text-slate-500">Invoice {{ $currentBill->invoice_number }}{{ $currentBill->due_date ? ' · Due '.$currentBill->due_date->format('M j, Y') : '' }}</p>
                    </section>
                @endif

                <section aria-label="Student billing summary">
                    <div class="mb-4">
                        <h2 class="text-lg font-bold text-slate-900">Your account at a glance</h2>
                        <p class="mt-1 text-sm text-slate-500">A simple overview of your school billing activity.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Total school fees</p>
                            <p class="mt-3 text-2xl font-bold tracking-tight text-slate-900">₱{{ number_format($totalFees, 2) }}</p>
                            <p class="mt-2 text-xs text-slate-500">Before scholarship discounts</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <p class="text-sm font-medium text-slate-500">Scholarship discounts</p>
                            <p class="mt-3 text-2xl font-bold tracking-tight text-emerald-700">−₱{{ number_format($totalDiscount, 2) }}</p>
                            <p class="mt-2 text-xs text-slate-500">Applied to your bills</p>
                        </article>
                        <article class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div><p class="text-sm font-medium text-slate-500">Remaining balance</p><p class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">₱{{ number_format($totalBalance, 2) }}</p></div>
                                <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-50 text-indigo-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18m5-14.5c-.7-1.05-2.3-1.75-5-1.75-3 0-5 1.25-5 3.25s1.5 3 5 3.75 5 1.75 5 3.75-2 3.25-5 3.25c-2.7 0-4.3-.7-5-1.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                            </div>
                            <a href="{{ route('student.bills.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-700">See billing details <span aria-hidden="true">&rarr;</span></a>
                        </article>
                        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div><p class="text-sm font-medium text-slate-500">Payments recorded</p><p class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">₱{{ number_format($totalPaid, 2) }}</p></div>
                                <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5 9.5 17 19 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                            </div>
                            <p class="mt-4 text-sm text-slate-500">Total across all your bills</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div><p class="text-sm font-medium text-slate-500">Bills with a balance</p><p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($openBills) }}</p></div>
                                <span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.73 3h14.94a2 2 0 0 0 1.73-3L13.7 3.9a2 2 0 0 0-3.46 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </div>
                            <p class="mt-4 text-sm text-slate-500">Includes balances carried to their next payment period</p>
                        </article>
                        <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div><p class="text-sm font-medium text-slate-500">Latest payment</p><p class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">₱{{ number_format((float) ($latestPayment?->amount ?? 0), 2) }}</p></div>
                                <span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-50 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7.5L19 8.25v11A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75ZM14 4v5h5M8.5 13h7m-7 3.5h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            </div>
                            <p class="mt-4 text-sm text-slate-500">{{ $latestPayment ? $latestPayment->paid_at->format('M j, Y') : 'No payment recorded yet' }}</p>
                        </article>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm" aria-label="Tuition installment plan">
                    <div class="border-b border-indigo-100 bg-indigo-50/70 px-5 py-4 sm:px-6">
                        <h2 class="font-bold text-indigo-950">Tuition installment plan</h2>
                        <p class="mt-1 text-sm leading-5 text-indigo-800">₱{{ number_format((float) $tuitionPlan['tuition_total'], 2) }} total tuition, less the upfront enrollment and sanitation fees.</p>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Enrollment fee</p>
                            <p class="mt-2 text-lg font-bold text-slate-900">−₱{{ number_format((float) $tuitionPlan['enrollment_fee'], 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sanitation fee</p>
                            <p class="mt-2 text-lg font-bold text-slate-900">−₱{{ number_format((float) $tuitionPlan['sanitation_fee'], 2) }}</p>
                        </div>
                        <div class="rounded-xl bg-indigo-50 p-4 sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Tuition split across four periods</p>
                            <p class="mt-2 text-lg font-bold text-indigo-950">₱{{ number_format((float) $tuitionPlan['remaining_tuition'], 2) }}</p>
                        </div>
                        @foreach ($tuitionPlan['installments'] as $installment)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <p class="text-sm font-semibold text-slate-600">{{ $installment['period'] }}</p>
                                <p class="mt-2 text-xl font-bold text-slate-900">₱{{ number_format((float) $installment['amount'], 2) }}</p>
                            </div>
                        @endforeach
                    </div>
                    <p class="px-5 pb-5 text-xs leading-5 text-slate-500 sm:px-6">Upfront fee amounts follow the active fee catalog. This schedule is a tuition guide; your official invoice and recorded payments determine your account balance.</p>
                </section>

                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Recent transactions">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div><h2 class="font-bold text-slate-900">Recent transactions</h2><p class="mt-1 text-sm text-slate-500">Payments are listed after the school records or confirms them.</p></div>
                        <a href="{{ route('student.payments.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">View payment history</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @forelse ($recentPayments as $entry)
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 sm:px-6">
                                <div><p class="font-semibold text-slate-900">{{ $entry['bill']->invoice_number }} · {{ $entry['bill']->billing_period }}</p><p class="mt-1 text-xs text-slate-500">{{ $entry['payment']->paid_at->format('M j, Y g:i A') }} · {{ $entry['payment']->method }}@if($entry['payment']->reference) · Ref {{ $entry['payment']->reference }}@endif</p></div>
                                <p class="font-bold text-emerald-700">₱{{ number_format((float) $entry['payment']->amount, 2) }}</p>
                            </div>
                        @empty
                            <p class="px-5 py-5 text-sm text-slate-500 sm:px-6">No confirmed payments have been recorded yet. Submitted payment requests appear in your payment history.</p>
                        @endforelse
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-[1fr_0.75fr]">
                    <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-6">
                            <div><h2 class="font-bold text-slate-900">Recent bills</h2><p class="mt-1 text-sm text-slate-500">Your latest school invoices and balances.</p></div>
                            <a href="{{ route('student.bills.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50">View all</a>
                        </div>
                        @if ($studentBills->isNotEmpty())
                            <div class="divide-y divide-slate-100">
                                @foreach ($studentBills->take(4) as $bill)
                                    <a href="{{ route('student.bills.invoice', $bill) }}" class="group flex flex-wrap items-center justify-between gap-4 px-5 py-4 transition hover:bg-slate-50/80 sm:px-6">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7.5L19 8.25v11A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75ZM14 4v5h5M8.5 13h7m-7 3.5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-800">{{ $bill->invoice_number }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $bill->schoolTerm->display_name }} · {{ $bill->billing_date->format('M j, Y') }}</span></span>
                                        </div>
                                        <div class="ml-13 flex items-center gap-3 sm:ml-0">
                                            <span class="text-right"><span class="block text-sm font-bold text-slate-800">₱{{ number_format((float) $bill->period_balance, 2) }}</span><span class="mt-0.5 block text-xs text-slate-500">due this period</span></span>
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-rose-50 text-rose-700' => $bill->payment_status === 'Unpaid',
                                                'bg-amber-50 text-amber-700' => $bill->payment_status === 'Partially Paid',
                                                'bg-emerald-50 text-emerald-700' => $bill->payment_status === 'Fully Paid',
                                            ])>{{ $bill->payment_status }}</span>
                                            <span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-indigo-600" aria-hidden="true">&rarr;</span>
                                        </div>
                                    </a>
                                    @php($latestLetter = $bill->promissoryLetters->sortByDesc('submitted_at')->first())
                                    @if ($latestLetter)
                                        <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/70 px-5 py-2.5 text-xs sm:px-6">
                                            <span class="text-slate-600">Promissory request: <strong>{{ $latestLetter->status }}</strong></span>
                                            <a href="{{ route('student.promissory-letters.show', $latestLetter) }}" class="font-semibold text-indigo-600 hover:underline">View request</a>
                                        </div>
                                    @endif
                                    @if ((float) $bill->remaining_balance > 0)
                                        <div class="flex flex-wrap items-start justify-between gap-4 border-t border-slate-100 bg-slate-50/70 px-5 py-3 sm:px-6">
                                            @php($pendingPaymentRequest = $bill->paymentRequests->firstWhere('status', 'Pending'))
                                            @if ($pendingPaymentRequest)
                                                <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Payment request pending: ₱{{ number_format((float) $pendingPaymentRequest->amount, 2) }} · awaiting admin confirmation</p>
                                            @else
                                            <details class="min-w-56 flex-1">
                                                <summary class="cursor-pointer text-sm font-semibold text-emerald-700 hover:underline">Submit a full or partial payment request</summary>
                                                <form method="POST" action="{{ route('student.bills.payments.store', $bill) }}" x-data="{ confirming: false, paymentAmount: @js(old('amount')), paymentReference: @js(old('reference')) }" class="mt-3 grid gap-3 rounded-xl border border-emerald-100 bg-white p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                                    @csrf
                                                    <label class="block text-xs font-semibold text-slate-700">Amount (remaining ₱{{ number_format((float) $bill->remaining_balance, 2) }})
                                                        <input name="amount" type="number" min="0.01" max="{{ $bill->remaining_balance }}" step="0.01" value="{{ old('amount') }}" x-model="paymentAmount" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                                    </label>
                                                    <label class="block text-xs font-semibold text-slate-700">Payment reference (optional)
                                                        <input name="reference" maxlength="100" value="{{ old('reference') }}" x-model="paymentReference" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                                                    </label>
                                                    <button type="button" @click="if ($el.form.reportValidity()) confirming = true" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Review request</button>
                                                    <p class="text-xs leading-5 text-slate-500 sm:col-span-3">An administrator must confirm receipt before the payment is added to your balance.</p>
                                                    <div x-cloak x-show="confirming" role="status" class="space-y-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 sm:col-span-3">
                                                        <p class="font-semibold text-slate-900">Confirm request for ₱<span x-text="Number(paymentAmount || 0).toFixed(2)"></span></p>
                                                        <p class="text-xs leading-5 text-slate-700">This sends a request to the school for verification. Your bill will not be marked paid until the school confirms receipt.</p>
                                                        <div class="flex flex-wrap gap-2">
                                                            <button type="button" @click="confirming = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Go back</button>
                                                            <button type="submit" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Confirm and send request</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </details>
                                            @endif
                                            <div class="w-full text-xs text-slate-600">
                                                @foreach ($bill->paymentRequests->sortByDesc('created_at') as $paymentRequest)
                                                    <p>Request · ₱{{ number_format((float) $paymentRequest->amount, 2) }} · <strong>{{ $paymentRequest->status }}</strong>@if($paymentRequest->admin_note) — {{ $paymentRequest->admin_note }}@endif</p>
                                                @endforeach
                                            </div>
                                            @php($canRequestPromissory = $bill->extensions->isEmpty() && !$bill->promissoryLetters->contains(fn ($letter) => in_array($letter->status, ['Pending', 'Approved', 'Overdue'], true)))
                                            @if ($canRequestPromissory)
                                                <a href="{{ route('student.promissory-letters.create', $bill) }}" class="rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-sm font-semibold text-amber-800 hover:bg-amber-50">Request promissory letter</a>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="px-6 py-12 text-center">
                                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-500"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7.5L19 8.25v11A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75ZM14 4v5h5M8.5 13h7m-7 3.5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                <h3 class="mt-4 font-semibold text-slate-900">No bills to show</h3>
                                <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500">When the school creates a bill for you, it will appear here along with its payment status.</p>
                            </div>
                        @endif
                    </div>

                    <aside class="space-y-5">
                        <section class="overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-sm">
                            <div class="flex items-start justify-between gap-3 border-b border-indigo-50 px-5 py-4">
                                <div><h2 class="font-bold text-slate-900">Next payment</h2><p class="mt-1 text-sm text-slate-500">Your next scheduled due date.</p></div>
                                <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-indigo-700 hover:underline">All updates</a>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @forelse ($upcomingPaymentBills as $bill)
                                    <a href="{{ route('student.bills.statement', $bill) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                                        <div class="flex items-center justify-between gap-3">
                                            <span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ $bill->billing_period }} · {{ $bill->schoolTerm->display_name }}</span><span class="mt-1 block text-xs text-slate-500">{{ $bill->invoice_number }}</span></span>
                                            <span class="shrink-0 text-right"><span class="block text-sm font-bold text-slate-900">₱{{ number_format((float) $bill->period_balance, 2) }}</span><span class="mt-1 block text-xs font-semibold {{ $bill->due_date->isBefore(today()) ? 'text-rose-700' : 'text-indigo-700' }}">{{ $bill->due_date->isBefore(today()) ? 'Past due' : 'Due' }} {{ $bill->due_date->format('M j') }}</span></span>
                                        </div>
                                    </a>
                                @empty
                                    <p class="px-5 py-5 text-sm leading-6 text-slate-500">No outstanding payment has a due date scheduled yet. Once the school schedules your bill, the date and remaining balance will show here.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-indigo-600">STUDENT PROFILE</p><h2 class="mt-2 text-xl font-bold text-slate-900">{{ $studentProfile->invoice_name }}</h2><p class="mt-1 text-sm text-slate-500">Student ID · {{ $studentProfile->student_number }}</p></div>
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-500 to-sky-500 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($studentProfile->first_name, 0, 1).mb_substr($studentProfile->last_name, 0, 1)) }}</span>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                                <div><p class="text-xs font-medium text-slate-500">Course</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ $studentProfile->course?->code ?? 'Not assigned' }}</p></div>
                                <div><p class="text-xs font-medium text-slate-500">Year level</p><p class="mt-1 text-sm font-semibold text-slate-800">{{ $studentProfile->year_level ?? 'Not assigned' }}</p></div>
                                @if ($studentProfile->scholarshipAssignments()->isNotEmpty())
                                    <div class="col-span-2 rounded-lg bg-indigo-50 p-3"><p class="text-xs font-medium text-indigo-700">Scholarship(s)</p>@foreach ($studentProfile->scholarshipAssignments() as $scholarship)<p class="mt-1 text-sm font-semibold text-indigo-900">{{ $scholarship->name }} · ₱{{ number_format((float) $scholarship->discount_amount, 2) }} fixed discount</p>@endforeach</div>
                                @endif
                            </div>
                            <a href="{{ route('profile.show') }}" class="mt-5 inline-flex w-full items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">Manage my account</a>
                        </section>

                        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-900">Enrolled subjects</h2><p class="mt-1 text-sm text-slate-500">Subjects assigned by the school for your active terms.</p></div>
                            <div class="divide-y divide-slate-100">
                                @forelse($studentProfile->enrollments->sortByDesc(fn($enrollment) => $enrollment->schoolTerm->school_year.' '.$enrollment->schoolTerm->semester) as $enrollment)
                                    <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm"><div><p class="font-semibold text-slate-800">{{ $enrollment->subject->code }} · {{ $enrollment->subject->name }}</p><p class="text-xs text-slate-500">{{ $enrollment->schoolTerm->display_name }} · {{ $enrollment->units }} units</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">No extra fee</span></div>
                                @empty
                                    <p class="px-5 py-5 text-sm text-slate-500">No subjects have been assigned to your account yet.</p>
                                @endforelse
                            </div>
                        </section>

                        @if ($studentCarryForwards->isNotEmpty())
                            <section class="overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-sm">
                                <div class="border-b border-amber-100 bg-amber-50/70 px-5 py-4 sm:px-6"><h2 class="font-bold text-amber-950">Approved balances carried to the next payment period</h2><p class="mt-1 text-sm text-amber-900">Each amount is counted once and remains linked to its original invoice.</p></div>
                                <div class="divide-y divide-slate-100">@foreach ($studentCarryForwards as $extension)<div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-6"><div><p class="font-semibold text-slate-900">{{ $extension->next_billing_period ?? 'Next payment period' }} · {{ $extension->nextSchoolTerm->display_name }}</p><p class="mt-1 text-sm text-slate-600">Original invoice <a class="font-semibold text-indigo-600 hover:underline" href="{{ route('student.bills.invoice', $extension->sourceBill) }}">{{ $extension->sourceBill->invoice_number }}</a>@if($extension->destinationBill) · Included in {{ $extension->destinationBill->invoice_number }} with this period's fees @endif</p></div><p class="text-right"><span class="block text-xs text-slate-500">Deferred balance</span><span class="font-bold text-slate-900">₱{{ number_format((float) $extension->remaining_balance, 2) }}</span></p></div>@endforeach</div>
                            </section>
                        @endif

                        <section class="rounded-2xl bg-gradient-to-br from-sky-50 to-indigo-50 p-5 ring-1 ring-indigo-100 sm:p-6">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-indigo-600 shadow-sm"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5v17m0-17c-3.3 0-5.5 1-5.5 3s2.2 3 5.5 3 5.5 1 5.5 3-2.2 3-5.5 3-5.5 1-5.5 3 2.2 3 5.5 3m0-18c3.3 0 5.5 1 5.5 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            <h2 class="mt-4 font-bold text-slate-900">Stay up to date</h2>
                            <p class="mt-1.5 text-sm leading-6 text-slate-600">Check your billing history for the latest payment updates and print a copy of any invoice you need.</p>
                            <a href="{{ route('student.bills.index') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-indigo-700 hover:text-indigo-800">Go to billing history <span aria-hidden="true">&rarr;</span></a>
                        </section>
                    </aside>
                </section>
            @else
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm sm:p-8">
                    <div class="flex items-start gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-amber-700 shadow-sm"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.73 3h14.94a2 2 0 0 0 1.73-3L13.7 3.9a2 2 0 0 0-3.46 0Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        <div><h2 class="font-bold text-amber-950">Student profile not linked</h2><p class="mt-1 text-sm leading-6 text-amber-900">Your login is ready, but a school administrator still needs to link your student profile. Contact the school office if you think this is a mistake.</p></div>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
