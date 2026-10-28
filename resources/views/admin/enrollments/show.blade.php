<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('admin.enrollments.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-800">← Student enrollment</a>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $student->invoice_name }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $student->course?->code ?? 'No course assigned' }} · {{ $student->year_level ?? 'Year level not assigned' }}</p>
            </div>
            <a href="{{ route('admin.enrollments.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Enroll student</a>
        </div>
    </x-slot>

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-5">
            @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-bold text-slate-900">Enrolled subjects</h2>
                    <p class="mt-1 text-sm text-slate-500">Subject enrollments grouped by term.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($enrollments as $enrollment)
                        <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $enrollment->subject->code }} · {{ $enrollment->subject->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $enrollment->schoolTerm->display_name }} · {{ $enrollment->units }} units</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($enrollment->withdrawn_at)
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Withdrawn {{ $enrollment->withdrawn_at->format('M j, Y') }}</span>
                                @elseif ($enrollment->withdrawalRequest?->status === 'Pending')
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Withdrawal pending</span>
                                @elseif ($enrollment->withdrawalRequest?->status === 'Rejected')
                                    <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">Withdrawal rejected</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Active</span>
                                @endif
                                @if ($enrollment->bill)
                                    <a href="{{ route('admin.bills.show', $enrollment->bill) }}" class="text-xs font-semibold text-indigo-700 underline">{{ $enrollment->bill->invoice_number }}</a>
                                @else
                                    <span class="text-xs text-slate-500">Not billed</span>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <p class="font-semibold text-slate-800">No subject enrollments</p>
                            <p class="mt-1 text-sm text-slate-500">This student does not have any enrolled subjects yet.</p>
                        </div>
                    @endforelse
                </div>
            </section>
            <div class="flex justify-end"><a href="{{ route('admin.subject-withdrawals.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">Review withdrawal requests</a></div>
        </div>
    </main>
</x-app-layout>
