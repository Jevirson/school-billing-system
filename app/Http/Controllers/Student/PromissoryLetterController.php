<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\PromissoryLetter;
use App\Models\PromissoryLetterEvent;
use App\Models\StudentProfile;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromissoryLetterController extends Controller
{
    public function index(): View
    {
        $student = request()->user()->studentProfile;

        abort_unless($student instanceof StudentProfile, Response::HTTP_FORBIDDEN);

        return view('student.promissory-letters.index', [
            'letters' => $student->promissoryLetters()
                ->with(['bill', 'nextSchoolTerm', 'extension.sourceBill', 'extension.destinationBill'])
                ->latest('submitted_at')
                ->get(),
            'carryForwards' => BillExtension::with(['sourceBill.schoolTerm', 'sourceBill.payments', 'nextSchoolTerm', 'destinationBill', 'promissoryLetter'])
                ->whereHas('sourceBill', fn ($query) => $query->where('student_profile_id', $student->id))
                ->orderByDesc('created_at')
                ->get(),
            'bills' => $student->bills()->with(['schoolTerm', 'payments', 'promissoryLetters', 'extensions', 'extensionsReceived'])->latest('billing_date')->get(),
        ]);
    }

    public function create(Bill $bill): View
    {
        $student = $this->studentForBill($bill);
        $this->ensureRequestable($bill);
        $bill->load(['schoolTerm', 'studentProfile.course']);

        return view('student.promissory-letters.create', [
            'bill' => $bill,
            'student' => $student->load('course'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bill_id' => ['required', 'integer', 'exists:bills,id'],
            'reason' => ['required', 'string', 'min:10', 'max:3000'],
            'requested_amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'commitment_date' => ['required', 'date', 'after:today'],
        ]);

        $student = $request->user()->studentProfile;
        abort_unless($student instanceof StudentProfile, Response::HTTP_FORBIDDEN);

        $letter = DB::transaction(function () use ($data, $student, $request): PromissoryLetter {
            $bill = $student->bills()->whereKey($data['bill_id'])->with(['schoolTerm', 'studentProfile.course'])->lockForUpdate()->first();
            abort_unless($bill instanceof Bill, Response::HTTP_FORBIDDEN);
            $this->ensureRequestable($bill);

            $requestedAmount = $data['requested_amount'] ?? $bill->remaining_balance;
            $requestedCents = Money::toCents($requestedAmount);
            $remainingCents = Money::toCents($bill->remaining_balance);
            if ($requestedCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'The requested deferral cannot exceed the current outstanding balance.',
                ]);
            }

            $letter = PromissoryLetter::create([
                'student_profile_id' => $student->id,
                'bill_id' => $bill->id,
                'student_name' => $student->invoice_name,
                'student_number' => $student->student_number,
                'course_name' => $student->course?->name,
                'year_level' => $student->year_level,
                'school_year' => $bill->schoolTerm->school_year,
                'semester' => $bill->schoolTerm->semester,
                'billing_period' => $bill->billing_period,
                'invoice_number' => $bill->invoice_number,
                'outstanding_amount' => $bill->remaining_balance,
                'requested_amount' => Money::fromCents($requestedCents),
                'reason' => $data['reason'],
                'commitment_date' => $data['commitment_date'],
                'contact_information' => $request->user()->email,
                'submitted_at' => now(),
                'status' => 'Pending',
            ]);

            PromissoryLetterEvent::create([
                'promissory_letter_id' => $letter->id,
                'changed_by' => $request->user()->id,
                'status' => 'Pending',
                'note' => 'Promissory letter submitted.',
            ]);

            return $letter;
        });

        return redirect()->route('student.promissory-letters.show', $letter)->with('status', 'Your promissory letter was submitted for review.');
    }

    public function show(PromissoryLetter $letter): View
    {
        abort_unless($letter->student_profile_id === request()->user()->studentProfile?->id, Response::HTTP_FORBIDDEN);

        $letter->load(['bill', 'nextSchoolTerm', 'extension.sourceBill', 'events.changedBy']);
        request()->user()->unreadNotifications
            ->filter(fn ($notification): bool => ($notification->data['promissory_letter_id'] ?? null) === $letter->id)
            ->each(fn ($notification) => $notification->markAsRead());

        return view('student.promissory-letters.show', ['letter' => $letter]);
    }

    private function studentForBill(Bill $bill): StudentProfile
    {
        $student = request()->user()->studentProfile;
        abort_unless($student instanceof StudentProfile && $bill->student_profile_id === $student->id, Response::HTTP_FORBIDDEN);

        return $student;
    }

    private function ensureRequestable(Bill $bill): void
    {
        if ((float) $bill->remaining_balance <= 0) {
            throw ValidationException::withMessages(['bill' => 'A promissory letter is only available for a bill with an outstanding balance.']);
        }

        if ($bill->extensions()->exists() || $bill->promissoryLetters()->whereIn('status', ['Pending', 'Approved', 'Overdue'])->exists()) {
            throw ValidationException::withMessages(['bill' => 'A request or approved extension already exists for this bill.']);
        }
    }
}
