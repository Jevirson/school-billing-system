<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">PROMISSORY REQUEST</p><h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $letter->invoice_number }}</h1></div><div class="flex items-center gap-3"><span @class(['rounded-full px-3 py-1.5 text-xs font-bold','bg-amber-50 text-amber-800' => $letter->status === 'Pending','bg-emerald-50 text-emerald-800' => in_array($letter->status, ['Approved', 'Fulfilled'], true),'bg-rose-50 text-rose-800' => in_array($letter->status, ['Rejected', 'Overdue'], true)])>{{ $letter->status }}</span><button onclick="window.print()" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Print letter</button></div></div></x-slot>
    <div class="bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-bold text-slate-900">Student and bill</h2>
                    <dl class="mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</dt><dd class="mt-1 font-semibold text-slate-900">{{ $letter->student_name }}</dd><dd class="text-sm text-slate-500">{{ $letter->student_number }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Course and year level</dt><dd class="mt-1 font-semibold text-slate-900">{{ $letter->course_name ?? 'Not assigned' }}</dd><dd class="text-sm text-slate-500">{{ $letter->year_level ?? 'Not assigned' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Invoice / billing period</dt><dd class="mt-1"><a href="{{ route('admin.bills.show', $letter->bill) }}" class="font-semibold text-indigo-600 hover:underline">{{ $letter->invoice_number }}</a></dd><dd class="text-sm text-slate-500">{{ $letter->semester }} · {{ $letter->billing_period }} · {{ $letter->school_year }}</dd><dd class="mt-1 text-sm font-semibold text-slate-700">Requested amount: ₱{{ number_format((float) $letter->requested_amount, 2) }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted / commitment</dt><dd class="mt-1 font-semibold text-slate-900">{{ $letter->submitted_at->format('M j, Y g:i A') }}</dd><dd class="text-sm text-slate-500">Payment promised by {{ $letter->commitment_date->format('M j, Y') }}</dd></div>
                        <div class="rounded-xl bg-amber-50 p-4 sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-amber-800">Current outstanding balance</dt><dd class="mt-1 text-2xl font-bold text-amber-950">₱{{ number_format((float) $letter->bill->remaining_balance, 2) }}</dd><dd class="mt-1 text-xs text-amber-800">At request: ₱{{ number_format((float) $letter->outstanding_amount, 2) }} · Total paid: ₱{{ number_format((float) $letter->bill->total_paid, 2) }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $letter->reason }}</dd></div>
                    </dl>
                </section>
                @include('promissory-letters.partials.letter', ['letter' => $letter])
            </div>
            <aside class="space-y-6">
                @if ($letter->status === 'Pending')
                    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="font-bold text-slate-900">Review request</h2>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Approval preserves the original invoice and carries only the approved unpaid amount into the immediately following payment period. Exam dates are scheduled separately. This does not record a payment.</p>
                        <form method="POST" action="{{ route('admin.promissory-letters.decide', $letter) }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <label class="block text-sm font-semibold text-slate-700">Amount to defer (requested ₱{{ number_format((float) $letter->requested_amount, 2) }})
                                <input name="deferred_amount" type="number" min="0.01" max="{{ min((float) $letter->requested_amount ?: (float) $letter->outstanding_amount, (float) $letter->bill->remaining_balance) }}" step="0.01" value="{{ old('deferred_amount', number_format(min((float) $letter->requested_amount ?: (float) $letter->outstanding_amount, (float) $letter->bill->remaining_balance), 2, '.', '')) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            </label>
                            <div class="rounded-lg bg-indigo-50 p-3 text-sm">
                                <p class="font-semibold text-indigo-950">Next payment period</p>
                                @if ($nextBillingPeriod)
                                    <p class="mt-1 text-indigo-800">{{ $nextBillingPeriod }} · {{ $letter->bill->schoolTerm->display_name }}</p>
                                    @if ($nextBillingSchedule)
                                        <p class="mt-1 text-xs text-indigo-700">Payment due date: {{ $nextBillingSchedule->due_date->format('M j, Y') }}</p>
                                    @else
                                        <p class="mt-1 text-xs text-indigo-700">No payment due date is configured yet. Approval is still allowed; the due date can be set later in Billing setup.</p>
                                    @endif
                                @else
                                    <p class="mt-1 text-amber-900">There is no following payment period after this bill.</p>
                                @endif
                            </div>
                            <label class="block text-sm font-semibold text-slate-700">Administrator note (optional)<textarea name="admin_note" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-normal">{{ old('admin_note') }}</textarea></label>
                            <button @disabled($nextBillingPeriod === null) class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Approve and carry to {{ $nextBillingPeriod ?? 'next period' }}</button>
                        </form>
                        <div class="my-5 border-t border-slate-100"></div>
                        <form method="POST" action="{{ route('admin.promissory-letters.decide', $letter) }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <label class="block text-sm font-semibold text-slate-700">Reason for rejection <textarea name="admin_note" rows="3" required minlength="3" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm font-normal" placeholder="Explain why this request cannot be approved.">{{ old('admin_note') }}</textarea></label>
                            <button class="w-full rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">Deny request</button>
                        </form>
                    </section>
                @elseif ($letter->extension)
                    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-amber-800">APPROVED EXTENSION</p>
                        <h2 class="mt-2 text-lg font-bold text-amber-950">{{ $letter->extension->next_billing_period ?? 'Next payment period' }} · {{ $letter->nextSchoolTerm->display_name }}</h2>
                        <p class="mt-2 text-sm leading-6 text-amber-900">Approved through {{ $letter->extension->approved_until->format('F j, Y') }}. The carry-forward was ₱{{ number_format((float) $letter->extension->amount_at_approval, 2) }}; current original invoice balance is <strong>₱{{ number_format((float) $letter->extension->remaining_balance, 2) }}</strong>.</p>
                        <p class="mt-3 text-xs leading-5 text-amber-900">The deferred amount is included in this payment period's balance and remains linked to the original invoice. Payments recorded on the original invoice reduce the carried amount without duplicating the debt.</p>
                    </section>
                @elseif ($letter->admin_note)
                    <section class="rounded-2xl border border-slate-200 bg-white p-6"><h2 class="font-bold text-slate-900">Administrator response</h2><p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $letter->admin_note }}</p></section>
                @endif
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-bold text-slate-900">Request history</h2><ol class="mt-4 space-y-4">@foreach($letter->events as $event)<li class="flex gap-3"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><div><p class="text-sm font-semibold text-slate-800">{{ $event->status }} <span class="font-normal text-slate-500">· {{ $event->created_at->format('M j, Y g:i A') }}</span></p>@if($event->changedBy)<p class="text-xs text-slate-500">By {{ $event->changedBy->name }}</p>@endif @if($event->note)<p class="mt-1 whitespace-pre-line text-sm leading-5 text-slate-600">{{ $event->note }}</p>@endif</div></li>@endforeach</ol></section>
            </aside>
        </div>
    </div></div>
</x-app-layout>
