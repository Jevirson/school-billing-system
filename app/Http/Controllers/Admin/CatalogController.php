<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSchedule;
use App\Models\Course;
use App\Models\ExamSchedule;
use App\Models\FeeType;
use App\Models\PromissoryLetter;
use App\Models\Scholarship;
use App\Models\SchoolTerm;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'subject_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'subject_search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('admin.catalog.index', [
            'courses' => Course::orderBy('code')->get(),
            'terms' => SchoolTerm::orderByDesc('school_year')->orderBy('semester')->get(),
            'billingSchedules' => BillingSchedule::with(['schoolTerm', 'course'])->withCount('bills')->latest()->get(),
            'examSchedules' => ExamSchedule::with(['schoolTerm', 'course'])->latest()->get(),
            'examPeriods' => ExamSchedule::PERIODS,
            'billingPeriods' => BillingSchedule::PERIODS,
            'fees' => FeeType::orderBy('name')->get(),
            'scholarships' => Scholarship::orderBy('name')->get(),
            'subjects' => Subject::with('course')
                ->withCount('enrollments')
                ->when($filters['subject_course_id'] ?? null, fn ($query, $id) => $query->where('course_id', $id))
                ->when($filters['subject_search'] ?? null, function ($query, string $search): void {
                    $query->where(fn ($query) => $query->where('code', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'));
                })
                ->orderBy('course_id')->orderBy('code')->get(),
            'subjectFilters' => $filters,
            'semesters' => SchoolTerm::SEMESTERS,
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('subjects', 'code')->where('course_id', $request->input('course_id'))],
            'name' => ['required', 'string', 'max:255'],
            'units' => ['required', 'numeric', 'decimal:0,1', 'min:0.5', 'max:99.9'],
            'year_level' => ['nullable', 'string', 'max:30'],
            'semester' => ['nullable', Rule::in(SchoolTerm::SEMESTERS)],
        ]);

        Subject::create($data);

        return back()->with('status', 'Subject added.');
    }

    public function updateSubject(Request $request, Subject $subject): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('subjects', 'code')->where('course_id', $request->input('course_id'))->ignore($subject->id)],
            'name' => ['required', 'string', 'max:255'],
            'units' => ['required', 'numeric', 'decimal:0,1', 'min:0.5', 'max:99.9'],
            'year_level' => ['nullable', 'string', 'max:30'],
            'semester' => ['nullable', Rule::in(SchoolTerm::SEMESTERS)],
        ]);

        $subject->update($data);

        return back()->with('status', 'Subject updated.');
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        if ($subject->enrollments()->exists()) {
            return back()->with('error', "Cannot delete {$subject->code}; it has enrollment or billing history.");
        }

        $subject->delete();

        return back()->with('status', "Subject {$subject->code} deleted.");
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:courses,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Course::create($data);

        return back()->with('status', 'Course added.');
    }

    public function updateCourse(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('courses', 'code')->ignore($course->id)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $course->update($data);

        return back()->with('status', 'Course updated.');
    }

    public function destroyCourse(Course $course): RedirectResponse
    {
        if ($course->students()->exists() || $course->subjects()->exists()) {
            return back()->with('error', "Cannot delete {$course->code}; it is assigned to students or subjects.");
        }

        $course->delete();

        return back()->with('status', "Course {$course->code} deleted.");
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        return $this->saveTerm($request);
    }

    public function updateTerm(Request $request, SchoolTerm $term): RedirectResponse
    {
        if ($this->termIsInUse($term)) {
            return back()->withErrors([
                'school_term_id' => 'This term is already used by bills or carry-forwards and can no longer be changed.',
            ]);
        }

        return $this->saveTerm($request, $term);
    }

    public function destroyTerm(SchoolTerm $term): RedirectResponse
    {
        if ($this->termIsInUse($term)) {
            return back()->with('error', "Cannot delete {$term->display_name}; it is used by bills or carry-forwards.");
        }

        $term->delete();

        return back()->with('status', "School term {$term->display_name} deleted.");
    }

    private function termIsInUse(SchoolTerm $term): bool
    {
        return $term->bills()->exists()
            || $term->enrollments()->exists()
            || $term->billingSchedules()->exists()
            || $term->examSchedules()->exists()
            || $term->extensions()->exists()
            || PromissoryLetter::where('next_school_term_id', $term->id)->exists();
    }

    private function saveTerm(Request $request, ?SchoolTerm $term = null): RedirectResponse
    {
        $data = $request->validate([
            'school_year' => [
                'required',
                'regex:/^\d{4}-\d{4}$/',
                Rule::unique('school_terms', 'school_year')
                    ->where('semester', $request->input('semester'))
                    ->ignore($term?->id),
            ],
            'semester' => ['required', Rule::in(SchoolTerm::SEMESTERS)],
        ]);

        [$startYear, $endYear] = array_map('intval', explode('-', $data['school_year']));

        if ($endYear !== $startYear + 1) {
            return back()->withErrors(['school_year' => 'Enter consecutive years, for example 2026-2027.'])->withInput();
        }

        if ($term) {
            $term->update($data);

            return back()->with('status', 'School term updated.');
        }

        SchoolTerm::create($data);

        return back()->with('status', 'School year and semester added.');
    }

    public function storeFee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:fee_types,name'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        FeeType::create($data);

        return back()->with('status', 'Fee added.');
    }

    public function updateFee(Request $request, FeeType $fee): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('fee_types', 'name')->ignore($fee->id)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
        ]);

        $fee->update($data);

        return back()->with('status', 'Fee updated.');
    }

    public function destroyFee(FeeType $fee): RedirectResponse
    {
        if ($fee->billItems()->exists()) {
            return back()->with('error', "Cannot delete {$fee->name}; it is referenced by an invoice. You can mark it inactive instead.");
        }

        $fee->delete();

        return back()->with('status', "Fee {$fee->name} deleted.");
    }

    public function storeScholarship(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:scholarships,name'],
            'discount_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ]);

        Scholarship::create($data);

        return back()->with('status', 'Fixed-amount scholarship added.');
    }

    public function updateScholarship(Request $request, Scholarship $scholarship): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('scholarships', 'name')->ignore($scholarship->id)],
            'discount_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
        ]);

        $scholarship->update($data);

        return back()->with('status', 'Scholarship updated.');
    }

    public function destroyScholarship(Scholarship $scholarship): RedirectResponse
    {
        if ($scholarship->studentProfiles()->exists()
            || $scholarship->assignedStudentProfiles()->exists()
            || $scholarship->bills()->exists()) {
            return back()->with('error', "Cannot delete {$scholarship->name}; it is assigned to students or referenced by bills. You can mark it inactive instead.");
        }

        $scholarship->delete();

        return back()->with('status', "Scholarship {$scholarship->name} deleted.");
    }
}
