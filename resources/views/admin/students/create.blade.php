<x-app-layout>
    <x-slot name="header"><h2 class="text-2xl font-semibold text-slate-900">Add student</h2></x-slot>
    <div class="min-h-screen bg-slate-50 px-4 py-8 sm:px-6"><div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        @if ($errors->any())<div class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('admin.students.store') }}" class="grid gap-5 sm:grid-cols-2" x-data="{ enabled: @js((bool) old('is_scholar', count((array) old('scholarship_ids', [])))) }">@csrf
            @foreach (['first_name' => 'First name', 'middle_name' => 'Middle name (optional)', 'last_name' => 'Last name'] as $field => $label)
                <label class="block text-sm font-medium text-slate-700">{{ $label }}<input name="{{ $field }}" value="{{ old($field) }}" @required(!in_array($field, ['middle_name', 'year_level'])) class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            @endforeach
            <div class="text-sm font-medium text-slate-700">School ID
                <p class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-slate-600">Assigned automatically starting at 16300001</p>
            </div>
            <label class="block text-sm font-medium text-slate-700">Year level
                <select name="year_level" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Select year level (optional)</option>
                    @foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $yearLevel)
                        <option value="{{ $yearLevel }}" @selected(old('year_level') === $yearLevel)>{{ $yearLevel }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-slate-700">Course <span class="text-red-600">*</span>
                <select name="course_id" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Select a course</option>
                    @foreach ($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->code }} — {{ $course->name }}</option>@endforeach
                </select>
                @if ($courses->isEmpty())
                    <span class="mt-1 block text-xs font-normal text-amber-800">No courses yet. <a class="font-semibold underline" href="{{ route('admin.catalog.index') }}#courses">Add a course in Billing setup</a>, then return here.</span>
                @endif
            </label>
            <fieldset class="sm:col-span-2 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                <legend class="px-1 text-sm font-semibold text-slate-800">Scholarship</legend>
                <p class="mb-3 text-xs text-slate-600">To create a scholarship, open <a class="font-semibold text-indigo-700 underline" href="{{ route('admin.catalog.index') }}#scholarships">Billing setup → Scholarships</a>.</p>
                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-800">
                    <input type="checkbox" name="is_scholar" value="1" x-model="enabled" @checked(old('is_scholar')) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Is this student a scholar?
                </label>
                <div x-cloak x-show="enabled" class="mt-4">
                    <label for="scholarship_ids_0" class="block text-sm font-medium text-slate-700">First scholarship and fixed discount</label>
                    <select id="scholarship_ids_0" name="scholarship_ids[]" :disabled="!enabled" :required="enabled" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">Select scholarship</option>
                        @foreach ($scholarships as $scholarship)
                            <option value="{{ $scholarship->id }}" @selected((int) old('scholarship_ids.0') === $scholarship->id)>{{ $scholarship->name }} — ₱{{ number_format((float) $scholarship->discount_amount, 2) }}</option>
                        @endforeach
                    </select>
                    <label for="scholarship_ids_1" class="mt-3 block text-sm font-medium text-slate-700">Second scholarship (optional)</label>
                    <select id="scholarship_ids_1" name="scholarship_ids[]" :disabled="!enabled" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm">
                        <option value="">No second scholarship</option>
                        @foreach ($scholarships as $scholarship)
                            <option value="{{ $scholarship->id }}" @selected((int) old('scholarship_ids.1') === $scholarship->id)>{{ $scholarship->name }} — ₱{{ number_format((float) $scholarship->discount_amount, 2) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Choose up to two different scholarships. Their fixed discounts are added together and capped at the bill total.</p>
                    @if ($scholarships->isEmpty())<p class="mt-2 text-xs text-amber-800">No active scholarships are available. Add one in Billing setup first.</p>@endif
                </div>
            </fieldset>
            <div class="sm:col-span-2 border-t border-slate-100 pt-5"><h3 class="font-semibold text-slate-900">Student login</h3></div>
            <label class="block text-sm font-medium text-slate-700">Username<input name="username" value="{{ old('username') }}" required autocomplete="off" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="block text-sm font-medium text-slate-700">Email<input name="email" type="email" value="{{ old('email') }}" required class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="block text-sm font-medium text-slate-700">Password<input name="password" type="password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            <label class="block text-sm font-medium text-slate-700">Confirm password<input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm"></label>
            <div class="sm:col-span-2 flex justify-end gap-3"><a href="{{ route('admin.students.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Create student account</button></div>
        </form>
    </div></div>
</x-app-layout>
