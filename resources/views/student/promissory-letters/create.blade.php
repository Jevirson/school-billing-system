<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">PAYMENT EXTENSION</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Request a promissory letter</h1></div></x-slot>
    <div class="bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-5xl">
        @if ($errors->any())<div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
            <section class="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">BILL DETAILS</p>
                <h2 class="mt-3 text-xl font-bold text-slate-900">{{ $bill->invoice_number }}</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div><dt class="text-slate-500">Student</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student->invoice_name }} · {{ $student->student_number }}</dd></div>
                    <div><dt class="text-slate-500">Course and year</dt><dd class="mt-1 font-semibold text-slate-800">{{ $student->course?->name ?? 'Not assigned' }} · {{ $student->year_level ?? 'Not assigned' }}</dd></div>
                    <div><dt class="text-slate-500">School term</dt><dd class="mt-1 font-semibold text-slate-800">{{ $bill->schoolTerm->display_name }}</dd></div>
                    <div class="rounded-xl bg-indigo-50 p-4"><dt class="text-indigo-700">Outstanding amount</dt><dd class="mt-1 text-2xl font-bold text-indigo-900">₱{{ number_format((float) $bill->remaining_balance, 2) }}</dd><p class="mt-1 text-xs text-indigo-700">Automatically retrieved from your invoice.</p></div>
                </dl>
            </section>
            <div class="space-y-6" x-data="{ reason: @js(old('reason', '')), commitmentDate: @js(old('commitment_date', '')), requestedAmount: @js(old('requested_amount', $bill->remaining_balance)) }">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="font-bold text-slate-900">Your request</h2>
                    <form method="POST" action="{{ route('student.promissory-letters.store') }}" class="mt-5 space-y-5">
                        @csrf
                        <input type="hidden" name="bill_id" value="{{ $bill->id }}">
                        <label class="block text-sm font-semibold text-slate-700">Amount requested for deferral
                            <input name="requested_amount" x-model="requestedAmount" type="number" min="0.01" max="{{ $bill->remaining_balance }}" step="0.01" required class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm font-normal focus:border-indigo-500 focus:ring-indigo-500">
                            <span class="mt-1 block text-xs font-normal text-slate-500">You may request all or part of the current outstanding balance.</span>
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">Reason for requesting more time
                            <textarea name="reason" x-model="reason" rows="4" minlength="10" maxlength="3000" required placeholder="Please explain your circumstances..." class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm font-normal leading-6 focus:border-indigo-500 focus:ring-indigo-500">{{ old('reason') }}</textarea>
                        </label>
                        <label class="block text-sm font-semibold text-slate-700">Proposed payment date
                            <input name="commitment_date" x-model="commitmentDate" type="date" min="{{ today()->addDay()->toDateString() }}" value="{{ old('commitment_date') }}" required class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm font-normal focus:border-indigo-500 focus:ring-indigo-500">
                        </label>
                        <p class="text-xs leading-5 text-slate-500">Your information and current outstanding balance are filled in from your school records. Choose the amount you need deferred; an administrator may approve all or part of it. This request does not defer payment unless approved.</p>
                        <button class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Submit request for approval</button>
                    </form>
                </section>

                <section class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-5 sm:p-6">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-[0.14em] text-indigo-700">LIVE PREVIEW</p><h2 class="mt-1 text-lg font-bold text-slate-900">Promissory letter</h2></div><span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-700">Review before submitting</span></div>
                    <article class="rounded-xl border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-700 shadow-sm">
                        <div class="border-b-2 border-slate-800 pb-4 text-center">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">{{ config('app.name', 'Billing System') }}</p>
                            <p class="mt-2 text-lg font-bold tracking-wide text-slate-900">PROMISSORY LETTER</p>
                            <p class="text-xs uppercase tracking-widest text-slate-500">Request for Extension of Payment</p>
                        </div>
                        <p class="mt-4 text-right"><strong>Date:</strong> {{ now()->format('F j, Y') }}</p>
                        <p class="mt-4"><strong>To:</strong> The School Administrator<br><strong>Subject:</strong> Request for Extension of Payment</p>
                        <div class="mt-4 grid gap-x-4 gap-y-1 rounded-lg bg-slate-50 p-3 text-xs sm:grid-cols-2">
                            <p><strong>Student:</strong> {{ $student->invoice_name }}</p>
                            <p><strong>Student ID:</strong> {{ $student->student_number }}</p>
                            <p><strong>Course / Year:</strong> {{ $student->course?->name ?? 'Not assigned' }} / {{ $student->year_level ?? 'Not assigned' }}</p>
                            <p><strong>Term:</strong> {{ $bill->schoolTerm->semester }}, {{ $bill->billing_period }}, S.Y. {{ $bill->schoolTerm->school_year }}</p>
                            <p><strong>Proposed payment period:</strong> {{ \App\Models\BillingSchedule::nextPeriod($bill->billing_period) ?? 'To be arranged with the school' }}</p>
                            <p><strong>Invoice:</strong> {{ $bill->invoice_number }}</p>
                            <p><strong>Outstanding:</strong> ₱{{ number_format((float) $bill->remaining_balance, 2) }}</p>
                            <p><strong>Deferral requested:</strong> <span x-text="new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(requestedAmount || 0))"></span></p>
                        </div>
                        <p class="mt-4">Dear Sir/Madam:</p>
                        <p class="mt-3 indent-6">I respectfully request an extension of time to pay my outstanding school fees for <strong>{{ $bill->schoolTerm->semester }}</strong>, School Year <strong>{{ $bill->schoolTerm->school_year }}</strong>. Due to <strong x-text="reason || '[Your reason will appear here]'"></strong>, I am unable to settle the amount by the original due date and kindly ask the school to consider my request.</p>
                        <p class="mt-3 indent-6">I respectfully request that <strong x-text="new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(requestedAmount || 0))"></strong> of invoice <strong>{{ $bill->invoice_number }}</strong> be deferred to <strong>{{ \App\Models\BillingSchedule::nextPeriod($bill->billing_period) ?? 'a payment period arranged with the school' }}</strong>. I undertake to pay the approved amount on or before <strong x-text="commitmentDate || '[Proposed payment date]'"></strong>. I understand that this letter does not defer payment unless the school grants approval.</p>
                        <p class="mt-3">Thank you for your time and consideration.</p>
                        <p class="mt-5">Respectfully yours,</p>
                        <div class="mt-8 w-56 border-t border-slate-500 pt-2"><p class="font-semibold text-slate-900">{{ $student->invoice_name }}</p><p class="text-xs">Student signature</p></div>
                        <p class="mt-3 text-xs"><strong>Contact information:</strong> {{ request()->user()->email }}</p>
                    </article>
                </section>
            </div>
        </div>
    </div></div>
</x-app-layout>
