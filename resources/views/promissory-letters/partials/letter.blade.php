<style>
    @media print {
        nav, header, footer, .no-print { display: none !important; }
        body { background: #fff !important; }
        .print-letter { border: 0 !important; box-shadow: none !important; }
    }
</style>
<article class="print-letter rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
    <div class="border-b-2 border-slate-800 pb-5 text-center">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">{{ config('app.name', 'Billing System') }}</p>
        <h1 class="mt-3 text-2xl font-bold tracking-wide text-slate-900">PROMISSORY LETTER</h1>
        <p class="mt-1 text-xs uppercase tracking-widest text-slate-500">Request for Extension of Payment</p>
    </div>
    <div class="py-6 text-sm leading-6 text-slate-700 sm:text-base">
        <p class="text-right"><strong>Date:</strong> {{ $letter->submitted_at->format('F j, Y') }}</p>
        <div class="mt-6">
            <p><strong>To:</strong> The School Administrator</p>
            <p><strong>Subject:</strong> Request for Extension of Payment</p>
        </div>
        <div class="mt-5 grid gap-x-6 gap-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2">
            <p><strong>Student:</strong> {{ $letter->student_name }}</p>
            <p><strong>Student ID:</strong> {{ $letter->student_number }}</p>
            <p><strong>Course / Year:</strong> {{ $letter->course_name ?: 'Not assigned' }}{{ $letter->year_level ? ' / '.$letter->year_level : '' }}</p>
            <p><strong>Term:</strong> {{ $letter->semester }} · {{ $letter->billing_period }}, S.Y. {{ $letter->school_year }}</p>
            <p><strong>Proposed payment period:</strong> {{ \App\Models\BillingSchedule::nextPeriod($letter->billing_period) ?? 'To be arranged with the school' }}</p>
            <p><strong>Invoice:</strong> {{ $letter->invoice_number }}</p>
            <p><strong>Outstanding amount:</strong> ₱{{ number_format((float) $letter->outstanding_amount, 2) }}</p>
            <p><strong>Amount requested for deferral:</strong> ₱{{ number_format((float) $letter->requested_amount, 2) }}</p>
        </div>

        <div class="mt-6 space-y-4">
            <p>Dear Sir/Madam:</p>
            <p class="indent-8">
                I respectfully request an extension of time to pay my outstanding school fees for
                <strong>{{ $letter->semester }}</strong>, School Year <strong>{{ $letter->school_year }}</strong>.
                Due to {{ $letter->reason }}, I am unable to settle the amount by the original due date. I kindly ask the school to consider my request.
            </p>
            <p class="indent-8">
                I respectfully request that <strong>₱{{ number_format((float) $letter->requested_amount, 2) }}</strong>
                of invoice <strong>{{ $letter->invoice_number }}</strong> be deferred. I undertake to pay the amount approved
                by the school on or before
                <strong>{{ $letter->commitment_date->format('F j, Y') }}</strong>, if this request is approved.
                I understand that this letter does not defer payment unless the school grants approval.
            </p>
            <p class="indent-8">Thank you for your time and consideration.</p>
        </div>

        <div class="mt-8">
            <p>Respectfully yours,</p>
            <div class="mt-12 w-72 border-t border-slate-500 pt-2">
                <p class="font-semibold text-slate-900">{{ $letter->student_name }}</p>
                <p class="text-sm">Student signature</p>
            </div>
            <p class="mt-4 text-sm"><strong>Contact information:</strong> {{ $letter->contact_information ?: 'Not provided' }}</p>
        </div>

        <div class="mt-10 border-t border-dashed border-slate-300 pt-4 text-xs leading-5 text-slate-500">
            <p class="font-bold uppercase tracking-wide">For school use</p>
            <p class="mt-2">Decision: ____________________ &nbsp;&nbsp; Date: ____________________</p>
            <p class="mt-2">Reviewed by: __________________________________________</p>
        </div>
    </div>
    @if ($letter->admin_note)
        <div class="no-print mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <p class="font-semibold text-slate-800">Administrator note</p>
            <p class="mt-1 whitespace-pre-line text-slate-600">{{ $letter->admin_note }}</p>
        </div>
    @endif
</article>
