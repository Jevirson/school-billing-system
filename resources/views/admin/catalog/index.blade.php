<x-app-layout>
    <x-slot name="header"><h2 class="text-2xl font-semibold text-slate-900">Billing setup</h2></x-slot>
    <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl space-y-6">
            @if (session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ session('status') }}</div>@endif
            @if (session('error'))<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">{{ session('error') }}</div>@endif
            @if ($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <section id="exam-dates" class="scroll-mt-6 rounded-2xl border border-sky-100 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h3 class="text-lg font-semibold text-slate-900">Exam dates</h3><p class="mt-1 text-sm leading-6 text-slate-500">Exam announcements and dates are separate from payment due dates. Eligible students receive a notification when an exam date is added or changed.</p></div>
                </div>
                <form method="POST" action="{{ route('admin.exam-schedules.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf
                    <select name="school_term_id" required aria-label="School term" class="rounded-lg border-slate-300 text-sm"><option value="">School term</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected(old('school_term_id') == $term->id)>{{ $term->display_name }}</option>@endforeach</select>
                    <select name="exam_period" required aria-label="Exam period" class="rounded-lg border-slate-300 text-sm"><option value="">Exam period</option>@foreach($examPeriods as $period)<option value="{{ $period }}" @selected(old('exam_period') === $period)>{{ $period }}</option>@endforeach</select>
                    <label class="sr-only" for="new-exam-date">Exam date</label><input id="new-exam-date" name="exam_date" type="date" value="{{ old('exam_date') }}" required aria-label="Exam date" class="rounded-lg border-slate-300 text-sm">
                    <select name="course_id" aria-label="Course" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->code }}</option>@endforeach</select>
                    <select name="year_level" aria-label="Year level" class="rounded-lg border-slate-300 text-sm"><option value="">All year levels</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level') === $year)>{{ $year }}</option>@endforeach</select>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 sm:col-span-2 lg:col-span-5">Add exam date and send exam notification</button>
                </form>
                <div class="mt-5 space-y-3">
                    @forelse($examSchedules as $schedule)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <form method="POST" action="{{ route('admin.exam-schedules.update', $schedule) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf @method('PUT')
                                <select name="school_term_id" required aria-label="School term" class="rounded-lg border-slate-300 text-sm">@foreach($terms as $term)<option value="{{ $term->id }}" @selected($schedule->school_term_id === $term->id)>{{ $term->display_name }}</option>@endforeach</select>
                                <select name="exam_period" required aria-label="Exam period" class="rounded-lg border-slate-300 text-sm">@foreach($examPeriods as $period)<option value="{{ $period }}" @selected($schedule->exam_period === $period)>{{ $period }}</option>@endforeach</select>
                                <label class="sr-only" for="exam-date-{{ $schedule->id }}">Exam date</label><input id="exam-date-{{ $schedule->id }}" name="exam_date" type="date" value="{{ $schedule->exam_date->toDateString() }}" required aria-label="Exam date" class="rounded-lg border-slate-300 text-sm">
                                <select name="course_id" aria-label="Course" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($schedule->course_id === $course->id)>{{ $course->code }}</option>@endforeach</select>
                                <select name="year_level" aria-label="Year level" class="rounded-lg border-slate-300 text-sm"><option value="">All year levels</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected($schedule->year_level === $year)>{{ $year }}</option>@endforeach</select>
                                <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 sm:col-span-2 lg:col-span-5">
                                    <span>{{ $schedule->schoolTerm->display_name }} · {{ $schedule->exam_date->format('M j, Y') }} · {{ $schedule->course?->code ?? 'All courses' }} · {{ $schedule->year_level ?? 'All year levels' }}</span>
                                    <div class="flex items-center gap-3"><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-slate-50">Save changes</button></div>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.exam-schedules.destroy', $schedule) }}" class="mt-2 text-right" onsubmit="return confirm('Delete this exam date? Payment due dates will not change.')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Delete date</button>
                            </form>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">No exam dates have been added yet.</p>
                    @endforelse
                </div>
            </section>
            <section id="payment-dates" class="scroll-mt-6 rounded-2xl border border-indigo-100 bg-white p-6 shadow-sm">
                <div><h3 class="text-lg font-semibold text-slate-900">Payment due dates</h3><p class="mt-1 text-sm leading-6 text-slate-500">These dates apply to bills and payment reminders only. Promissory balances move to the next payment period even if its due date has not been set. Exam dates are managed separately above.</p></div>
                <form method="POST" action="{{ route('admin.billing-schedules.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf
                    <select name="school_term_id" required aria-label="School term" class="rounded-lg border-slate-300 text-sm"><option value="">School term</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected(old('school_term_id') == $term->id)>{{ $term->display_name }}</option>@endforeach</select>
                    <select name="billing_period" required aria-label="Payment period" class="rounded-lg border-slate-300 text-sm"><option value="">Payment period</option>@foreach($billingPeriods as $period)<option value="{{ $period }}" @selected(old('billing_period') === $period)>{{ $period }}</option>@endforeach</select>
                    <label class="sr-only" for="new-payment-date">Payment due date</label><input id="new-payment-date" name="due_date" type="date" value="{{ old('due_date') }}" required aria-label="Payment due date" class="rounded-lg border-slate-300 text-sm">
                    <select name="course_id" aria-label="Course" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->code }}</option>@endforeach</select>
                    <select name="year_level" aria-label="Year level" class="rounded-lg border-slate-300 text-sm"><option value="">All year levels</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level') === $year)>{{ $year }}</option>@endforeach</select>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 sm:col-span-2 lg:col-span-5">Add payment due date</button>
                </form>
                <div class="mt-5 space-y-3">
                    @forelse($billingSchedules as $schedule)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <form method="POST" action="{{ route('admin.billing-schedules.update', $schedule) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf @method('PUT')
                                <select name="school_term_id" required aria-label="School term" class="rounded-lg border-slate-300 text-sm">@foreach($terms as $term)<option value="{{ $term->id }}" @selected($schedule->school_term_id === $term->id)>{{ $term->display_name }}</option>@endforeach</select>
                                <select name="billing_period" required aria-label="Payment period" class="rounded-lg border-slate-300 text-sm">@foreach($billingPeriods as $period)<option value="{{ $period }}" @selected($schedule->billing_period === $period)>{{ $period }}</option>@endforeach</select>
                                <label class="sr-only" for="payment-date-{{ $schedule->id }}">Payment due date</label><input id="payment-date-{{ $schedule->id }}" name="due_date" type="date" value="{{ $schedule->due_date->toDateString() }}" required aria-label="Payment due date" class="rounded-lg border-slate-300 text-sm">
                                <select name="course_id" aria-label="Course" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($schedule->course_id === $course->id)>{{ $course->code }}</option>@endforeach</select>
                                <select name="year_level" aria-label="Year level" class="rounded-lg border-slate-300 text-sm"><option value="">All year levels</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected($schedule->year_level === $year)>{{ $year }}</option>@endforeach</select>
                                <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 sm:col-span-2 lg:col-span-5">
                                    <span>{{ $schedule->schoolTerm->display_name }} · {{ $schedule->due_date->format('M j, Y') }} · {{ $schedule->bills_count }} matching bill(s) · {{ $schedule->course?->code ?? 'All courses' }} · {{ $schedule->year_level ?? 'All year levels' }}</span>
                                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-slate-50">Save changes</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.billing-schedules.destroy', $schedule) }}" class="mt-2 text-right" onsubmit="return confirm('Delete this payment due date? Existing bills will keep their saved due dates.')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Delete due date</button>
                            </form>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500">No payment due dates have been added yet.</p>
                    @endforelse
                </div>
            </section>
            <div class="grid gap-6 lg:grid-cols-2">
                <section id="courses" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Courses</h3>
                    <form method="POST" action="{{ route('admin.courses.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
                        <input name="code" value="{{ old('code') }}" placeholder="Course code" required class="rounded-lg border-slate-300 text-sm">
                        <input name="name" value="{{ old('name') }}" placeholder="Course name" required class="rounded-lg border-slate-300 text-sm">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add course</button>
                    </form>
                    <div class="mt-5 space-y-3">@forelse ($courses as $course)
                        <div class="rounded-xl border border-slate-100 p-3">
                            <form method="POST" action="{{ route('admin.courses.update', $course) }}" class="grid gap-2 sm:grid-cols-[130px_1fr_auto]">@csrf @method('PUT')
                                <input name="code" value="{{ $course->code }}" aria-label="Course code" required class="rounded-lg border-slate-300 text-sm">
                                <input name="name" value="{{ $course->name }}" aria-label="Course name" required class="rounded-lg border-slate-300 text-sm">
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" class="mt-2 text-right" onsubmit="return confirm('Delete this course? Courses assigned to students cannot be deleted.')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Delete course</button>
                            </form>
                        </div>
                    @empty<p class="mt-5 text-sm text-slate-500">No courses added.</p>@endforelse</div>
                </section>

                <section id="subjects" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><h3 class="text-lg font-semibold text-slate-900">Subjects</h3><p class="mt-1 text-sm text-slate-500">Set course, subject details, units, and optional year/semester targeting.</p></div>
                        <a href="{{ route('admin.enrollments.index') }}" class="rounded-lg border border-indigo-200 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Manage student enrollment</a>
                    </div>
                    <div class="mt-4 rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 text-sm text-sky-900">Subject units are for academic enrollment only. No fee is added for a subject; billable charges are managed separately in Billing setup.</div>
                    <form method="POST" action="{{ route('admin.subjects.store') }}" class="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5" x-data="{ code: @js(old('code', '')), name: @js(old('name', '')), units: @js(old('units', '')) }">@csrf
                        <div class="mb-4"><h4 class="font-semibold text-slate-900">Add a subject</h4><p class="mt-1 text-xs text-slate-500">Add the subject details and choose where it applies. No subject fee is required.</p></div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <label class="block text-sm font-medium text-slate-700">Course<select name="course_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Select course</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->code }} — {{ $course->name }}</option>@endforeach</select></label>
                            <label class="block text-sm font-medium text-slate-700">Subject code<input name="code" x-model="code" value="{{ old('code') }}" placeholder="e.g. CS101" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                            <label class="block text-sm font-medium text-slate-700">Subject name<input name="name" x-model="name" value="{{ old('name') }}" placeholder="e.g. Introduction to Computing" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                            <label class="block text-sm font-medium text-slate-700">Units<input name="units" x-model="units" type="number" min="0.5" max="99.9" step="0.1" value="{{ old('units') }}" placeholder="e.g. 3.0" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                            <label class="block text-sm font-medium text-slate-700">Year level <span class="font-normal text-slate-400">· optional</span><select name="year_level" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Any year level</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level') === $year)>{{ $year }}</option>@endforeach</select></label>
                            <label class="block text-sm font-medium text-slate-700">Semester <span class="font-normal text-slate-400">· optional</span><select name="semester" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">Any semester</option>@foreach($semesters as $semester)<option value="{{ $semester }}" @selected(old('semester') === $semester)>{{ $semester }}</option>@endforeach</select></label>
                        </div>
                        <div x-cloak x-show="code || name || units" x-transition class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-100 bg-white px-3 py-2.5 text-sm">
                            <span class="font-semibold text-slate-800"><span x-show="code" x-text="code"></span><span x-show="code && name"> · </span><span x-text="name || 'Subject name preview'"></span></span>
                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700" x-text="units ? `${units} units` : 'Units not set'"></span>
                        </div>
                        <div class="mt-4 flex justify-end"><button class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"><span aria-hidden="true">+</span> Add subject</button></div>
                    </form>
                    <form method="GET" action="{{ route('admin.catalog.index') }}#subjects" class="mt-5 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <input name="subject_search" value="{{ $subjectFilters['subject_search'] ?? '' }}" placeholder="Search subjects by code or name" class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <select name="subject_course_id" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(($subjectFilters['subject_course_id'] ?? '') == $course->id)>{{ $course->code }} — {{ $course->name }}</option>@endforeach</select>
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Apply filters</button>
                    </form>
                    <div class="mt-4 space-y-3" aria-live="polite">
                        @forelse($subjects as $subject)
                            <div class="rounded-xl border border-slate-200 bg-white p-4 transition hover:border-indigo-200 hover:shadow-sm">
                                <form method="POST" action="{{ route('admin.subjects.update', $subject) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf @method('PUT')
                                    <select name="course_id" required class="rounded-lg border-slate-300 text-sm">@foreach($courses as $course)<option value="{{ $course->id }}" @selected($subject->course_id === $course->id)>{{ $course->code }}</option>@endforeach</select>
                                    <input name="code" value="{{ $subject->code }}" aria-label="Subject code" required class="rounded-lg border-slate-300 text-sm">
                                    <input name="name" value="{{ $subject->name }}" aria-label="Subject name" required class="rounded-lg border-slate-300 text-sm">
                                    <input name="units" type="number" min="0.5" step="0.1" value="{{ $subject->units }}" aria-label="Units" required class="rounded-lg border-slate-300 text-sm">
                                    <select name="year_level" aria-label="Year level" class="rounded-lg border-slate-300 text-sm"><option value="">Any year</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected($subject->year_level === $year)>{{ $year }}</option>@endforeach</select>
                                    <select name="semester" aria-label="Semester" class="rounded-lg border-slate-300 text-sm"><option value="">Any semester</option>@foreach($semesters as $semester)<option value="{{ $semester }}" @selected($subject->semester === $semester)>{{ $semester }}</option>@endforeach</select>
                                    <div class="flex items-center justify-between gap-2"><span class="text-xs font-medium text-slate-500">{{ $subject->units }} units · No fee</span><button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Save</button></div>
                                </form>
                                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500"><span>{{ $subject->course->code }} · {{ $subject->enrollments_count }} enrollments</span><form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" onsubmit="return confirm('Delete this subject? Enrolled or billed subjects cannot be deleted.')">@csrf @method('DELETE')<button class="font-semibold text-rose-600 hover:underline">Delete subject</button></form></div>
                            </div>
                        @empty<p class="text-sm text-slate-500">No subjects match these filters.</p>@endforelse
                    </div>
                </section>

                <section id="school-terms" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">School year and semester</h3>
                    <form method="POST" action="{{ route('admin.terms.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
                        <input name="school_year" value="{{ old('school_year') }}" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}" required class="rounded-lg border-slate-300 text-sm">
                        <select name="semester" required class="rounded-lg border-slate-300 text-sm"><option value="">Select semester</option>@foreach ($semesters as $semester)<option value="{{ $semester }}" @selected(old('semester') === $semester)>{{ $semester }}</option>@endforeach</select>
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add term</button>
                    </form>
                    <div class="mt-5 space-y-3">@forelse ($terms as $term)
                        <div class="rounded-xl border border-slate-100 p-3">
                            <form method="POST" action="{{ route('admin.terms.update', $term) }}" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">@csrf @method('PUT')
                                <input name="school_year" value="{{ $term->school_year }}" aria-label="School year for {{ $term->display_name }}" pattern="[0-9]{4}-[0-9]{4}" required class="rounded-lg border-slate-300 text-sm">
                                <select name="semester" aria-label="Semester for {{ $term->display_name }}" required class="rounded-lg border-slate-300 text-sm">@foreach ($semesters as $semester)<option value="{{ $semester }}" @selected($term->semester === $semester)>{{ $semester }}</option>@endforeach</select>
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.terms.destroy', $term) }}" class="mt-2 text-right" onsubmit="return confirm('Delete this school term? Terms linked to bills or promissory balances cannot be deleted.')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Delete term</button>
                            </form>
                        </div>
                    @empty<p class="mt-5 text-sm text-slate-500">No school terms added.</p>@endforelse</div>
                </section>

                <section id="fees" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Fee catalog</h3>
                    <form method="POST" action="{{ route('admin.fees.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
                        <input name="name" value="{{ old('name') }}" placeholder="Fee name" required class="rounded-lg border-slate-300 text-sm">
                        <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" placeholder="Amount (₱)" required class="rounded-lg border-slate-300 text-sm">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add fee</button>
                    </form>
                    <div class="mt-5 space-y-3">@forelse ($fees as $fee)
                        <div class="rounded-xl border border-slate-100 p-3">
                            <form method="POST" action="{{ route('admin.fees.update', $fee) }}" class="grid gap-2 sm:grid-cols-[1fr_130px_120px_auto]">@csrf @method('PUT')
                                <input name="name" value="{{ $fee->name }}" required class="rounded-lg border-slate-300 text-sm">
                                <input name="amount" type="number" min="0.01" step="0.01" value="{{ $fee->amount }}" required class="rounded-lg border-slate-300 text-sm">
                                <select name="is_active" class="rounded-lg border-slate-300 text-sm"><option value="1" @selected($fee->is_active)>Active</option><option value="0" @selected(!$fee->is_active)>Inactive</option></select>
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.fees.destroy', $fee) }}" class="mt-2 text-right" onsubmit="return confirm('Delete this fee? Fees already used on invoices cannot be deleted; mark them inactive instead.')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Delete fee</button>
                            </form>
                        </div>
                    @empty<p class="text-sm text-slate-500">No fees added.</p>@endforelse</div>
                </section>

                <section id="scholarships" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Fixed-amount scholarships</h3>
                    <p class="mt-1 text-sm text-slate-500">Discounts are stored in pesos and applied once per bill.</p>
                    <form method="POST" action="{{ route('admin.scholarships.store') }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
                        <input name="name" value="{{ old('name') }}" placeholder="Scholarship name" required class="rounded-lg border-slate-300 text-sm">
                        <input name="discount_amount" type="number" min="0.01" step="0.01" value="{{ old('discount_amount') }}" placeholder="Discount (₱)" required class="rounded-lg border-slate-300 text-sm">
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Add scholarship</button>
                    </form>
                    <div class="mt-5 space-y-3">@forelse ($scholarships as $scholarship)
                        <div class="rounded-xl border border-slate-100 p-3">
                            <form method="POST" action="{{ route('admin.scholarships.update', $scholarship) }}" class="grid gap-2 sm:grid-cols-[1fr_130px_120px_auto]">@csrf @method('PUT')
                                <input name="name" value="{{ $scholarship->name }}" required class="rounded-lg border-slate-300 text-sm">
                                <input name="discount_amount" type="number" min="0.01" step="0.01" value="{{ $scholarship->discount_amount }}" required class="rounded-lg border-slate-300 text-sm">
                                <select name="is_active" class="rounded-lg border-slate-300 text-sm"><option value="1" @selected($scholarship->is_active)>Active</option><option value="0" @selected(!$scholarship->is_active)>Inactive</option></select>
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.scholarships.destroy', $scholarship) }}" class="mt-2 text-right" onsubmit="return confirm('Delete this scholarship? Assigned scholarships cannot be deleted; mark inactive instead.')">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-rose-600 hover:underline">Delete scholarship</button>
                            </form>
                        </div>
                    @empty<p class="text-sm text-slate-500">No scholarships added.</p>@endforelse</div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
