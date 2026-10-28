<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamSchedule;
use App\Models\SchoolTerm;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\ExamScheduleNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExamScheduleController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->to(route('admin.catalog.index').'#exam-dates');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSchedule($request);
        $data['scope_key'] = $this->scopeKey($data);
        $this->ensureUnique($data);

        $schedule = DB::transaction(fn () => ExamSchedule::create($data));
        $this->notifyStudents($schedule);

        return back()->with('status', 'Exam date created and eligible students notified.');
    }

    public function update(Request $request, ExamSchedule $examSchedule): RedirectResponse
    {
        $data = $this->validatedSchedule($request);
        $data['scope_key'] = $this->scopeKey($data);
        $this->ensureUnique($data, $examSchedule);
        $notifyStudents = $examSchedule->exam_date->toDateString() !== $data['exam_date']
            || $examSchedule->scope_key !== $data['scope_key']
            || $examSchedule->exam_period !== $data['exam_period']
            || $examSchedule->school_term_id !== (int) $data['school_term_id'];

        DB::transaction(function () use ($examSchedule, $data): void {
            ExamSchedule::whereKey($examSchedule->id)->lockForUpdate()->firstOrFail()->update($data);
        });

        if ($notifyStudents) {
            $this->notifyStudents($examSchedule->fresh());
        }

        return back()->with('status', 'Exam date updated.');
    }

    public function destroy(ExamSchedule $examSchedule): RedirectResponse
    {
        $examSchedule->delete();

        return back()->with('status', 'Exam date deleted. Payment due dates were not changed.');
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'school_term_id' => ['required', 'integer', 'exists:school_terms,id'],
            'exam_period' => ['required', Rule::in(ExamSchedule::PERIODS)],
            'exam_date' => ['required', 'date'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'year_level' => ['nullable', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
        ]);
    }

    private function scopeKey(array $data): string
    {
        return ($data['course_id'] ?? 'all').':'.($data['year_level'] ?? 'all');
    }

    private function ensureUnique(array $data, ?ExamSchedule $ignore = null): void
    {
        $query = ExamSchedule::where('school_term_id', $data['school_term_id'])
            ->where('exam_period', $data['exam_period'])
            ->where('scope_key', $data['scope_key']);
        if ($ignore) {
            $query->where('id', '!=', $ignore->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'exam_period' => 'An exam date already exists for this term, period, and student group.',
            ]);
        }
    }

    private function notifyStudents(ExamSchedule $schedule): void
    {
        $schedule->loadMissing('schoolTerm');
        StudentProfile::query()
            ->with('user')
            ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_STUDENT))
            ->when($schedule->course_id !== null, fn ($query) => $query->where('course_id', $schedule->course_id))
            ->when($schedule->year_level !== null, fn ($query) => $query->where('year_level', $schedule->year_level))
            ->get()
            ->each(fn (StudentProfile $student) => $student->user->notify(new ExamScheduleNotification($schedule)));
    }
}
