<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillExtension;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\PaymentRequestSubmittedNotification;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $student = request()->user()->studentProfile;

        return view('student.bills.index', [
            'student' => $student?->load('course'),
            'bills' => $student
                ? $student->bills()->with(['schoolTerm', 'payments', 'latestPayment', 'paymentRequests', 'promissoryLetters', 'extensions', 'extensionsReceived.sourceBill', 'extensionsReceived.promissoryLetter'])->latest('billing_date')->get()
                : collect(),
            'letters' => $student
                ? $student->promissoryLetters()->latest('submitted_at')->get()
                : collect(),
            'carryForwards' => $student
                ? BillExtension::with(['sourceBill.schoolTerm', 'sourceBill.payments', 'nextSchoolTerm', 'destinationBill', 'promissoryLetter'])
                    ->whereHas('sourceBill', fn ($query) => $query->where('student_profile_id', $student->id))
                    ->orderByDesc('created_at')
                    ->get()
                : collect(),
        ]);
    }

    public function invoice(Bill $bill): View
    {
        $student = request()->user()->studentProfile;

        abort_unless($student instanceof StudentProfile && $bill->student_profile_id === $student->id, Response::HTTP_FORBIDDEN);

        $bill->load(['studentProfile.course', 'schoolTerm', 'items', 'extensions.nextSchoolTerm', 'extensionsReceived.sourceBill', 'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id')]);

        return view('invoices.show', ['bill' => $bill]);
    }

    public function statement(Bill $bill): View
    {
        $student = request()->user()->studentProfile;
        abort_unless($student instanceof StudentProfile && $bill->student_profile_id === $student->id, Response::HTTP_FORBIDDEN);

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

    public function paymentHistory(): View
    {
        $student = request()->user()->studentProfile;
        $bills = $student
            ? $student->bills()
                ->with(['schoolTerm', 'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id'), 'paymentRequests'])
                ->latest('billing_date')
                ->paginate(25)
            : Bill::whereRaw('1 = 0')->paginate(25);

        return view('student.payments.index', ['student' => $student, 'bills' => $bills]);
    }

    public function receipt(Payment $payment): View
    {
        $student = request()->user()->studentProfile;
        abort_unless($student instanceof StudentProfile && $payment->bill->student_profile_id === $student->id, Response::HTTP_FORBIDDEN);

        $payment->load(['bill.studentProfile.course', 'bill.schoolTerm', 'recordedBy']);

        return view('payments.receipt', ['payment' => $payment]);
    }

    public function submitPaymentRequest(Request $request, Bill $bill): RedirectResponse
    {
        $student = $request->user()->studentProfile;
        abort_unless($student instanceof StudentProfile && $bill->student_profile_id === $student->id, Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $paymentRequest = DB::transaction(function () use ($bill, $data, $request): PaymentRequest {
            $lockedBill = Bill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedBill->student_profile_id === $request->user()->studentProfile?->id, Response::HTTP_FORBIDDEN);

            $remainingCents = Money::toCents($lockedBill->remaining_balance);
            $paymentCents = Money::toCents($data['amount']);

            if ($remainingCents <= 0 || $paymentCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Enter an amount no greater than the remaining balance of ₱'.number_format(max(0, $remainingCents) / 100, 2).'.',
                ]);
            }

            if ($lockedBill->paymentRequests()->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'A payment request is already waiting for review for this bill.',
                ]);
            }

            return PaymentRequest::create([
                'student_profile_id' => $request->user()->studentProfile->id,
                'bill_id' => $lockedBill->id,
                'amount' => Money::fromCents($paymentCents),
                'reference' => ($data['reference'] ?? null) ?: null,
                'student_note' => $data['notes'] ?? null,
                'status' => 'Pending',
            ]);
        });

        $paymentRequest->load(['studentProfile', 'bill']);
        User::where('role', User::ROLE_ADMIN)->get()
            ->each(fn (User $admin) => $admin->notify(new PaymentRequestSubmittedNotification($paymentRequest)));

        return redirect()->route('student.dashboard')->with('status', 'Your payment request was submitted. Your balance will change after an administrator confirms the payment.');
    }
}
