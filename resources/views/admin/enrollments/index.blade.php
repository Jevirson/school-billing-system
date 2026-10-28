<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">ACADEMIC RECORDS</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Student enrollment</h1>
            </div>
            <a href="{{ route('admin.enrollments.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Enroll student</a>
        </div>
    </x-slot>

    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-5">
            @if (session('status'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
            @if (session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>@endif

            <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto]">
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search student name or subject" aria-label="Search student name or subject" class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="school_term_id" aria-label="Filter by school term" class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All terms</option>
                    @foreach ($terms as $term)<option value="{{ $term->id }}" @selected(($filters['school_term_id'] ?? '') == $term->id)>{{ $term->display_name }}</option>@endforeach
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Filter</button>
            </form>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr><th scope="col" class="px-5 py-4">Student</th><th scope="col" class="px-5 py-4">Course</th><th scope="col" class="px-5 py-4">Year level</th><th scope="col" class="px-5 py-4 text-right"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($students as $student)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-4 font-semibold text-slate-900">{{ $student->invoice_name }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $student->course?->code ?? 'Not assigned' }}@if($student->course)<span class="block text-xs text-slate-500">{{ $student->course->name }}</span>@endif</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $student->year_level ?? 'Not assigned' }}</td>
                                    <td class="px-5 py-4 text-right"><a href="{{ route('admin.enrollments.show', $student) }}" class="inline-flex items-center rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50">View subjects</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-12 text-center"><p class="font-semibold text-slate-800">No students with active enrollments found</p><p class="mt-1 text-sm text-slate-500">Enroll a student to see them listed here.</p></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($students->hasPages())<div class="border-t border-slate-100 p-4">{{ $students->links() }}</div>@endif
            </section>
        </div>
    </main>
</x-app-layout>
