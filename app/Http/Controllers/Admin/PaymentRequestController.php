<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Notifications\PaymentRequestStatusNotification;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(PaymentRequest::STATUSES)],
            'student' => ['nullable', 'string', 'max:100'],
        ]);

        $paymentRequests = PaymentRequest::with(['studentProfile.course', 'bill.schoolTerm', 'reviewer'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['student'] ?? null, function ($query, string $search): void {
                $query->whereHas('studentProfile', function ($query) use ($search): void {
                    $query->where('student_number', 'like', '%'.$search.'%')
                        ->orWhere('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.payment-requests.index', [
            'paymentRequests' => $paymentRequests,
            'statuses' => PaymentRequest::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function decide(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:100', 'unique:payments,reference'],
            'method' => ['required_if:decision,approve', 'nullable', 'string', 'max:50'],
        ]);

        $updatedRequest = DB::transaction(function () use ($data, $paymentRequest, $request): PaymentRequest {
            $lockedRequest = PaymentRequest::whereKey($paymentRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedRequest->status !== 'Pending' || $lockedRequest->payment_id !== null) {
                throw ValidationException::withMessages(['decision' => 'This payment request has already been reviewed.']);
            }

            $bill = Bill::whereKey($lockedRequest->bill_id)->lockForUpdate()->firstOrFail();
            if ($data['decision'] === 'reject') {
                if (trim((string) ($data['admin_note'] ?? '')) === '') {
                    throw ValidationException::withMessages(['admin_note' => 'Provide a reason for rejecting this request.']);
                }

                $lockedRequest->update([
                    'status' => 'Rejected',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'admin_note' => trim($data['admin_note']),
                ]);
            } else {
                $amountCents = Money::toCents($lockedRequest->amount);
                $remainingCents = Money::toCents($bill->remaining_balance);
                if ($amountCents <= 0 || $amountCents > $remainingCents) {
                    throw ValidationException::withMessages([
                        'decision' => 'The requested amount is above the current balance. Reject this request and ask the student to submit the updated amount.',
                    ]);
                }

                $reference = ($data['reference'] ?? null) ?: $lockedRequest->reference;
                if ($reference && Payment::where('reference', $reference)->exists()) {
                    throw ValidationException::withMessages([
                        'reference' => 'That receipt or reference number is already recorded on another payment.',
                    ]);
                }

                $payment = Payment::create([
                    'bill_id' => $bill->id,
                    'recorded_by' => $request->user()->id,
                    'amount' => Money::fromCents($amountCents),
                    'balance_after' => Money::fromCents(max(0, $remainingCents - $amountCents)),
                    'paid_at' => now(),
                    'reference' => $reference,
                    'method' => $data['method'] ?: 'Student Request',
                    'notes' => trim('Approved student payment request #'.$lockedRequest->id.'. '.($lockedRequest->student_note ?? '').' '.($data['admin_note'] ?? '')),
                ]);

                $lockedRequest->update([
                    'status' => 'Approved',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                    'admin_note' => $data['admin_note'] ?? null,
                    'payment_id' => $payment->id,
                ]);
            }

            return $lockedRequest->fresh(['studentProfile.user', 'bill']);
        });

        $updatedRequest->studentProfile->user->notify(new PaymentRequestStatusNotification($updatedRequest));
        return back()->with('status', 'Payment request '.$updatedRequest->status.'.');
    }
}
