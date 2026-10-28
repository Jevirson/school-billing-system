<x-app-layout>
    <x-slot name="header"><h2 class="text-2xl font-semibold text-slate-900">{{ $bill ? 'Edit student bill' : 'Create student bill' }}</h2></x-slot>
    <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6"><div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @if ($errors->any())<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ $bill ? route('admin.bills.update', $bill) : route('admin.bills.store') }}" class="space-y-6" x-data="{
            students: @js($students->map(fn ($student) => [
                'id' => (string) $student->id,
                'name' => $student->invoice_name,
                'studentNumber' => $student->student_number,
                'course' => $student->course ? $student->course->code.' — '.$student->course->name : 'Course not assigned',
                'scholarshipName' => $student->scholarshipAssignments()->pluck('name')->implode(', '),
                'scholarshipCents' => $student->scholarshipAssignments()->sum(fn ($scholarship) => (int) round((float) $scholarship->discount_amount * 100)),
                'searchText' => strtolower($student->invoice_name.' '.$student->student_number.' '.($student->course?->code ?? '').' '.($student->course?->name ?? '')),
            ])->values()),
            fees: @js($fees->map(fn ($fee) => ['id' => (int) $fee->id, 'amountCents' => (int) round((float) $fee->amount * 100)])),
            enrollments: @js($enrollments->map(fn ($enrollment) => [
                'id' => (int) $enrollment->id,
                'studentId' => (string) $enrollment->student_profile_id,
                'termId' => (string) $enrollment->school_term_id,
                'description' => $enrollment->subject->code.' — '.$enrollment->subject->name,
                'units' => $enrollment->units,
            ])->values()),
            selectedFeeIds: @js(array_map('intval', old('fee_ids', $bill?->items->pluck('fee_type_id')->filter()->all() ?? []))),
            selectedEnrollmentIds: @js(array_map('intval', old('enrollment_ids', []))),
            query: '',
            selectedStudentId: @js((string) old('student_profile_id', $bill?->student_profile_id ?? '')),
            selectedTermId: @js((string) old('school_term_id', $bill?->school_term_id ?? '')),
            open: false,
            get filteredStudents() {
                const query = this.query.trim().toLowerCase();
                return this.students.filter(student => !query || student.searchText.includes(query));
            },
            get selectedStudent() {
                return this.students.find(student => student.id === this.selectedStudentId);
            },
            get totalFeeCents() {
                return this.fees.filter(fee => this.selectedFeeIds.includes(fee.id)).reduce((total, fee) => total + fee.amountCents, 0);
            },
            get applicableEnrollments() {
                return this.enrollments.filter(enrollment => enrollment.studentId === this.selectedStudentId && enrollment.termId === this.selectedTermId);
            },
            get totalChargesCents() {
                return this.totalFeeCents;
            },
            get scholarshipDiscountCents() {
                return Math.min(this.selectedStudent?.scholarshipCents ?? 0, this.totalChargesCents);
            },
            formatPeso(cents) {
                return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(cents / 100);
            },
            chooseStudent(student) {
                this.selectedStudentId = student.id;
                this.query = `${student.studentNumber} — ${student.name}`;
                this.open = false;
            },
            init() {
                if (this.selectedStudent) {
                    this.query = `${this.selectedStudent.studentNumber} — ${this.selectedStudent.name}`;
                }
            }
        }">@csrf @if ($bill) @method('PUT') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="relative" @click.outside="open = false">
                    <label for="student-search" class="block text-sm font-medium text-slate-700">Student</label>
                    <input id="student-search" type="search" autocomplete="off" placeholder="Search by name, student ID, or course" x-model="query" @focus="open = true" @input="selectedStudentId = ''; open = true" @keydown.escape="open = false" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    <input type="hidden" name="student_profile_id" :value="selectedStudentId">
                    <div x-cloak x-show="open" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg" role="listbox" aria-label="Student search results">
                        <template x-for="student in filteredStudents" :key="student.id">
                            <button type="button" @click="chooseStudent(student)" class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-indigo-50 focus:bg-indigo-50 focus:outline-none" role="option">
                                <span><span class="block text-sm font-medium text-slate-900" x-text="student.name"></span><span class="mt-0.5 block text-xs text-slate-500" x-text="student.studentNumber"></span></span>
                                <span class="shrink-0 text-right text-xs font-medium text-indigo-700" x-text="student.course"></span>
                            </button>
                        </template>
                        <p x-show="filteredStudents.length === 0" class="px-4 py-3 text-sm text-slate-500">No students match your search.</p>
                    </div>
                    <p x-show="selectedStudent" class="mt-1.5 text-xs text-slate-600">
                        Course: <span class="font-semibold text-slate-800" x-text="selectedStudent?.course"></span>
                    </p>
                    @error('student_profile_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @if ($students->isEmpty())<p class="mt-1 text-xs text-amber-800">Add a student account before creating a bill.</p>@endif
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">School year and semester<select name="school_term_id" x-model="selectedTermId" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"><option value="">Select term</option>@foreach ($terms as $term)<option value="{{ $term->id }}" @selected(old('school_term_id', $bill?->school_term_id) == $term->id)>{{ $term->display_name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-medium text-slate-700">Billing period<select name="billing_period" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">@foreach($billingPeriods as $period)<option value="{{ $period }}" @selected(old('billing_period', $bill?->billing_period ?? 'Full Term') === $period)>{{ $period }}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">The due date is applied automatically from the matching billing schedule, if configured.</span></label>
                </div>
            </div>
            <fieldset><legend class="text-sm font-semibold text-slate-800">Applicable fees</legend><div class="mt-3 grid gap-3 sm:grid-cols-2">@forelse ($fees as $fee)<label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 p-4 text-sm transition hover:border-indigo-300 hover:bg-indigo-50/40"><span class="flex items-center gap-3"><input type="checkbox" name="fee_ids[]" value="{{ $fee->id }}" x-model.number="selectedFeeIds" class="rounded border-slate-300 text-indigo-600"><span>{{ $fee->name }}</span></span><span class="font-semibold">₱{{ number_format((float) $fee->amount, 2) }}</span></label>@empty<p class="text-sm text-amber-700">Add an active fee in Billing setup before creating a bill.</p>@endforelse</div></fieldset>
                <fieldset class="rounded-xl border border-slate-200 p-4"><legend class="px-1 text-sm font-semibold text-slate-800">Enrolled subjects for selected student and term</legend><p class="mb-2 text-xs text-slate-500">Select subjects to link them to this bill. Subjects are not charged separately.</p><template x-for="enrollment in applicableEnrollments" :key="enrollment.id"><label class="flex cursor-pointer items-center justify-between gap-4 border-b py-3 text-sm"><span><input type="checkbox" name="enrollment_ids[]" :value="enrollment.id" x-model.number="selectedEnrollmentIds" class="mr-2 rounded border-slate-300 text-indigo-600"><span x-text="enrollment.description"></span><span class="ml-2 text-xs text-slate-500" x-text="enrollment.units + ' units'"></span></span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">No extra fee</span></label></template><p x-show="applicableEnrollments.length === 0" class="py-2 text-sm text-slate-500">No unbilled enrollment subjects match the selected student and term.</p>@error('enrollment_ids')<p class="text-xs text-red-600">{{ $message }}</p>@enderror</fieldset>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 text-sm text-slate-700">
                <p class="font-semibold">Scholarship discount is applied from the student's current assignment.</p>
                <p class="mt-1 text-xs leading-5 text-slate-600" x-show="selectedStudent?.scholarshipName">Assigned scholarship(s): <strong x-text="selectedStudent?.scholarshipName"></strong> · <span x-text="formatPeso(scholarshipDiscountCents)"></span> total discount applied.</p>
                <p class="mt-1 text-xs leading-5 text-slate-600" x-show="!selectedStudent?.scholarshipName">Select a student and applicable fees to preview totals.</p>
                <dl class="mt-4 space-y-2 border-t border-indigo-100 pt-3"><div class="flex justify-between"><dt>Selected catalog fees</dt><dd class="font-semibold" x-text="formatPeso(totalChargesCents)"></dd></div><div class="flex justify-between"><dt>Scholarship discount</dt><dd class="font-semibold" x-text="'− ' + formatPeso(scholarshipDiscountCents)"></dd></div><div class="flex justify-between border-t border-indigo-100 pt-2 text-base font-bold"><dt>Net amount due</dt><dd x-text="formatPeso(totalChargesCents - scholarshipDiscountCents)"></dd></div></dl>
            </div>
            <label class="block text-sm font-medium text-slate-700">Billing date<input name="billing_date" type="date" value="{{ old('billing_date', $bill?->billing_date->toDateString() ?? now()->toDateString()) }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="block text-sm font-medium text-slate-700">Notes (optional)<textarea name="notes" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">{{ old('notes', $bill?->notes) }}</textarea></label>
            <div class="flex justify-end gap-3"><a href="{{ $bill ? route('admin.bills.show', $bill) : route('admin.bills.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">{{ $bill ? 'Save changes' : 'Create bill' }}</button></div>
        </form>
    </div></div>
</x-app-layout>
