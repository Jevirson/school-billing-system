<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillingSchedule;
use App\Models\Course;
use App\Models\SchoolTerm;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\BillingScheduleNotification;
use App\Notifications\BillDueDateNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingScheduleController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(route('admin.catalog.index').'#payment-dates');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSchedule($request);
        $data['scope_key'] = $this->scopeKey($data);
        $this->ensureUnique($data);

        $schedule = DB::transaction(fn () => BillingSchedule::create($data));
        $this->applyToBills($schedule, true);

        return back()->with('status', 'Payment due date created and applicable bills updated.');
    }

    public function update(Request $request, BillingSchedule $billingSchedule): RedirectResponse
    {
        $data = $this->validatedSchedule($request);
        $data['scope_key'] = $this->scopeKey($data);
        $this->ensureUnique($data, $billingSchedule);
        $oldTermId = $billingSchedule->school_term_id;
        $oldPeriod = $billingSchedule->billing_period;
        $notifyStudents = $billingSchedule->due_date->toDateString() !== $data['due_date']
            || $billingSchedule->scope_key !== $data['scope_key']
            || $billingSchedule->billing_period !== $data['billing_period']
            || $billingSchedule->school_term_id !== (int) $data['school_term_id'];

        DB::transaction(function () use ($billingSchedule, $data): void {
            $lockedSchedule = BillingSchedule::whereKey($billingSchedule->id)->lockForUpdate()->firstOrFail();
            $lockedSchedule->update($data);
        });

        $billingSchedule->refresh();
        $sameScopePeriod = $oldTermId === $billingSchedule->school_term_id && $oldPeriod === $billingSchedule->billing_period;
        if (! $sameScopePeriod) {
            $this->syncPeriod($billingSchedule, $oldTermId, $oldPeriod, false);
        }
        $this->syncPeriod($billingSchedule, $billingSchedule->school_term_id, $billingSchedule->billing_period, $notifyStudents);

        return back()->with('status', 'Billing schedule updated.');
    }

    public function destroy(BillingSchedule $billingSchedule): RedirectResponse
    {
        $billingSchedule->delete();

        return back()->with('status', 'Payment due date deleted. Existing bills keep their saved due dates.');
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'school_term_id' => ['required', 'integer', 'exists:school_terms,id'],
            'billing_period' => ['required', Rule::in(BillingSchedule::PERIODS)],
            'due_date' => ['required', 'date'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'year_level' => ['nullable', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
        ]);
    }

    private function scopeKey(array $data): string
    {
        return ($data['course_id'] ?? 'all').':'.($data['year_level'] ?? 'all');
    }

    private function ensureUnique(array $data, ?BillingSchedule $ignore = null): void
    {
        $query = BillingSchedule::where('school_term_id', $data['school_term_id'])
            ->where('billing_period', $data['billing_period'])
            ->where('scope_key', $data['scope_key']);
        if ($ignore) {
            $query->where('id', '!=', $ignore->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'billing_period' => 'A schedule already exists for this term, period, and student group.',
            ]);
        }
    }

    private function applyToBills(BillingSchedule $schedule, bool $notifyStudents): void
    {
        $this->syncPeriod($schedule, $schedule->school_term_id, $schedule->billing_period, $notifyStudents);
    }

    private function syncPeriod(BillingSchedule $schedule, int $termId, string $period, bool $notifyStudents): void
    {
        $notifiedStudentProfileIds = [];
        $bills = Bill::with(['studentProfile.user', 'schoolTerm', 'payments'])
            ->where('school_term_id', $termId)
            ->where('billing_period', $period)
            ->get();
        foreach ($bills as $bill) {
            $winner = BillingSchedule::resolveFor($bill->studentProfile, $termId, $period);
            if ($bill->billing_schedule_id !== $schedule->id && $winner?->id !== $schedule->id) {
                continue;
            }

            $bill->update([
                'billing_schedule_id' => $winner?->id,
                'due_date' => $winner?->due_date,
            ]);

            if ($notifyStudents && $winner?->id === $schedule->id) {
                $bill->studentProfile->user->notify(new BillDueDateNotification($bill->fresh(['schoolTerm', 'payments'])));
                $notifiedStudentProfileIds[] = $bill->student_profile_id;
            }
        }

        if (! $notifyStudents) {
            return;
        }

        $schedule->loadMissing('schoolTerm');
        StudentProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_STUDENT))
            ->when($schedule->course_id !== null, fn ($query) => $query->where('course_id', $schedule->course_id))
            ->when($schedule->year_level !== null, fn ($query) => $query->where('year_level', $schedule->year_level))
            ->whereNotIn('id', $notifiedStudentProfileIds)
            ->get()
            ->each(fn (StudentProfile $student) => $student->user->notify(new BillingScheduleNotification($schedule)));
    }
}
