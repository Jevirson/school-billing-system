<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4"><h2 class="text-2xl font-semibold text-slate-900">Students</h2><a href="{{ route('admin.students.create') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Add student</a></div>
    </x-slot>
    <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-7xl">
        @if (session('status'))<div class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if (session('error'))<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-4">Student ID</th><th class="px-5 py-4">Name</th><th class="px-5 py-4">Course</th><th class="px-5 py-4">Year</th><th class="px-5 py-4">Scholarship</th><th class="px-5 py-4">Login</th><th class="px-5 py-4"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse ($students as $student)
                <tr x-data="{ deleteOpen: false, confirmation: '' }">
                    <td class="px-5 py-4 font-medium text-slate-900">{{ $student->student_number }}</td>
                    <td class="px-5 py-4">{{ $student->invoice_name }}</td>
                    <td class="px-5 py-4">{{ $student->course?->name ?? '—' }}</td>
                    <td class="px-5 py-4">{{ $student->year_level ?? '—' }}</td>
                    <td class="px-5 py-4">@forelse ($student->scholarshipAssignments() as $scholarship)<span class="block font-medium text-indigo-700">{{ $scholarship->name }}</span><span class="block text-xs text-slate-500">₱{{ number_format((float) $scholarship->discount_amount, 2) }}</span>@empty<span class="text-slate-400">—</span>@endforelse</td>
                    <td class="px-5 py-4 text-slate-500">{{ $student->user->username }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <a href="{{ route('admin.students.edit', $student) }}" class="font-semibold text-indigo-600 hover:underline">Edit</a>
                            <button type="button" @click="deleteOpen = true" class="font-semibold text-red-600 hover:underline">Delete</button>
                        </div>
                        <div x-cloak x-show="deleteOpen" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-student-title-{{ $student->id }}" @keydown.escape.window="deleteOpen = false">
                            <div @click.outside="deleteOpen = false" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                                <div class="flex items-start gap-4">
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-100 text-red-700" aria-hidden="true">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.73 3h14.94a2 2 0 0 0 1.73-3L13.7 3.9a2 2 0 0 0-3.46 0Z"/></svg>
                                    </span>
                                    <div>
                                        <h2 id="delete-student-title-{{ $student->id }}" class="text-lg font-bold text-slate-900">Final approval required</h2>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">This permanently deletes <strong>{{ $student->invoice_name }}</strong>, the login account, invoices, payments, payment correction history, and promissory records. This cannot be undone.</p>
                                        @if ($student->bills_count > 0)
                                            <p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-800">This student has {{ $student->bills_count }} bill(s). All related billing and payment records will also be deleted.</p>
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.students.destroy', $student) }}" class="mt-5 space-y-4">
                                    @csrf
                                    @method('DELETE')
                                    <label class="block text-sm font-semibold text-slate-700">Type the student ID <span class="font-mono text-red-700">{{ $student->student_number }}</span> to confirm
                                        <input type="text" name="confirm_student_number" x-model="confirmation" autocomplete="off" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-red-500 focus:ring-red-500">
                                    </label>
                                    <div class="flex justify-end gap-3">
                                        <button type="button" @click="deleteOpen = false; confirmation = ''" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                        <button type="submit" :disabled="confirmation !== @js($student->student_number)" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Permanently delete student</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty<tr><td colspan="7" class="px-5 py-10 text-center text-slate-500">No students have been added.</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="mt-5">{{ $students->links() }}</div>
    </div></div>
</x-app-layout>
