<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">ADMINISTRATION</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Dashboard</h1>
            </div>
            <p class="text-sm text-slate-500">{{ now()->format('l, F j, Y') }}</p>
        </div>
    </x-slot>

    <div class="bg-slate-50 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 px-6 py-8 text-white shadow-xl sm:px-10 sm:py-10">
                <div class="absolute -right-16 -top-28 -z-10 h-80 w-80 rounded-full border-[40px] border-white/5"></div>
                <div class="absolute -bottom-36 right-40 -z-10 h-64 w-64 rounded-full bg-indigo-400/10 blur-3xl"></div>
                <div class="relative flex flex-col gap-8 md:flex-row md:items-end md:justify-between">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-indigo-100">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            SCHOOL BILLING OVERVIEW
                        </span>
                        <h2 class="mt-5 text-3xl font-bold tracking-tight sm:text-4xl">Good day, {{ Auth::user()->name }}.</h2>
                        <p class="mt-3 max-w-xl text-sm leading-6 text-indigo-100 sm:text-base">
                            Your school billing workspace is ready. Manage student records, issue bills, and keep payments on track from here.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.announcements.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white">
                            Make an announcement
                        </a>
                        <a href="{{ route('admin.bills.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-indigo-800 shadow-sm transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-900">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            Create a bill
                        </a>
                        <a href="{{ route('admin.students.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.5 17a6.5 6.5 0 0 1 13 0M16 5v5m-2.5-2.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                            Add a student
                        </a>
                        <a href="{{ route('admin.promissory-letters.index', ['status' => 'Pending']) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white">
                            Review requests
                            @if ($pendingPromissoryCount > 0)<span class="rounded-full bg-amber-300 px-2 py-0.5 text-xs font-bold text-amber-950">{{ $pendingPromissoryCount }}</span>@endif
                        </a>
                    </div>
                </div>
            </section>

            <section aria-label="School billing statistics">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">At a glance</h2>
                        <p class="mt-1 text-sm text-slate-500">A quick snapshot of your billing activity.</p>
                    </div>
                    <a href="{{ route('admin.bills.index') }}" class="hidden text-sm font-semibold text-indigo-600 hover:text-indigo-700 sm:inline-flex sm:items-center sm:gap-1">View all bills <span aria-hidden="true">&rarr;</span></a>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-slate-500">Student profiles</p><p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($profileCount) }}</p></div>
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-50 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20m6-9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm10 9v-1.5a3.5 3.5 0 0 0-2.75-3.42M16 4.14a3.5 3.5 0 0 1 0 6.72" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                        </div>
                        <a href="{{ route('admin.students.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-sky-700 hover:text-sky-800">View students <span aria-hidden="true">&rarr;</span></a>
                    </article>
                    <article class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-slate-500">Bills issued</p><p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($billCount) }}</p></div>
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-violet-50 text-violet-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7.5L19 8.25v11A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75ZM14 4v5h5M8.5 13h7m-7 3.5h7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </div>
                        <a href="{{ route('admin.bills.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-violet-700 hover:text-violet-800">Review invoices <span aria-hidden="true">&rarr;</span></a>
                    </article>
                    <article class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-slate-500">Outstanding balance</p><p class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">₱{{ number_format((float) $outstandingBalance, 2) }}</p></div>
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v18m5-14.5c-.7-1.05-2.3-1.75-5-1.75-3 0-5 1.25-5 3.25s1.5 3 5 3.75 5 1.75 5 3.75-2 3.25-5 3.25c-2.7 0-4.3-.7-5-1.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></span>
                        </div>
                        <p class="mt-4 text-sm text-slate-500">Remaining across all student bills</p>
                    </article>
                    <article class="group rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div><p class="text-sm font-medium text-slate-500">Student accounts</p><p class="mt-3 text-3xl font-bold tracking-tight text-slate-900">{{ number_format($studentCount) }}</p></div>
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3.5 19 7v5c0 4.25-2.8 7.25-7 9-4.2-1.75-7-4.75-7-9V7l7-3.5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                        </div>
                        <p class="mt-4 text-sm text-slate-500">{{ number_format($adminCount) }} admins · {{ number_format($totalUsers) }} accounts total</p>
                    </article>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.6fr_0.9fr]">
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-5 sm:px-6">
                        <div><h2 class="font-bold text-slate-900">Recent bills</h2><p class="mt-1 text-sm text-slate-500">The latest invoices added to your system.</p></div>
                        <a href="{{ route('admin.bills.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-indigo-600 transition hover:bg-indigo-50">View all</a>
                    </div>
                    @if ($recentBills->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3.5 sm:px-6">Student</th><th class="px-5 py-3.5">Invoice</th><th class="px-5 py-3.5">Balance</th><th class="px-5 py-3.5">Status</th></tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($recentBills as $bill)
                                        <tr class="transition hover:bg-slate-50/70">
                                            <td class="whitespace-nowrap px-5 py-4 sm:px-6"><span class="block font-semibold text-slate-800">{{ $bill->studentProfile->invoice_name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $bill->studentProfile->student_number }}</span></td>
                                            <td class="whitespace-nowrap px-5 py-4"><a href="{{ route('admin.bills.show', $bill) }}" class="font-semibold text-indigo-600 hover:text-indigo-700">{{ $bill->invoice_number }}</a><span class="mt-0.5 block text-xs text-slate-500">{{ $bill->schoolTerm->display_name }}</span></td>
                                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">₱{{ number_format((float) $bill->remaining_balance, 2) }}</td>
                                            <td class="whitespace-nowrap px-5 py-4"><span @class([
                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-rose-50 text-rose-700' => $bill->payment_status === 'Unpaid',
                                                'bg-amber-50 text-amber-700' => $bill->payment_status === 'Partially Paid',
                                                'bg-emerald-50 text-emerald-700' => $bill->payment_status === 'Fully Paid',
                                            ])>{{ $bill->payment_status }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="px-6 py-12 text-center">
                            <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-indigo-50 text-indigo-600"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7.5L19 8.25v11A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75ZM14 4v5h5M8.5 13h7m-7 3.5h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <h3 class="mt-4 font-semibold text-slate-900">No bills yet</h3>
                            <p class="mx-auto mt-1 max-w-sm text-sm leading-6 text-slate-500">Once you have added a student, create a bill to see invoices and balances here.</p>
                            <a href="{{ route('admin.bills.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Create your first bill <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    @endif
                </div>

                <aside class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                    <div><h2 class="font-bold text-slate-900">Quick actions</h2><p class="mt-1 text-sm text-slate-500">Common tasks, right when you need them.</p></div>
                    <div class="mt-5 space-y-3">
                        <a href="{{ route('admin.bills.create') }}" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-indigo-100 text-indigo-700"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Create a bill</span><span class="mt-0.5 block text-xs text-slate-500">Issue a new student invoice</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-indigo-600" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('admin.students.create') }}" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-sky-200 hover:bg-sky-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.5 17a6.5 6.5 0 0 1 13 0M16 5v5m-2.5-2.5h5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Add a student</span><span class="mt-0.5 block text-xs text-slate-500">Create a student login and profile</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-sky-600" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('admin.enrollments.create') }}" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-cyan-200 hover:bg-cyan-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-cyan-100 text-cyan-700"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 4.5h12v11H4v-11ZM7 7.5h6M7 10h6M7 12.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Enroll a student</span><span class="mt-0.5 block text-xs text-slate-500">Assign subjects by course and school term</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-cyan-600" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('admin.catalog.index') }}" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-amber-200 hover:bg-amber-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 5.5h13m-11 0V4h9v1.5m-9 0v10h9v-10m-6 3h3m-3 3h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Billing setup</span><span class="mt-0.5 block text-xs text-slate-500">Manage fees, terms, and discounts</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-amber-700" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('admin.catalog.index') }}#exam-dates" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-sky-200 hover:bg-sky-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 5.5h12v11H4v-11ZM6.5 3.5v4m7-4v4M4 8.5h12m-8 3h1m2.5 0h1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Exam &amp; payment dates</span><span class="mt-0.5 block text-xs text-slate-500">Manage period dates in Billing setup</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-sky-700" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('admin.promissory-letters.index', ['status' => 'Pending']) }}" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-amber-200 hover:bg-amber-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7.5L19 8.25v11A1.75 1.75 0 0 1 17.25 21h-10A1.75 1.75 0 0 1 5.5 19.25v-13A2.5 2.5 0 0 1 8 3.75ZM14 4v5h5M8.5 13h7m-7 3.5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Promissory requests</span><span class="mt-0.5 block text-xs text-slate-500">{{ $pendingPromissoryCount }} waiting for review</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-amber-700" aria-hidden="true">&rarr;</span>
                        </a>
                        <a href="{{ route('admin.payment-requests.index', ['status' => 'Pending']) }}" class="group flex items-center gap-4 rounded-xl border border-slate-200 p-4 transition hover:border-emerald-200 hover:bg-emerald-50/50">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 6.5h14v9H3v-9ZM3 8h14M6 12.5h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                            <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-slate-800">Payment requests</span><span class="mt-0.5 block text-xs text-slate-500">{{ $pendingPaymentRequestCount }} waiting for payment confirmation</span></span><span class="text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-emerald-700" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                    <div class="mt-5 rounded-xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Need a hand?</p>
                        <p class="mt-1.5 text-sm leading-5 text-slate-600">Set up school terms and fees before creating your first bill.</p>
                        <a href="{{ route('admin.catalog.index') }}" class="mt-3 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-700">Set up billing <span class="ml-1" aria-hidden="true">&rarr;</span></a>
                    </div>
                </aside>
            </section>
        </div>
    </div>
</x-app-layout>
