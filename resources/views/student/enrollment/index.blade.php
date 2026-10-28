<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">STUDENT PORTAL</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">My subjects</h1>
        </div>
    </x-slot>

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl space-y-6">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            @if ($student)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Student details">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Student</p><p class="mt-1 font-semibold text-slate-900">{{ $student->invoice_name }}</p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Course</p><p class="mt-1 font-semibold text-slate-900">{{ $student->course?->code ?? 'Not assigned' }}{{ $student->course ? ' · '.$student->course->name : '' }}</p></div>
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Year level</p><p class="mt-1 font-semibold text-slate-900">{{ $student->year_level ?? 'Not assigned' }}</p></div>
                    </div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="{ expandedTerm: null }">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div><h2 class="text-lg font-bold text-slate-900">Your enrolled subjects</h2><p class="mt-1 text-sm text-slate-500">View subjects by term and request a withdrawal if needed.</p></div>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ $enrollments->whereNull('withdrawn_at')->count() }} active</span>
                    </div>

                    <div class="mt-5 space-y-3">
                        @forelse ($enrollments->groupBy('school_term_id') as $termId => $termEnrollments)
                            @php($term = $termEnrollments->first()->schoolTerm)
                            <article class="overflow-hidden rounded-xl border border-slate-200">
                                <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                                    <div><h3 class="font-semibold text-slate-900">{{ $term->display_name }}</h3><p class="mt-0.5 text-xs text-slate-500">{{ $termEnrollments->whereNull('withdrawn_at')->count() }} active subjects</p></div>
                                    <button type="button" @click="expandedTerm = expandedTerm === {{ (int) $termId }} ? null : {{ (int) $termId }}" :aria-expanded="expandedTerm === {{ (int) $termId }}" class="rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50">
                                        <span x-text="expandedTerm === {{ (int) $termId }} ? 'Hide subjects' : 'View subjects'">View subjects</span>
                                    </button>
                                </div>
                                <div x-cloak x-show="expandedTerm === {{ (int) $termId }}" x-transition class="divide-y divide-slate-100">
                                    @foreach ($termEnrollments as $enrollment)
                                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="font-semibold text-slate-900">{{ $enrollment->subject->code }} · {{ $enrollment->subject->name }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ $enrollment->units }} units</p>
                                                @if ($enrollment->withdrawn_at)
                                                    <p class="mt-1 text-xs font-semibold text-slate-500">Withdrawn {{ $enrollment->withdrawn_at->format('M j, Y') }}</p>
                                                @elseif ($enrollment->withdrawalRequest?->status === 'Pending')
                                                    <p class="mt-1 text-xs font-semibold text-amber-700">Withdrawal request pending admin review</p>
                                                @elseif ($enrollment->withdrawalRequest?->status === 'Rejected')
                                                    <p class="mt-1 text-xs text-rose-700">Last request rejected: {{ $enrollment->withdrawalRequest->admin_note }}</p>
                                                @endif
                                                @if ($enrollment->withdrawalRequest?->refund)
                                                    <p class="mt-1 text-xs font-medium text-slate-600">Refund: ₱{{ number_format((float) $enrollment->withdrawalRequest->refund->amount, 2) }} · {{ $enrollment->withdrawalRequest->refund->status }}</p>
                                                @endif
                                            </div>
                                            @unless ($enrollment->withdrawn_at || $enrollment->withdrawalRequest?->status === 'Pending')
                                                <form method="POST" action="{{ route('student.enrollment.withdrawals.store', $enrollment) }}" onsubmit="return confirm('Submit a withdrawal request for {{ $enrollment->subject->code }}? It will take effect only after admin approval.')">
                                                    @csrf
                                                    <button class="shrink-0 rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">Request withdrawal</button>
                                                </form>
                                            @endunless
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 px-5 py-10 text-center">
                                <p class="font-semibold text-slate-800">No enrolled subjects yet</p>
                                <p class="mt-1 text-sm text-slate-500">Your subjects will appear here after enrollment is completed.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @else
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Your account is not linked to a student profile. Contact the school administrator.</div>
            @endif
        </div>
    </main>
</x-app-layout>
