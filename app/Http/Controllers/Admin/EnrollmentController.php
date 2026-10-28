<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SchoolTerm;
use App\Models\StudentProfile;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'school_term_id' => ['nullable', 'integer', 'exists:school_terms,id'],
        ]);

        $termId = $filters['school_term_id'] ?? null;
        $search = $filters['search'] ?? null;
        $matchingEnrollments = fn ($query) => $query
            ->whereNull('withdrawn_at')
            ->when($termId, fn ($query) => $query->where('school_term_id', $termId));

        $students = StudentProfile::query()
            ->with('course')
            ->whereHas('enrollments', $matchingEnrollments)
            ->when($search, function ($query, string $search) use ($termId): void {
                $query->where(function ($query) use ($search, $termId): void {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('middle_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhereHas('enrollments', function ($enrollments) use ($search, $termId): void {
                            $enrollments->whereNull('withdrawn_at')
                                ->when($termId, fn ($query) => $query->where('school_term_id', $termId))
                                ->whereHas('subject', fn ($subject) => $subject
                                    ->where('code', 'like', '%'.$search.'%')
                                    ->orWhere('name', 'like', '%'.$search.'%'));
                        });
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.enrollments.index', [
            'students' => $students,
            'terms' => SchoolTerm::orderByDesc('school_year')->orderBy('semester')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(StudentProfile $studentProfile): View
    {
        $studentProfile->load('course');

        return view('admin.enrollments.show', [
            'student' => $studentProfile,
            'enrollments' => $studentProfile->enrollments()
                ->with(['subject', 'schoolTerm', 'bill', 'withdrawalRequest.refund'])
                ->latest('school_term_id')
                ->latest('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.enrollments.create', [
            'students' => StudentProfile::with('course')->orderBy('last_name')->orderBy('first_name')->get(),
            'terms' => SchoolTerm::orderByDesc('school_year')->orderBy('semester')->get(),
            'subjects' => Subject::with('course')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'school_term_id' => ['required', 'integer', 'exists:school_terms,id'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['required', 'integer', 'distinct', 'exists:subjects,id'],
        ]);

        DB::transaction(function () use ($data): void {
            $student = StudentProfile::whereKey($data['student_profile_id'])->lockForUpdate()->firstOrFail();
            $term = SchoolTerm::findOrFail($data['school_term_id']);
            $subjects = Subject::whereIn('id', $data['subject_ids'])
                ->where('course_id', $student->course_id)
                ->get();

            if ($subjects->count() !== count($data['subject_ids'])) {
                throw ValidationException::withMessages([
                    'subject_ids' => 'Choose subjects assigned to the student’s course only.',
                ]);
            }

            foreach ($subjects as $subject) {
                if (($subject->year_level && $subject->year_level !== $student->year_level)
                    || ($subject->semester && $subject->semester !== $term->semester)) {
                    throw ValidationException::withMessages([
                        'subject_ids' => "{$subject->code} is not available for this student’s year level or school term.",
                    ]);
                }

                if (Enrollment::where('student_profile_id', $student->id)
                    ->where('subject_id', $subject->id)
                    ->where('school_term_id', $term->id)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'subject_ids' => "{$subject->code} is already enrolled for this student and term.",
                    ]);
                }
            }

            foreach ($subjects as $subject) {
                Enrollment::create([
                    'student_profile_id' => $student->id,
                    'subject_id' => $subject->id,
                    'school_term_id' => $term->id,
                    'units' => $subject->units,
                ]);
            }
        });

        return redirect()->route('admin.enrollments.index')->with('status', 'Student enrollment saved.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        if ($enrollment->bill_id) {
            return back()->with('error', 'This enrollment is attached to a bill and cannot be removed.');
        }

        $enrollment->delete();

        return back()->with('status', 'Enrollment removed.');
    }
}
