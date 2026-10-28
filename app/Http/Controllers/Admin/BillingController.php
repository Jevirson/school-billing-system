<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\BillingSchedule;
use App\Models\FeeType;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentRevision;
use App\Models\SchoolTerm;
use App\Models\StudentProfile;
use App\Notifications\PaymentRecordedNotification;
use App\Notifications\BillDueDateNotification;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'school_term_id' => ['nullable', 'integer', 'exists:school_terms,id'],
            'status' => ['nullable', 'in:Unpaid,Partially Paid,Fully Paid,Overdue'],
            'billing_period' => ['nullable', Rule::in(BillingSchedule::PERIODS)],
        ]);

        $bills = Bill::query()
            ->with(['studentProfile.course', 'schoolTerm', 'payments'])
            ->withCount(['payments', 'promissoryLetters', 'extensions'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhereHas('studentProfile', function ($query) use ($search): void {
                            $query->where('student_number', 'like', '%'.$search.'%')
                                ->orWhere('first_name', 'like', '%'.$search.'%')
                                ->orWhere('middle_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhereHas('course', function ($query) use ($search): void {
                                    $query->where('code', 'like', '%'.$search.'%')
                                        ->orWhere('name', 'like', '%'.$search.'%');
                                });
                        });
                });
            })
            ->when($filters['school_term_id'] ?? null, fn ($query, int $termId) => $query->where('school_term_id', $termId))
            ->when($filters['billing_period'] ?? null, fn ($query, string $period) => $query->where('billing_period', $period))
            ->when($filters['status'] ?? null, function ($query, string $status): void {
                $paidAmount = '(SELECT COALESCE(SUM(payments.amount), 0) FROM payments WHERE payments.bill_id = bills.id)';
                $totalDue = '(bills.net_amount_due'
                    .' - (SELECT COALESCE(SUM(source_extensions.amount_at_approval), 0) FROM bill_extensions AS source_extensions WHERE source_extensions.source_bill_id = bills.id)'
                    .' + (SELECT COALESCE(SUM(destination_extensions.amount_at_approval), 0) FROM bill_extensions AS destination_extensions WHERE destination_extensions.destination_bill_id = bills.id))';

                match ($status) {
                    'Unpaid' => $query->whereRaw($paidAmount.' <= 0')->whereRaw($totalDue.' > 0'),
                    'Fully Paid' => $query->whereRaw($paidAmount.' >= '.$totalDue),
                    'Partially Paid' => $query->whereRaw($paidAmount.' > 0 AND '.$paidAmount.' < '.$totalDue),
                    'Overdue' => $query->whereNotNull('due_date')->whereDate('due_date', '<', today())->whereRaw($paidAmount.' < '.$totalDue),
                };
            })
            ->latest('billing_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.bills.index', [
            'bills' => $bills,
            'terms' => SchoolTerm::orderByDesc('school_year')->orderBy('semester')->get(),
            'billingPeriods' => BillingSchedule::PERIODS,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.bills.create', [
            'students' => StudentProfile::with(['user', 'course', 'scholarship', 'scholarships'])->orderBy('last_name')->get(),
            'terms' => SchoolTerm::orderByDesc('school_year')->orderBy('semester')->get(),
            'fees' => FeeType::where('is_active', true)->orderBy('name')->get(),
            'billingPeriods' => BillingSchedule::PERIODS,
            'enrollments' => Enrollment::with(['studentProfile', 'subject', 'schoolTerm'])
                ->whereNull('bill_id')
                ->whereNull('withdrawn_at')
                ->whereDoesntHave('withdrawalRequest', fn ($query) => $query->where('status', 'Pending'))
                ->orderBy('school_term_id')
                ->get(),
            'bill' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'school_term_id' => ['required', 'exists:school_terms,id'],
            'billing_period' => ['sometimes', 'required', Rule::in(BillingSchedule::PERIODS)],
            'fee_ids' => ['nullable', 'array'],
            'fee_ids.*' => ['required', 'integer', 'distinct', 'exists:fee_types,id'],
            'enrollment_ids' => ['nullable', 'array'],
            'enrollment_ids.*' => ['required', 'integer', 'distinct', 'exists:enrollments,id'],
            'billing_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $feeIds = $data['fee_ids'] ?? [];
        $enrollmentIds = $data['enrollment_ids'] ?? [];
        $data['billing_period'] ??= 'Full Term';

        if ($feeIds === [] && $enrollmentIds === []) {
            throw ValidationException::withMessages(['fee_ids' => 'Select at least one fee or an enrolled subject.']);
        }

        $fees = FeeType::whereIn('id', $feeIds)->where('is_active', true)->get();
        if ($fees->count() !== count(array_unique($feeIds))) {
            throw ValidationException::withMessages(['fee_ids' => 'Choose only active fees from the list.']);
        }

        $bill = DB::transaction(function () use ($data, $fees, $enrollmentIds): Bill {
            $term = SchoolTerm::findOrFail($data['school_term_id']);
            $student = StudentProfile::with(['scholarship', 'scholarships'])
                ->whereKey($data['student_profile_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $existingBill = Bill::where('student_profile_id', $data['student_profile_id'])
                ->where('school_term_id', $term->id)
                ->where('billing_period', $data['billing_period'])
                ->lockForUpdate()
                ->first();

            $carryForwardPlaceholder = $existingBill
                && Money::toCents($existingBill->total_fees) === 0
                && $existingBill->items()->doesntExist()
                && $existingBill->extensionsReceived()->exists()
                && ! $existingBill->payments()->exists()
                && ! $existingBill->promissoryLetters()->exists()
                && ! $existingBill->extensions()->exists();

            if ($existingBill && ! $carryForwardPlaceholder) {
                throw ValidationException::withMessages([
                    'school_term_id' => 'A bill already exists for this student and school term.',
                ]);
            }

            $enrollments = Enrollment::with('subject')
                ->whereIn('id', $enrollmentIds)
                ->lockForUpdate()
                ->get();
            if ($enrollments->count() !== count($enrollmentIds)
                || $enrollments->contains(fn (Enrollment $enrollment): bool => $enrollment->student_profile_id !== $student->id
                    || $enrollment->school_term_id !== $term->id
                    || $enrollment->bill_id !== null
                    || $enrollment->withdrawn_at !== null
                    || $enrollment->withdrawalRequest()->where('status', 'Pending')->exists())) {
                throw ValidationException::withMessages([
                    'enrollment_ids' => 'Choose only unbilled subjects enrolled for this student and school term.',
                ]);
            }

            $totalCents = $fees->sum(fn (FeeType $fee): int => Money::toCents($fee->amount));
            $scholarships = $student->scholarshipAssignments();
            if ($scholarships->contains(fn ($assignedScholarship): bool => ! $assignedScholarship->is_active)) {
                throw ValidationException::withMessages([
                    'student_profile_id' => 'This student is assigned to an inactive scholarship. Update the assignment before creating the bill.',
                ]);
            }
            $discountCents = min(
                $scholarships->sum(fn ($assignedScholarship): int => Money::toCents($assignedScholarship->discount_amount)),
                $totalCents
            );
            $scholarship = $scholarships->first();
            $scholarshipName = $scholarships->pluck('name')->implode(', ') ?: null;
            $schedule = BillingSchedule::resolveFor($student, $term->id, $data['billing_period']);

            if ($carryForwardPlaceholder) {
                $bill = $existingBill->fresh();
                $bill->update([
                    'billing_schedule_id' => $schedule?->id,
                    'scholarship_id' => $scholarship?->id,
                    'scholarship_name' => $scholarshipName,
                    'total_fees' => Money::fromCents($totalCents),
                    'scholarship_discount' => Money::fromCents($discountCents),
                    'net_amount_due' => Money::fromCents($totalCents - $discountCents),
                    'billing_date' => $data['billing_date'],
                    'due_date' => $schedule?->due_date,
                    'notes' => $data['notes'] ?? $bill->notes,
                ]);
            } else {
                $bill = Bill::create([
                    'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'student_profile_id' => $data['student_profile_id'],
                    'school_term_id' => $term->id,
                    'billing_schedule_id' => $schedule?->id,
                    'billing_period' => $data['billing_period'],
                    'scholarship_id' => $scholarship?->id,
                    'scholarship_name' => $scholarshipName,
                    'total_fees' => Money::fromCents($totalCents),
                    'scholarship_discount' => Money::fromCents($discountCents),
                    'net_amount_due' => Money::fromCents($totalCents - $discountCents),
                    'billing_date' => $data['billing_date'],
                    'due_date' => $schedule?->due_date,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            foreach ($fees as $fee) {
                $amount = Money::toCents($fee->amount);
                $bill->items()->create([
                    'fee_type_id' => $fee->id,
                    'description' => $fee->name,
                    'quantity' => 1,
                    'unit_amount' => Money::fromCents($amount),
                    'line_total' => Money::fromCents($amount),
                ]);
            }

            foreach ($enrollments as $enrollment) {
                $enrollment->update(['bill_id' => $bill->id]);
            }

            BillExtension::query()
                ->whereNull('destination_bill_id')
                ->where('next_school_term_id', $term->id)
                ->where('next_billing_period', $data['billing_period'])
                ->whereHas('sourceBill', fn ($query) => $query->where('student_profile_id', $student->id))
                ->lockForUpdate()
                ->update(['destination_bill_id' => $bill->id]);

            return $bill;
        });

        if ($bill->billing_schedule_id) {
            $bill->load(['studentProfile.user', 'schoolTerm', 'payments']);
            $bill->studentProfile->user->notify(new BillDueDateNotification($bill));
        }

        return redirect()->route('admin.bills.show', $bill)->with('status', 'Bill created successfully.');
    }

    public function edit(Bill $bill): View|RedirectResponse
    {
        if ($this->billHasFinancialHistory($bill) || $bill->enrollments()->exists()) {
            return redirect()->route('admin.bills.show', $bill)->with('error', 'This bill has payments or promissory history and can no longer be edited.');
        }

        $bill->load('items.feeType');
        $fees = FeeType::where(function ($query) use ($bill): void {
            $query->where('is_active', true)
                ->orWhereIn('id', $bill->items->pluck('fee_type_id')->filter());
        })
            ->orderBy('name')
            ->get();

        return view('admin.bills.create', [
            'students' => StudentProfile::with(['user', 'course', 'scholarship', 'scholarships'])->orderBy('last_name')->get(),
            'terms' => SchoolTerm::orderByDesc('school_year')->orderBy('semester')->get(),
            'fees' => $fees,
            'billingPeriods' => BillingSchedule::PERIODS,
            'enrollments' => Enrollment::with(['studentProfile', 'subject', 'schoolTerm'])
                ->whereNull('bill_id')
                ->whereNull('withdrawn_at')
                ->whereDoesntHave('withdrawalRequest', fn ($query) => $query->where('status', 'Pending'))
                ->orderBy('school_term_id')
                ->get(),
            'bill' => $bill,
        ]);
    }

    public function update(Request $request, Bill $bill): RedirectResponse
    {
        $data = $this->validatedBill($request);
        $data['billing_period'] ??= $bill->billing_period ?? 'Full Term';
        $fees = $this->billFees($data['fee_ids'], $bill);
        $totalCents = $fees->sum(fn (FeeType $fee): int => Money::toCents($fee->amount));
        $student = StudentProfile::with(['scholarship', 'scholarships'])->findOrFail($data['student_profile_id']);
        $scholarships = $student->scholarshipAssignments();
        $scholarship = $scholarships->first();
        $scholarshipName = $scholarships->pluck('name')->implode(', ') ?: null;

        if ($scholarships->contains(fn ($assignedScholarship): bool => ! $assignedScholarship->is_active)) {
            throw ValidationException::withMessages([
                'student_profile_id' => 'This student is assigned to an inactive scholarship. Update the assignment before editing this bill.',
            ]);
        }

        $discountCents = min(
            $scholarships->sum(fn ($assignedScholarship): int => Money::toCents($assignedScholarship->discount_amount)),
            $totalCents
        );

        DB::transaction(function () use ($bill, $data, $fees, $student, $scholarship, $scholarshipName, $totalCents, $discountCents): void {
            $lockedBill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($this->billHasFinancialHistory($lockedBill)) {
                throw ValidationException::withMessages([
                    'bill' => 'This bill has payments or promissory history and can no longer be edited.',
                ]);
            }
            if (Bill::where('student_profile_id', $student->id)
                ->where('school_term_id', $data['school_term_id'])
                ->where('billing_period', $data['billing_period'])
                ->where('id', '!=', $lockedBill->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'school_term_id' => 'A bill already exists for this student and school term.',
                ]);
            }

            $schedule = BillingSchedule::resolveFor($student, (int) $data['school_term_id'], $data['billing_period']);
            $lockedBill->update([
                'student_profile_id' => $student->id,
                'school_term_id' => $data['school_term_id'],
                'billing_schedule_id' => $schedule?->id,
                'billing_period' => $data['billing_period'],
                'scholarship_id' => $scholarship?->id,
                'scholarship_name' => $scholarshipName,
                'total_fees' => Money::fromCents($totalCents),
                'scholarship_discount' => Money::fromCents($discountCents),
                'net_amount_due' => Money::fromCents($totalCents - $discountCents),
                'billing_date' => $data['billing_date'],
                'due_date' => $schedule?->due_date,
                'notes' => $data['notes'] ?? null,
            ]);
            $lockedBill->items()->delete();

            foreach ($fees as $fee) {
                $amount = Money::toCents($fee->amount);
                $lockedBill->items()->create([
                    'fee_type_id' => $fee->id,
                    'description' => $fee->name,
                    'quantity' => 1,
                    'unit_amount' => Money::fromCents($amount),
                    'line_total' => Money::fromCents($amount),
                ]);
            }
        });

        return redirect()->route('admin.bills.show', $bill)->with('status', 'Bill updated successfully.');
    }

    public function destroy(Bill $bill): RedirectResponse
    {
        $deletedInvoice = $bill->invoice_number;

        $deleted = DB::transaction(function () use ($bill): bool {
            $lockedBill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($this->billHasFinancialHistory($lockedBill)) {
                return false;
            }

            $lockedBill->delete();

            return true;
        });

        if (! $deleted) {
            return redirect()->route('admin.bills.show', $bill)->with(
                'error',
                'This bill has payments or promissory history and cannot be deleted.'
            );
        }

        return redirect()->route('admin.bills.index')->with('status', "Bill {$deletedInvoice} deleted.");
    }

    private function validatedBill(Request $request): array
    {
        return $request->validate([
            'student_profile_id' => ['required', 'exists:student_profiles,id'],
            'school_term_id' => ['required', 'exists:school_terms,id'],
            'billing_period' => ['sometimes', 'required', Rule::in(BillingSchedule::PERIODS)],
            'fee_ids' => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['required', 'integer', 'distinct', 'exists:fee_types,id'],
            'billing_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function billFees(array $feeIds, ?Bill $bill = null)
    {
        $allowedFees = FeeType::query()->where(function ($query) use ($bill): void {
            $query->where('is_active', true);
            if ($bill) {
                $query->orWhereIn('id', $bill->items()->pluck('fee_type_id')->filter());
            }
        });
        $fees = $allowedFees->whereIn('id', $feeIds)->get();

        if ($fees->count() !== count(array_unique(array_map('intval', $feeIds)))) {
            throw ValidationException::withMessages(['fee_ids' => 'Choose only active fees or fees already on this bill.']);
        }

        return $fees;
    }

    private function billHasFinancialHistory(Bill $bill): bool
    {
        return $bill->payments()->exists()
            || $bill->promissoryLetters()->exists()
            || $bill->extensions()->exists()
            || $bill->extensionsReceived()->exists()
            || $bill->enrollments()->whereNotNull('withdrawn_at')->exists();
    }

    public function show(Bill $bill): View
    {
        $bill->load(['studentProfile.course', 'schoolTerm', 'items', 'extensions.nextSchoolTerm', 'extensions.destinationBill', 'extensionsReceived.sourceBill.schoolTerm', 'extensionsReceived.promissoryLetter', 'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id')]);
        $bill->loadCount(['payments', 'promissoryLetters', 'extensions', 'extensionsReceived']);

        return view('admin.bills.show', ['bill' => $bill]);
    }

    public function invoice(Bill $bill): View
    {
        $bill->load(['studentProfile.course', 'schoolTerm', 'items', 'extensions.nextSchoolTerm', 'extensionsReceived.sourceBill', 'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id')]);

        return view('invoices.show', ['bill' => $bill]);
    }

    public function statement(Bill $bill): View
    {
        $bill->load([
            'studentProfile.course',
            'schoolTerm',
            'items',
            'extensions.nextSchoolTerm',
            'extensions.destinationBill',
            'extensionsReceived.sourceBill',
            'payments' => fn ($query) => $query->with('recordedBy')->orderByDesc('paid_at')->orderByDesc('id'),
        ]);

        return view('statements.show', ['bill' => $bill]);
    }

    public function editPayment(Payment $payment): View
    {
        $payment->load(['bill.studentProfile', 'bill.schoolTerm', 'revisions.editedBy']);

        return view('admin.payments.edit', ['payment' => $payment]);
    }

    public function recordPayment(Request $request, Bill $bill): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:payments,reference'],
            'method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = DB::transaction(function () use ($bill, $data, $request): Payment {
            $lockedBill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $remainingCents = Money::toCents($lockedBill->remaining_balance);
            $paymentCents = Money::toCents($data['amount']);

            if ($paymentCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot exceed the remaining balance of ₱'.number_format(max(0, $remainingCents) / 100, 2).'.',
                ]);
            }

            return Payment::create([
                'bill_id' => $lockedBill->id,
                'recorded_by' => $request->user()->id,
                'amount' => Money::fromCents($paymentCents),
                'balance_after' => Money::fromCents(max(0, $remainingCents - $paymentCents)),
                'paid_at' => $data['paid_at'],
                'reference' => ($data['reference'] ?? null) ?: null,
                'method' => $data['method'],
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $payment->load('bill.studentProfile.user');
        $payment->bill->studentProfile->user->notify(new PaymentRecordedNotification($payment));

        return redirect()->route('admin.bills.show', $bill)->with('status', 'Payment recorded successfully. Print the receipt from the payment history.');
    }

    public function updatePayment(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('payments', 'reference')->ignore($payment->id)],
            'method' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($payment, $data, $request): void {
            $lockedBill = Bill::whereKey($payment->bill_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $remainingWithoutCurrentCents = Money::toCents($lockedBill->remaining_balance)
                + Money::toCents($lockedPayment->amount);
            $amountCents = Money::toCents($data['amount']);

            if ($amountCents > $remainingWithoutCurrentCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot exceed the remaining balance after other payments of ₱'.number_format(max(0, $remainingWithoutCurrentCents) / 100, 2).'.',
                ]);
            }

            $oldValues = [
                'amount' => $lockedPayment->amount,
                'paid_at' => $lockedPayment->paid_at->toDateTimeString(),
                'reference' => $lockedPayment->reference,
                'method' => $lockedPayment->method,
                'notes' => $lockedPayment->notes,
            ];
            $newValues = [
                'amount' => Money::fromCents($amountCents),
                'balance_after' => Money::fromCents(max(0, $remainingWithoutCurrentCents - $amountCents)),
                'paid_at' => Carbon::parse($data['paid_at'])->toDateTimeString(),
                'reference' => ($data['reference'] ?? null) ?: null,
                'method' => $data['method'],
                'notes' => $data['notes'] ?? null,
            ];

            if ($oldValues !== $newValues) {
                PaymentRevision::create([
                    'payment_id' => $lockedPayment->id,
                    'edited_by' => $request->user()->id,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                ]);
                $lockedPayment->update($newValues);
            }
        });

        $payment->load('bill.studentProfile');

        return redirect()
            ->route('admin.bills.show', $payment->bill)
            ->with('status', 'Payment updated. The previous values were saved in the audit history.');
    }

    public function receipt(Payment $payment): View
    {
        $payment->load(['bill.studentProfile.course', 'bill.schoolTerm', 'recordedBy']);

        return view('payments.receipt', ['payment' => $payment]);
    }
}
