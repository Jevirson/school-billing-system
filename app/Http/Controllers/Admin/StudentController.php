<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\Course;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\PaymentRevision;
use App\Models\PromissoryLetter;
use App\Models\Scholarship;
use App\Models\StudentProfile;
use App\Models\SubjectWithdrawalRefund;
use App\Models\SubjectWithdrawalRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(): View
    {
        return view('admin.students.index', [
            'students' => StudentProfile::with(['user', 'course', 'scholarship', 'scholarships'])
                ->withCount('bills')
                ->orderBy('last_name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.students.create', [
            'courses' => Course::orderBy('name')->get(),
            'scholarships' => Scholarship::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedStudent($request);

        $studentNumber = DB::transaction(function () use ($data): string {
            $studentNumber = $this->nextStudentNumber();
            $user = new User([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'username' => strtolower($data['username']),
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill([
                'role' => User::ROLE_STUDENT,
                'email_verified_at' => now(),
            ])->save();

            $student = $user->studentProfile()->create([
                'student_number' => $studentNumber,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'course_id' => $data['course_id'] ?? null,
                'scholarship_id' => $data['scholarship_ids'][0] ?? null,
                'year_level' => $data['year_level'] ?? null,
            ]);
            $student->scholarships()->sync($data['scholarship_ids']);

            return $studentNumber;
        });

        return redirect()->route('admin.students.index')->with(
            'status',
            'Student account created. School ID: '.$studentNumber.'. Login username: '.strtolower($data['username']).'. Give the student the password you set.'
        );
    }

    public function edit(StudentProfile $student): View
    {
        $student->load(['user', 'course', 'scholarship', 'scholarships']);
        $assignedScholarshipIds = $student->scholarshipAssignments()->pluck('id');
        $scholarships = Scholarship::where('is_active', true)
            ->orWhereIn('id', $assignedScholarshipIds)
            ->orderBy('name')
            ->get();

        return view('admin.students.edit', [
            'student' => $student,
            'courses' => Course::orderBy('name')->get(),
            'scholarships' => $scholarships,
        ]);
    }

    public function update(Request $request, StudentProfile $student): RedirectResponse
    {
        $data = $this->validatedStudent($request, $student);

        DB::transaction(function () use ($data, $student): void {
            $student->update([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'course_id' => $data['course_id'] ?? null,
                'scholarship_id' => $data['scholarship_ids'][0] ?? null,
                'year_level' => $data['year_level'] ?? null,
            ]);
            $student->scholarships()->sync($data['scholarship_ids']);

            $student->user->update([
                'name' => trim($data['first_name'].' '.$data['last_name']),
            ]);
        });

        return redirect()->route('admin.students.index')->with('status', 'Student profile updated.');
    }

    public function destroy(Request $request, StudentProfile $student): RedirectResponse
    {
        $request->validate([
            'confirm_student_number' => ['required', 'string', Rule::in([$student->student_number])],
        ], [
            'confirm_student_number.in' => 'Enter the student ID exactly as shown to confirm deletion.',
        ]);

        $studentName = $student->invoice_name;
        $studentNumber = $student->student_number;
        $userId = $student->user_id;

        DB::transaction(function () use ($student): void {
            $lockedStudent = StudentProfile::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $billIds = $lockedStudent->bills()->pluck('id');
            $letterIds = PromissoryLetter::where('student_profile_id', $lockedStudent->id)->pluck('id');
            $paymentIds = Payment::whereIn('bill_id', $billIds)->pluck('id');

            BillExtension::whereIn('source_bill_id', $billIds)
                ->orWhereIn('promissory_letter_id', $letterIds)
                ->delete();

            PaymentRevision::whereIn('payment_id', $paymentIds)->delete();
            PaymentRequest::where('student_profile_id', $lockedStudent->id)->delete();
            $withdrawalRequestIds = SubjectWithdrawalRequest::where('student_profile_id', $lockedStudent->id)->pluck('id');
            SubjectWithdrawalRefund::whereIn('subject_withdrawal_request_id', $withdrawalRequestIds)->delete();
            SubjectWithdrawalRequest::whereIn('id', $withdrawalRequestIds)->delete();
            Payment::whereIn('id', $paymentIds)->delete();
            PromissoryLetter::whereIn('id', $letterIds)->delete();
            Bill::whereIn('id', $billIds)->delete();
            $lockedStudent->enrollments()->delete();

            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $lockedStudent->user_id)
                ->delete();

            $lockedStudent->user()->delete();
        });

        return redirect()->route('admin.students.index')->with(
            'status',
            "Student {$studentName} ({$studentNumber}), their login account, invoices, payments, and promissory records were permanently deleted."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedStudent(Request $request, ?StudentProfile $student = null): array
    {
        if (! $request->has('scholarship_ids') && $request->filled('scholarship_id')) {
            $request->merge(['scholarship_ids' => [$request->input('scholarship_id')]]);
        }

        if ($request->has('scholarship_ids')) {
            $request->merge([
                'scholarship_ids' => array_values(array_filter(
                    (array) $request->input('scholarship_ids'),
                    fn ($id): bool => $id !== null && $id !== ''
                )),
            ]);
        }

        if (! $student) {
            $request->merge([
                'username' => Str::lower(trim((string) $request->input('username'))),
                'email' => Str::lower(trim((string) $request->input('email'))),
            ]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'course_id' => ['required', 'exists:courses,id'],
            'year_level' => ['nullable', 'in:1st Year,2nd Year,3rd Year,4th Year'],
            'is_scholar' => ['nullable', 'boolean'],
            'scholarship_ids' => ['nullable', 'array', 'max:2'],
            'scholarship_ids.*' => ['required', 'integer', 'distinct', 'exists:scholarships,id'],
        ]);

        $scholarshipIds = $data['scholarship_ids'] ?? [];
        if ($request->boolean('is_scholar') && count($scholarshipIds) === 0) {
            throw ValidationException::withMessages(['scholarship_ids' => 'Choose at least one scholarship.']);
        }

        if ($scholarshipIds !== [] && Scholarship::whereIn('id', $scholarshipIds)->where('is_active', true)->count() !== count($scholarshipIds)) {
            throw ValidationException::withMessages(['scholarship_ids' => 'Choose only active scholarships.']);
        }

        if (! $student) {
            $account = $request->validate([
                'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            return [...$data, ...$account, 'scholarship_ids' => $scholarshipIds];
        }

        return [...$data, 'scholarship_ids' => $scholarshipIds];
    }

    private function nextStudentNumber(): string
    {
        $sequence = DB::table('student_id_sequences')
            ->where('id', 1)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw new RuntimeException('The student ID sequence has not been initialized. Run the database migrations.');
        }

        $number = (int) $sequence->next_number;

        do {
            $studentNumber = '1630000'.$number;
            $number++;
        } while (StudentProfile::where('student_number', $studentNumber)->exists());

        DB::table('student_id_sequences')
            ->where('id', 1)
            ->update(['next_number' => $number]);

        return $studentNumber;
    }
}
