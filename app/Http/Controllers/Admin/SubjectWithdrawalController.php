<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\SubjectWithdrawalRefund;
use App\Models\SubjectWithdrawalRequest;
use App\Notifications\SubjectWithdrawalStatusNotification;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubjectWithdrawalController extends Controller
{
    public function index(): View
    {
        return view('admin.subject-withdrawals.index', [
            'withdrawalRequests' => SubjectWithdrawalRequest::with([
                'studentProfile.course',
                'enrollment.subject',
                'enrollment.schoolTerm',
                'enrollment.bill',
                'reviewer',
                'refund.bill',
                'refund.recordedBy',
            ])->latest()->paginate(20),
        ]);
    }

    public function decide(Request $request, SubjectWithdrawalRequest $withdrawalRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $reviewedRequest = DB::transaction(function () use ($data, $request, $withdrawalRequest): SubjectWithdrawalRequest {
            $lockedRequest = SubjectWithdrawalRequest::whereKey($withdrawalRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedRequest->status !== 'Pending') {
                throw ValidationException::withMessages(['decision' => 'This withdrawal request has already been reviewed.']);
            }

            $enrollment = $lockedRequest->enrollment()->lockForUpdate()->firstOrFail();
            if ($enrollment->withdrawn_at !== null) {
                throw ValidationException::withMessages(['decision' => 'This subject has already been withdrawn.']);
            }

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

                return $lockedRequest->fresh(['studentProfile.user', 'enrollment.subject', 'refund']);
            }

            $refundAmount = 0;
            $bill = $enrollment->bill_id
                ? Bill::whereKey($enrollment->bill_id)->lockForUpdate()->firstOrFail()
                : null;

            if ($bill) {
                if ($bill->extensions()->exists() || $bill->promissoryLetters()->exists()) {
                    throw ValidationException::withMessages([
                        'decision' => 'This bill has promissory or carry-forward history. Resolve that history before approving the withdrawal.',
                    ]);
                }

                $subject = $enrollment->subject()->firstOrFail();
                $subjectPrefix = 'Subject '.$subject->code.' — '.$subject->name.' (';
                $subjectItem = BillItem::where('bill_id', $bill->id)
                    ->whereNull('fee_type_id')
                    ->where('description', 'like', $subjectPrefix.'%')
                    ->lockForUpdate()
                    ->first();

                if ($subjectItem && Money::toCents($subjectItem->line_total) > 0) {
                    $reducedCents = Money::toCents($subjectItem->line_total);
                    $updatedTotalCents = max(0, Money::toCents($bill->total_fees) - $reducedCents);
                    $updatedDiscountCents = min(Money::toCents($bill->scholarship_discount), $updatedTotalCents);
                    $updatedNetCents = $updatedTotalCents - $updatedDiscountCents;
                    $paidCents = Money::toCents($bill->payments()->sum('amount'));
                    $refundAmount = max(0, $paidCents - $updatedNetCents);

                    $lockedRequest->update(['credited_amount' => Money::fromCents($reducedCents)]);
                    $subjectItem->update([
                        'description' => $subjectItem->description.' (withdrawn)',
                        'unit_amount' => '0.00',
                        'line_total' => '0.00',
                    ]);
                    $bill->update([
                        'total_fees' => Money::fromCents($updatedTotalCents),
                        'scholarship_discount' => Money::fromCents($updatedDiscountCents),
                        'net_amount_due' => Money::fromCents($updatedNetCents),
                    ]);
                }
            }

            $enrollment->update(['withdrawn_at' => now()]);
            $lockedRequest->update([
                'status' => 'Approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'admin_note' => $data['admin_note'] ?? null,
            ]);

            if ($refundAmount > 0) {
                SubjectWithdrawalRefund::create([
                    'subject_withdrawal_request_id' => $lockedRequest->id,
                    'bill_id' => $bill?->id,
                    'amount' => Money::fromCents($refundAmount),
                ]);
            }

            return $lockedRequest->fresh(['studentProfile.user', 'enrollment.subject', 'refund']);
        });

        $reviewedRequest->studentProfile->user->notify(new SubjectWithdrawalStatusNotification($reviewedRequest));

        return back()->with('status', 'Subject withdrawal '.$reviewedRequest->status.'.');
    }

    public function recordRefund(Request $request, SubjectWithdrawalRefund $refund): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('subject_withdrawal_refunds', 'reference')->ignore($refund->id)],
        ]);

        DB::transaction(function () use ($data, $request, $refund): void {
            $lockedRefund = SubjectWithdrawalRefund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($lockedRefund->status !== 'Pending') {
                throw ValidationException::withMessages(['refund' => 'This refund has already been recorded as paid.']);
            }

            $lockedRefund->update([
                'status' => 'Paid',
                'recorded_by' => $request->user()->id,
                'paid_at' => now(),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
            ]);
        });

        return back()->with('status', 'Cash refund recorded as paid.');
    }
}
