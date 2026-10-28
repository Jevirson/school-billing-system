<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SubjectWithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $student = $request->user()->studentProfile;

        return view('student.enrollment.index', [
            'student' => $student?->load('course'),
            'enrollments' => $student
                ? Enrollment::with(['subject', 'schoolTerm', 'withdrawalRequest.refund'])
                    ->where('student_profile_id', $student->id)
                    ->latest('school_term_id')
                    ->latest('id')
                    ->get()
                : collect(),
        ]);
    }

    public function requestWithdrawal(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $student = $request->user()->studentProfile;
        abort_unless($student && $enrollment->student_profile_id === $student->id, 404);

        DB::transaction(function () use ($enrollment, $student, $request): void {
            $lockedEnrollment = Enrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if ($lockedEnrollment->withdrawn_at !== null) {
                throw ValidationException::withMessages(['withdrawal' => 'This subject has already been withdrawn.']);
            }

            if (SubjectWithdrawalRequest::where('enrollment_id', $lockedEnrollment->id)
                ->where('status', 'Pending')
                ->exists()) {
                throw ValidationException::withMessages(['withdrawal' => 'A withdrawal request for this subject is already awaiting review.']);
            }

            SubjectWithdrawalRequest::create([
                'enrollment_id' => $lockedEnrollment->id,
                'student_profile_id' => $student->id,
                'student_note' => $request->validate([
                    'student_note' => ['nullable', 'string', 'max:1000'],
                ])['student_note'] ?? null,
            ]);
        });

        return back()->with('status', 'Your withdrawal request was sent to the school for review.');
    }
}
