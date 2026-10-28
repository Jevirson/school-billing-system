<x-app-layout>
    <x-slot name="header"><h2 class="text-2xl font-semibold text-slate-900">Payment due dates</h2></x-slot>
    <main class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6"><div class="mx-auto max-w-6xl space-y-6">
        @if(session('status'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="rounded-2xl border bg-white p-6 shadow-sm"><h3 class="font-semibold text-slate-900">Add a payment due date</h3><p class="mt-1 text-sm text-slate-500">Payment due dates update matching existing bills and apply automatically to future bills. Exam dates are managed separately in Billing setup.</p>
            <form method="POST" action="{{ route('admin.billing-schedules.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf
                <select name="school_term_id" required class="rounded-lg border-slate-300 text-sm"><option value="">School term</option>@foreach($terms as $term)<option value="{{ $term->id }}" @selected(old('school_term_id') == $term->id)>{{ $term->display_name }}</option>@endforeach</select>
                <select name="billing_period" required class="rounded-lg border-slate-300 text-sm"><option value="">Payment period</option>@foreach($periods as $period)<option value="{{ $period }}" @selected(old('billing_period') === $period)>{{ $period }}</option>@endforeach</select>
                <label class="sr-only" for="new-exam-date">Payment due date</label><input id="new-exam-date" name="due_date" type="date" value="{{ old('due_date') }}" required aria-label="Payment due date" class="rounded-lg border-slate-300 text-sm">
                <select name="course_id" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->code }}</option>@endforeach</select>
                <select name="year_level" class="rounded-lg border-slate-300 text-sm"><option value="">All year levels</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected(old('year_level') === $year)>{{ $year }}</option>@endforeach</select>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white sm:col-span-2 lg:col-span-5">Create payment due date and notify eligible students</button>
            </form>
        </section>
        <section class="space-y-4">
            @forelse($schedules as $schedule)
                <article class="rounded-2xl border bg-white p-5 shadow-sm">
                    <form method="POST" action="{{ route('admin.billing-schedules.update', $schedule) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@csrf @method('PUT')
                        <select name="school_term_id" required class="rounded-lg border-slate-300 text-sm">@foreach($terms as $term)<option value="{{ $term->id }}" @selected($schedule->school_term_id === $term->id)>{{ $term->display_name }}</option>@endforeach</select>
                        <select name="billing_period" required class="rounded-lg border-slate-300 text-sm" aria-label="Payment period">@foreach($periods as $period)<option value="{{ $period }}" @selected($schedule->billing_period === $period)>{{ $period }}</option>@endforeach</select>
                        <label class="sr-only" for="exam-date-{{ $schedule->id }}">Payment due date</label><input id="exam-date-{{ $schedule->id }}" name="due_date" type="date" value="{{ $schedule->due_date->toDateString() }}" required aria-label="Payment due date" class="rounded-lg border-slate-300 text-sm">
                        <select name="course_id" class="rounded-lg border-slate-300 text-sm"><option value="">All courses</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected($schedule->course_id === $course->id)>{{ $course->code }}</option>@endforeach</select>
                        <select name="year_level" class="rounded-lg border-slate-300 text-sm"><option value="">All year levels</option>@foreach(['1st Year','2nd Year','3rd Year','4th Year'] as $year)<option value="{{ $year }}" @selected($schedule->year_level === $year)>{{ $year }}</option>@endforeach</select>
                        <div class="flex justify-end sm:col-span-2 lg:col-span-5"><button class="rounded-lg border px-3 py-2 text-sm font-semibold text-indigo-700">Save changes</button></div>
                    </form>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500"><span>{{ $schedule->due_date->format('M j, Y') }} · {{ $schedule->bills_count }} matching bill(s) · {{ $schedule->course?->code ?? 'All courses' }} · {{ $schedule->year_level ?? 'All year levels' }}</span><form method="POST" action="{{ route('admin.billing-schedules.destroy', $schedule) }}" onsubmit="return confirm('Delete this payment due date? Existing bills will keep their saved due dates.')">@csrf @method('DELETE')<button class="font-semibold text-rose-600">Delete due date</button></form></div>
                </article>
            @empty
                <p class="rounded-xl border bg-white p-6 text-sm text-slate-500">No payment due dates have been created.</p>
            @endforelse
        </section>
    </div></main>
</x-app-layout>
