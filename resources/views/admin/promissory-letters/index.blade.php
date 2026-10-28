<x-app-layout>
    <x-slot name="header"><div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">PAYMENT EXTENSIONS</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Promissory requests</h1></div></x-slot>
    <div class="bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl space-y-6">
        <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <label class="text-xs font-semibold text-slate-600">Status<select name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-slate-600">School year<select name="school_year" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal"><option value="">All years</option>@foreach($schoolYears as $year)<option @selected(request('school_year') === $year)>{{ $year }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-slate-600">Semester<select name="semester" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal"><option value="">All semesters</option>@foreach($semesters as $semester)<option @selected(request('semester') === $semester)>{{ $semester }}</option>@endforeach</select></label>
            <label class="text-xs font-semibold text-slate-600">Student<input name="student" value="{{ request('student') }}" placeholder="Name or student ID" class="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal"></label>
            <div class="flex items-end gap-2"><button class="flex-1 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Filter</button><a href="{{ route('admin.promissory-letters.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Clear</a></div>
        </form>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Student</th><th class="px-5 py-4">Invoice and period</th><th class="px-5 py-4">Requested amount / reason</th><th class="px-5 py-4">Outstanding</th><th class="px-5 py-4">Commitment</th><th class="px-5 py-4">Submitted</th><th class="px-5 py-4">Status</th><th class="px-5 py-4"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($letters as $letter)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4"><span class="font-semibold text-slate-900">{{ $letter->student_name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $letter->student_number }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="font-semibold text-indigo-600">{{ $letter->invoice_number }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $letter->semester }} · {{ $letter->billing_period }} · {{ $letter->school_year }}</span></td>
                            <td class="max-w-xs px-5 py-4"><span class="font-semibold text-slate-900">₱{{ number_format((float) $letter->requested_amount, 2) }}</span><span class="mt-1 block line-clamp-2 text-xs text-slate-600">{{ $letter->reason }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold">₱{{ number_format((float) $letter->bill->remaining_balance, 2) }}</td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $letter->commitment_date->format('M j, Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4">{{ $letter->submitted_at->format('M j, Y') }}</td>
                            <td class="whitespace-nowrap px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-xs font-bold','bg-amber-50 text-amber-800' => $letter->status === 'Pending','bg-emerald-50 text-emerald-800' => in_array($letter->status, ['Approved', 'Fulfilled'], true),'bg-rose-50 text-rose-800' => in_array($letter->status, ['Rejected', 'Overdue'], true)])>{{ $letter->status }}</span></td>
                            <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('admin.promissory-letters.show', $letter) }}" class="font-semibold text-indigo-600 hover:underline">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-12 text-center"><p class="font-semibold text-slate-800">No promissory requests found</p><p class="mt-1 text-sm text-slate-500">New student requests will appear here.</p></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $letters->links() }}</div>
        </section>
    </div></div>
</x-app-layout>
