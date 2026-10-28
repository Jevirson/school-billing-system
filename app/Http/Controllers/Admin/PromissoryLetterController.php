<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\BillingSchedule;
use App\Models\PromissoryLetter;
use App\Models\PromissoryLetterEvent;
use App\Models\SchoolTerm;
use App\Models\StudentProfile;
use App\Notifications\PromissoryLetterStatusNotification;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PromissoryLetterController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(PromissoryLetter::STATUSES)],
            'school_year' => ['nullable', 'string', 'max:9'],
            'semester' => ['nullable', Rule::in(SchoolTerm::SEMESTERS)],
            'student' => ['nullable', 'string', 'max:100'],
        ]);

        $letters = PromissoryLetter::with(['studentProfile', 'bill', 'nextSchoolTerm'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['school_year'] ?? null, fn ($query, $year) => $query->where('school_year', $year))
            ->when($filters['semester'] ?? null, fn ($query, $semester) => $query->where('semester', $semester))
            ->when($filters['student'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('student_name', 'like', '%'.$search.'%')
                        ->orWhere('student_number', 'like', '%'.$search.'%');
                });
            })
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.promissory-letters.index', [
            'letters' => $letters,
            'statuses' => PromissoryLetter::STATUSES,
            'semesters' => SchoolTerm::SEMESTERS,
            'schoolYears' => SchoolTerm::query()->distinct()->orderByDesc('school_year')->pluck('school_year'),
        ]);
    }

    public function show(PromissoryLetter $letter): View
    {
        $letter->load(['studentProfile.course', 'bill.schoolTerm', 'bill.payments', 'nextSchoolTerm', 'extension.sourceBill.payments', 'extension.nextSchoolTerm', 'extension.destinationBill', 'reviewer', 'events.changedBy']);
        $nextBillingPeriod = BillingSchedule::nextPeriod($letter->bill->billing_period);
        $nextBillingSchedule = $nextBillingPeriod === null
            ? null
            : BillingSchedule::resolveFor(
                $letter->studentProfile,
                $letter->bill->school_term_id,
                $nextBillingPeriod
            );

        return view('admin.promissory-letters.show', [
            'letter' => $letter,
            'nextBillingPeriod' => $nextBillingPeriod,
            'nextBillingSchedule' => $nextBillingSchedule,
        ]);
    }

    public function decide(Request $request, PromissoryLetter $letter): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'admin_note' => ['nullable', 'string', 'max:3000'],
            'deferred_amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
        ]);

        $updatedLetter = DB::transaction(function () use ($data, $letter, $request): PromissoryLetter {
            $lockedLetter = PromissoryLetter::whereKey($letter->id)->lockForUpdate()->firstOrFail();

            if ($lockedLetter->status !== 'Pending') {
                throw ValidationException::withMessages(['decision' => 'Only pending requests can be reviewed.']);
            }

            $bill = Bill::whereKey($lockedLetter->bill_id)->lockForUpdate()->firstOrFail();
            $balance = $bill->remaining_balance;
            $now = now();

            if (Money::toCents($balance) <= 0) {
                $lockedLetter->update([
                    'status' => 'Fulfilled',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => $now,
                    'admin_note' => 'The bill was fully paid before review.',
                ]);
                PromissoryLetterEvent::create([
                    'promissory_letter_id' => $lockedLetter->id,
                    'changed_by' => $request->user()->id,
                    'status' => 'Fulfilled',
                    'note' => 'The bill was fully paid before review; no balance was carried forward.',
                ]);

                return $lockedLetter->fresh(['studentProfile.user']);
            }

            if ($data['decision'] === 'reject') {
                if (trim((string) ($data['admin_note'] ?? '')) === '') {
                    throw ValidationException::withMessages(['admin_note' => 'Enter a reason when rejecting a request.']);
                }

                $lockedLetter->update([
                    'status' => 'Rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => $now,
                    'admin_note' => $data['admin_note'],
                ]);
                PromissoryLetterEvent::create([
                    'promissory_letter_id' => $lockedLetter->id,
                    'changed_by' => $request->user()->id,
                    'status' => 'Rejected',
                    'note' => $data['admin_note'],
                ]);

                return $lockedLetter->fresh(['studentProfile.user']);
            }

            $student = StudentProfile::findOrFail($lockedLetter->student_profile_id);
            $nextBillingPeriod = BillingSchedule::nextPeriod($bill->billing_period);
            if ($nextBillingPeriod === null) {
                throw ValidationException::withMessages([
                    'decision' => 'There is no following payment period for this bill.',
                ]);
            }

            if (BillExtension::where('source_bill_id', $lockedLetter->bill_id)->exists()) {
                throw ValidationException::withMessages(['decision' => 'This bill already has a carry-forward balance.']);
            }

            $remainingCents = Money::toCents((string) $balance);
            $requestedCents = Money::toCents($lockedLetter->requested_amount);
            if ($requestedCents <= 0) {
                $requestedCents = Money::toCents($lockedLetter->outstanding_amount);
            }
            if ($requestedCents <= 0) {
                $requestedCents = $remainingCents;
            }
            $deferredCents = Money::toCents($data['deferred_amount'] ?? Money::fromCents(min($remainingCents, $requestedCents)));
            if ($deferredCents <= 0) {
                throw ValidationException::withMessages(['deferred_amount' => 'Enter an amount greater than zero to defer.']);
            }
            if ($deferredCents > min($remainingCents, $requestedCents)) {
                throw ValidationException::withMessages([
                    'deferred_amount' => 'The deferred amount cannot exceed the remaining bill balance or the amount requested by the student.',
                ]);
            }

            $lockedLetter->update([
                'status' => 'Approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => $now,
                'admin_note' => $data['admin_note'] ?? null,
                'next_school_term_id' => $bill->school_term_id,
            ]);
            $schedule = BillingSchedule::resolveFor($student, $bill->school_term_id, $nextBillingPeriod);
            $destinationBill = Bill::query()
                ->where('student_profile_id', $student->id)
                ->where('school_term_id', $bill->school_term_id)
                ->where('billing_period', $nextBillingPeriod)
                ->first();
            if (! $destinationBill) {
                $destinationBill = Bill::create([
                    'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'student_profile_id' => $student->id,
                    'school_term_id' => $bill->school_term_id,
                    'billing_schedule_id' => $schedule?->id,
                    'billing_period' => $nextBillingPeriod,
                    'total_fees' => '0.00',
                    'scholarship_discount' => '0.00',
                    'net_amount_due' => '0.00',
                    'billing_date' => today(),
                    'due_date' => $schedule?->due_date,
                    'notes' => 'Carry-forward balance from invoice '.$bill->invoice_number.'. Add this period’s fees to this invoice when billing.',
                ]);
            } elseif ($schedule !== null) {
                $destinationBill->update([
                    'billing_schedule_id' => $schedule->id,
                    'due_date' => $schedule->due_date,
                ]);
            }
            BillExtension::create([
                'promissory_letter_id' => $lockedLetter->id,
                'source_bill_id' => $lockedLetter->bill_id,
                'destination_bill_id' => $destinationBill?->id,
                'next_school_term_id' => $bill->school_term_id,
                'next_billing_period' => $nextBillingPeriod,
                'amount_at_approval' => number_format($deferredCents / 100, 2, '.', ''),
                'approved_until' => $lockedLetter->commitment_date,
            ]);
            PromissoryLetterEvent::create([
                'promissory_letter_id' => $lockedLetter->id,
                'changed_by' => $request->user()->id,
                'status' => 'Approved',
                'note' => trim(($data['admin_note'] ?? '').' ₱'.number_format($deferredCents / 100, 2).' moved from '.$bill->billing_period.' to '.$nextBillingPeriod.' in '.$bill->schoolTerm->display_name.'.'),
            ]);

            return $lockedLetter->fresh(['studentProfile.user']);
        });

        $updatedLetter->studentProfile->user->notify(new PromissoryLetterStatusNotification($updatedLetter));

        return redirect()->route('admin.promissory-letters.show', $updatedLetter)->with('status', 'Request status updated to '.$updatedLetter->status.'.');
    }

}
